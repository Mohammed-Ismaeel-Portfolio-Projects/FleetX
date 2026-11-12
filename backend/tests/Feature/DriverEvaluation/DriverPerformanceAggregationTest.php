<?php

declare(strict_types=1);

namespace Tests\Feature\DriverEvaluation;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationScoreLabel;
use App\Enums\DriverPerformanceMaturity;
use App\Enums\DriverPerformanceTrend;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\Vehicle;
use App\Services\DriverEvaluation\Aggregation\DriverPerformanceAggregationService;
use App\Support\DriverAssignmentTransitionMap;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DRIVER-EVAL-05 — driver performance aggregation & driver_scores projection.
 */
class DriverPerformanceAggregationTest extends TestCase
{
    use RefreshDatabase;

    private DriverPerformanceAggregationService $aggregation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->aggregation = app(DriverPerformanceAggregationService::class);

        config([
            'driver_evaluation.aggregation.min_established_samples' => 5,
            'driver_evaluation.aggregation.trend.window_size' => 5,
            'driver_evaluation.aggregation.trend.improving_delta' => 3.0,
            'driver_evaluation.aggregation.trend.declining_delta' => -3.0,
            'driver_evaluation.aggregation.live_only' => true,
        ]);
    }

    public function test_no_evaluations_score_unavailable_not_zero(): void
    {
        $driver = Driver::factory()->create();
        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertNull($row->score);
        $this->assertSame(DriverPerformanceMaturity::UNAVAILABLE, $row->maturity);
        $this->assertSame(DriverPerformanceTrend::INSUFFICIENT_DATA, $row->trend);
        $this->assertSame(0, (int) $row->evaluated_assignments_count);
        $this->assertSame('1.0.0', $row->aggregation_version);
        $this->assertFalse($row->isScoreAvailable());
    }

    public function test_one_evaluation_is_provisional_and_deterministic(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 88.0, confidence: AssignmentEvaluationConfidence::HIGH);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertEquals(88.0, (float) $row->score);
        $this->assertSame(DriverEvaluationScoreLabel::GOOD->value, $row->score_label);
        $this->assertSame(DriverPerformanceMaturity::PROVISIONAL, $row->maturity);
        $this->assertSame(AssignmentEvaluationConfidence::MEDIUM, $row->confidence);
        $this->assertSame(1, (int) $row->evaluated_assignments_count);
        $this->assertSame(1, (int) $row->total_deliveries);
        $this->assertSame(DriverPerformanceTrend::INSUFFICIENT_DATA, $row->trend);
    }

    public function test_multiple_evaluations_equal_weight_aggregate(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 80.0, finalizedAt: now()->subDays(3));
        $this->seedCompletedEvaluation($driver, 90.0, finalizedAt: now()->subDays(2));
        $this->seedCompletedEvaluation($driver, 100.0, finalizedAt: now()->subDay());

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertEquals(90.0, (float) $row->score); // (80+90+100)/3
        $this->assertSame(3, (int) $row->evaluated_assignments_count);
        $this->assertSame(DriverPerformanceMaturity::PROVISIONAL, $row->maturity);
        $this->assertGreaterThanOrEqual(0.0, (float) $row->score);
        $this->assertLessThanOrEqual(100.0, (float) $row->score);
    }

    public function test_superseded_revision_excluded(): void
    {
        $driver = Driver::factory()->create();
        $assignment = $this->makeAssignment(['driver_id' => $driver->id]);

        $this->createEvaluation($assignment, [
            'revision' => 1,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'overall_score' => 50.0,
            'finalized_at' => now()->subDay(),
        ]);
        $this->createEvaluation($assignment, [
            'revision' => 2,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'overall_score' => 95.0,
            'finalized_at' => now(),
            'supersedes_evaluation_id' => AssignmentEvaluation::query()
                ->where('assignment_id', $assignment->id)
                ->where('revision', 1)
                ->value('id'),
        ]);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertEquals(95.0, (float) $row->score);
        $this->assertSame(1, (int) $row->evaluated_assignments_count);
    }

    public function test_invalidated_evaluation_excluded(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 92.0);
        $bad = $this->seedCompletedEvaluation($driver, 10.0);
        $bad->status = AssignmentEvaluationStatus::INVALIDATED;
        $bad->save();

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertEquals(92.0, (float) $row->score);
        $this->assertSame(1, (int) $row->evaluated_assignments_count);
    }

    public function test_incomplete_does_not_penalize_score(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 90.0);
        $assignment = $this->makeAssignment(['driver_id' => $driver->id]);
        $this->createEvaluation($assignment, [
            'status' => AssignmentEvaluationStatus::INCOMPLETE,
            'overall_score' => null,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::INSUFFICIENT,
            'finalized_at' => now(),
        ]);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertEquals(90.0, (float) $row->score);
        $this->assertSame(1, (int) $row->evaluated_assignments_count);
        $this->assertSame(1, (int) $row->incomplete_assignments_count);
    }

    public function test_not_evaluable_category_excluded_from_category_average(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 90.0, categoryScores: [
            'safety' => ['evaluable' => true, 'score' => 80.0, 'configured_weight' => 0.35, 'normalized_weight' => 0.35, 'event_contributions' => [], 'skipped_overlaps' => []],
            'driving_behavior' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.25, 'normalized_weight' => 0.25, 'event_contributions' => [], 'skipped_overlaps' => []],
            'route_compliance' => ['evaluable' => false, 'score' => null, 'configured_weight' => 0.15, 'normalized_weight' => null, 'event_contributions' => [], 'skipped_overlaps' => [], 'reason' => 'insufficient_evidence_for_category'],
            'timeliness' => ['evaluable' => true, 'score' => 90.0, 'configured_weight' => 0.15, 'normalized_weight' => 0.15, 'event_contributions' => [], 'skipped_overlaps' => []],
            'workflow_discipline' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.10, 'normalized_weight' => 0.10, 'event_contributions' => [], 'skipped_overlaps' => []],
        ]);
        $this->seedCompletedEvaluation($driver, 90.0, categoryScores: [
            'safety' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.35, 'normalized_weight' => 0.35, 'event_contributions' => [], 'skipped_overlaps' => []],
            'driving_behavior' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.25, 'normalized_weight' => 0.25, 'event_contributions' => [], 'skipped_overlaps' => []],
            'route_compliance' => ['evaluable' => true, 'score' => 70.0, 'configured_weight' => 0.15, 'normalized_weight' => 0.15, 'event_contributions' => [], 'skipped_overlaps' => []],
            'timeliness' => ['evaluable' => true, 'score' => 90.0, 'configured_weight' => 0.15, 'normalized_weight' => 0.15, 'event_contributions' => [], 'skipped_overlaps' => []],
            'workflow_discipline' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.10, 'normalized_weight' => 0.10, 'event_contributions' => [], 'skipped_overlaps' => []],
        ]);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);
        $categories = $row->category_scores;

        $this->assertEquals(90.0, (float) $categories['safety']['score']); // (80+100)/2
        $this->assertEquals(70.0, (float) $categories['route_compliance']['score']); // only one evaluable
        $this->assertSame(1, (int) $categories['route_compliance']['sample_count']);
        $this->assertSame(2, (int) $categories['safety']['sample_count']);
    }

    public function test_trend_improving_declining_stable_and_insufficient(): void
    {
        $driver = Driver::factory()->create();

        // insufficient
        $this->seedCompletedEvaluation($driver, 80.0, finalizedAt: now()->subDays(20));
        $row = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertSame(DriverPerformanceTrend::INSUFFICIENT_DATA, $row->trend);

        // Build 10 scores: previous 5 low, recent 5 high → improving
        $scores = [70, 72, 74, 76, 78, 90, 91, 92, 93, 94];
        DriverScore::query()->where('driver_id', $driver->id)->delete();
        AssignmentEvaluation::query()->where('driver_id', $driver->id)->delete();
        $base = Carbon::parse('2026-06-01 12:00:00');
        foreach ($scores as $i => $score) {
            $this->seedCompletedEvaluation(
                $driver,
                (float) $score,
                finalizedAt: $base->copy()->addDays($i),
            );
        }
        $improving = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertSame(DriverPerformanceTrend::IMPROVING, $improving->trend);
        $this->assertGreaterThan(0, (float) $improving->trend_delta);
        $this->assertSame(DriverPerformanceMaturity::ESTABLISHED, $improving->maturity);

        // declining
        AssignmentEvaluation::query()->where('driver_id', $driver->id)->delete();
        DriverScore::query()->where('driver_id', $driver->id)->delete();
        $decliningScores = [95, 94, 93, 92, 91, 70, 71, 72, 73, 74];
        foreach ($decliningScores as $i => $score) {
            $this->seedCompletedEvaluation(
                $driver,
                (float) $score,
                finalizedAt: $base->copy()->addDays($i),
            );
        }
        $declining = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertSame(DriverPerformanceTrend::DECLINING, $declining->trend);

        // stable
        AssignmentEvaluation::query()->where('driver_id', $driver->id)->delete();
        DriverScore::query()->where('driver_id', $driver->id)->delete();
        $stableScores = [88, 89, 87, 88, 90, 89, 88, 87, 89, 88];
        foreach ($stableScores as $i => $score) {
            $this->seedCompletedEvaluation(
                $driver,
                (float) $score,
                finalizedAt: $base->copy()->addDays($i),
            );
        }
        $stable = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertSame(DriverPerformanceTrend::STABLE, $stable->trend);
    }

    public function test_driver_isolation_and_historical_attribution(): void
    {
        $driverA = Driver::factory()->create();
        $driverB = Driver::factory()->create();
        $this->seedCompletedEvaluation($driverA, 100.0);
        $this->seedCompletedEvaluation($driverB, 60.0);

        $a = $this->aggregation->aggregateForDriver((int) $driverA->id);
        $b = $this->aggregation->aggregateForDriver((int) $driverB->id);

        $this->assertEquals(100.0, (float) $a->score);
        $this->assertEquals(60.0, (float) $b->score);
        $this->assertSame(1, (int) $a->evaluated_assignments_count);
        $this->assertSame(1, (int) $b->evaluated_assignments_count);
    }

    public function test_repeated_aggregation_is_idempotent(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 85.0);
        $this->seedCompletedEvaluation($driver, 95.0);

        $first = $this->aggregation->aggregateForDriver((int) $driver->id);
        $second = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertSame(1, DriverScore::query()->where('driver_id', $driver->id)->count());
        $this->assertEquals((float) $first->score, (float) $second->score);
        $this->assertEquals($first->category_scores, $second->category_scores);
        $this->assertSame($first->aggregation_version, $second->aggregation_version);
        $this->assertSame(
            $first->aggregation_metadata['eligible_evaluation_ids'],
            $second->aggregation_metadata['eligible_evaluation_ids'],
        );
    }

    public function test_pending_and_processing_excluded(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 90.0);
        $assignment = $this->makeAssignment(['driver_id' => $driver->id]);
        $this->createEvaluation($assignment, [
            'status' => AssignmentEvaluationStatus::PENDING,
            'overall_score' => 10.0,
            'finalized_at' => null,
        ]);
        $assignment2 = $this->makeAssignment(['driver_id' => $driver->id]);
        $this->createEvaluation($assignment2, [
            'status' => AssignmentEvaluationStatus::PROCESSING,
            'overall_score' => 5.0,
            'finalized_at' => null,
        ]);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertEquals(90.0, (float) $row->score);
        $this->assertSame(1, (int) $row->evaluated_assignments_count);
    }

    public function test_simulation_excluded_when_live_only(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 100.0, mode: AssignmentEvaluationMode::LIVE);
        $this->seedCompletedEvaluation($driver, 40.0, mode: AssignmentEvaluationMode::SIMULATION);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertEquals(100.0, (float) $row->score);
        $this->assertSame(1, (int) $row->evaluated_assignments_count);
    }

    public function test_late_deliveries_from_timeliness_evidence(): void
    {
        $driver = Driver::factory()->create();
        $categories = $this->perfectCategories();
        $categories['timeliness']['event_contributions'] = [
            ['event_code' => 'late_arrival', 'applied_impact' => 12],
        ];
        $this->seedCompletedEvaluation($driver, 88.0, categoryScores: $categories);
        $this->seedCompletedEvaluation($driver, 100.0);

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->assertSame(1, (int) $row->late_deliveries);
        $this->assertSame(2, (int) $row->total_deliveries);
    }

    public function test_no_side_effects_on_tracking_assignment_shipment(): void
    {
        $driver = Driver::factory()->create();
        $evaluation = $this->seedCompletedEvaluation($driver, 91.0);
        $assignmentId = $evaluation->assignment_id;
        $shipmentId = $evaluation->shipment_id;

        $trackingBefore = TrackingLog::query()->count();
        $assignmentBefore = DB::table('assignments')->where('id', $assignmentId)->first();
        $shipmentBefore = DB::table('shipments')->where('id', $shipmentId)->first();
        $invoiceBefore = DB::table('monthly_invoices')->count();

        $this->aggregation->aggregateForDriver((int) $driver->id);
        $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertSame($trackingBefore, TrackingLog::query()->count());
        $this->assertEquals(
            (array) $assignmentBefore,
            (array) DB::table('assignments')->where('id', $assignmentId)->first(),
        );
        $this->assertEquals(
            (array) $shipmentBefore,
            (array) DB::table('shipments')->where('id', $shipmentId)->first(),
        );
        $this->assertSame($invoiceBefore, DB::table('monthly_invoices')->count());
        $this->assertSame(
            91.0,
            (float) AssignmentEvaluation::query()->whereKey($evaluation->id)->value('overall_score'),
        );
    }

    public function test_score_label_and_versions_persisted(): void
    {
        $driver = Driver::factory()->create();
        $this->seedCompletedEvaluation($driver, 96.0, scoringVersion: '1.0.0');

        $row = $this->aggregation->aggregateForDriver((int) $driver->id);

        $this->assertSame(DriverEvaluationScoreLabel::EXCELLENT->value, $row->score_label);
        $this->assertSame('1.0.0', $row->aggregation_version);
        $this->assertContains('1.0.0', $row->aggregation_metadata['scoring_versions_seen']);
        $this->assertSame('equal_weight_with_trend', $row->aggregation_metadata['strategy']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedCompletedEvaluation(
        Driver $driver,
        float $overallScore,
        ?Carbon $finalizedAt = null,
        AssignmentEvaluationConfidence $confidence = AssignmentEvaluationConfidence::HIGH,
        ?array $categoryScores = null,
        AssignmentEvaluationMode $mode = AssignmentEvaluationMode::LIVE,
        string $scoringVersion = '1.0.0',
    ): AssignmentEvaluation {
        $assignment = $this->makeAssignment(['driver_id' => $driver->id]);

        return $this->createEvaluation($assignment, [
            'evaluation_mode' => $mode,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'overall_score' => $overallScore,
            'score_label' => DriverEvaluationScoreLabel::fromScore($overallScore)->value,
            'confidence' => $confidence,
            'scoring_version' => $scoringVersion,
            'category_scores' => $categoryScores ?? $this->perfectCategories(),
            'finalized_at' => $finalizedAt ?? now(),
            'completed_at' => $finalizedAt ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEvaluation(Assignment $assignment, array $overrides = []): AssignmentEvaluation
    {
        return AssignmentEvaluation::query()->create(array_merge([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $assignment->driver_id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'revision' => 1,
            'overall_score' => 100.0,
            'score_label' => DriverEvaluationScoreLabel::EXCELLENT->value,
            'confidence' => AssignmentEvaluationConfidence::HIGH,
            'scoring_version' => '1.0.0',
            'category_scores' => $this->perfectCategories(),
            'scoring_metadata' => ['scoring_version' => '1.0.0'],
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'finalized_at' => now(),
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function perfectCategories(): array
    {
        $keys = ['safety', 'driving_behavior', 'route_compliance', 'timeliness', 'workflow_discipline'];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = [
                'evaluable' => true,
                'score' => 100.0,
                'configured_weight' => 0.2,
                'normalized_weight' => 0.2,
                'event_contributions' => [],
                'skipped_overlaps' => [],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAssignment(array $overrides = []): Assignment
    {
        Status::firstOrCreate(
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment'],
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment'],
        );

        return Assignment::factory()->create(array_merge([
            'shipment_id' => Shipment::factory()->create()->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => null,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
            'ended_at' => Carbon::parse('2026-08-02 18:00:00'),
            'is_active' => false,
        ], $overrides));
    }
}
