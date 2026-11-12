<?php

declare(strict_types=1);

namespace Tests\Feature\DriverEvaluation;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Enums\DriverEvaluationScoreLabel;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Vehicle;
use App\Services\DriverEvaluation\AssignmentEvaluationPersistenceService;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use App\Services\DriverEvaluation\Scoring\DriverEvaluationScoringEngine;
use App\Services\DriverEvaluation\Scoring\DriverEvaluationScoringService;
use App\Services\DriverEvaluation\Scoring\EvaluationScoringContext;
use App\Support\DriverAssignmentTransitionMap;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DRIVER-EVAL-04 — explainable assignment scoring engine.
 */
class DriverEvaluationScoringTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentEvaluationPersistenceService $persistence;

    private DriverEvaluationScoringService $scoring;

    private DriverEvaluationScoringEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->persistence = app(AssignmentEvaluationPersistenceService::class);
        $this->scoring = app(DriverEvaluationScoringService::class);
        $this->engine = app(DriverEvaluationScoringEngine::class);
    }

    public function test_no_negative_events_produces_perfect_score(): void
    {
        $evaluation = $this->mutableEvaluation();
        $result = $this->scoring->scoreAndFinalize(
            $evaluation,
            new EvaluationScoringContext(
                telemetrySampleCount: 20,
                routeGeometryAvailable: true,
                timingDataAvailable: true,
                workflowDataAvailable: true,
            ),
        );

        $this->assertSame(AssignmentEvaluationStatus::COMPLETED, $result->status);
        $this->assertEquals(100.0, (float) $result->overall_score);
        $this->assertSame(DriverEvaluationScoreLabel::EXCELLENT->value, $result->score_label);
        $this->assertSame(AssignmentEvaluationConfidence::HIGH, $result->confidence);
        $this->assertSame('1.0.0', $result->scoring_version);
        foreach ($result->category_scores as $category) {
            $this->assertTrue($category['evaluable']);
            $this->assertEquals(100.0, $category['score']);
        }
    }

    public function test_single_speeding_reduces_safety_and_overall(): void
    {
        $evaluation = $this->mutableEvaluation();
        $this->addEvent($evaluation, DriverEvaluationEventCode::SPEEDING, [1, 2, 3]);

        $result = $this->scoring->scoreAndFinalize(
            $evaluation->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertEquals(88.0, (float) $result->category_scores['safety']['score']); // 100-12
        $this->assertLessThan(100.0, (float) $result->overall_score);
        $this->assertGreaterThan(0.0, (float) $result->overall_score);
        $this->assertNotEmpty($result->category_scores['safety']['event_contributions']);
        $this->assertEquals(12.0, (float) $result->category_scores['safety']['event_contributions'][0]['base_impact']);
    }

    public function test_category_event_impacts_map_correctly(): void
    {
        $cases = [
            [DriverEvaluationEventCode::HARSH_BRAKING, 'safety', 10.0],
            [DriverEvaluationEventCode::SUDDEN_STOP, 'safety', 14.0],
            [DriverEvaluationEventCode::HARSH_ACCELERATION, 'driving_behavior', 8.0],
            [DriverEvaluationEventCode::EXCESSIVE_IDLE, 'driving_behavior', 6.0],
            [DriverEvaluationEventCode::ROUTE_DEVIATION, 'route_compliance', 15.0],
            [DriverEvaluationEventCode::LATE_ARRIVAL, 'timeliness', 12.0],
            [DriverEvaluationEventCode::WORKFLOW_VIOLATION, 'workflow_discipline', 20.0],
        ];

        foreach ($cases as [$code, $category, $impact]) {
            $evaluation = $this->mutableEvaluation();
            $this->addEvent($evaluation, $code, [10, 11]);
            $scored = $this->scoring->scoreAndFinalize(
                $evaluation->fresh(),
                new EvaluationScoringContext(10, true, true, true),
            );
            $this->assertEquals(
                100.0 - $impact,
                (float) $scored->category_scores[$category]['score'],
                "Failed for {$code->value}",
            );
        }
    }

    public function test_weights_renormalize_when_category_not_evaluable(): void
    {
        $evaluation = $this->mutableEvaluation();
        // No telemetry, no route, no timing → only workflow evaluable by default flag.
        $result = $this->engine->score(
            $evaluation->events,
            new EvaluationScoringContext(
                telemetrySampleCount: 0,
                routeGeometryAvailable: false,
                timingDataAvailable: false,
                workflowDataAvailable: true,
            ),
        );

        $this->assertTrue($result->isScoreAvailable);
        $this->assertFalse($result->categories[0]['evaluable']); // safety
        $workflow = collect($result->categories)->firstWhere('key', 'workflow_discipline');
        $this->assertTrue($workflow['evaluable']);
        $this->assertEquals(1.0, $workflow['normalized_weight']);
        $this->assertEquals(100.0, $result->overallScore);
    }

    public function test_missing_evidence_does_not_create_penalty(): void
    {
        $evaluation = $this->mutableEvaluation();
        $withTelemetry = $this->engine->score(
            $evaluation->events,
            new EvaluationScoringContext(20, true, true, true),
        );
        $withoutRoute = $this->engine->score(
            $evaluation->events,
            new EvaluationScoringContext(20, false, true, true),
        );

        $this->assertEquals(100.0, $withTelemetry->overallScore);
        $this->assertEquals(100.0, $withoutRoute->overallScore);
        $route = collect($withoutRoute->categories)->firstWhere('key', 'route_compliance');
        $this->assertFalse($route['evaluable']);
        $this->assertNull($route['score']);
    }

    public function test_score_bounds_and_repetition_decay(): void
    {
        $evaluation = $this->mutableEvaluation();
        for ($i = 0; $i < 20; $i++) {
            $this->addEvent($evaluation, DriverEvaluationEventCode::SPEEDING, [$i + 1], occurredAt: now()->addSeconds($i));
        }

        $result = $this->scoring->scoreAndFinalize(
            $evaluation->fresh(),
            new EvaluationScoringContext(50, true, true, true),
        );

        $this->assertGreaterThanOrEqual(0.0, (float) $result->overall_score);
        $this->assertLessThanOrEqual(100.0, (float) $result->overall_score);
        $this->assertGreaterThanOrEqual(0.0, (float) $result->category_scores['safety']['score']);
        $contributions = $result->category_scores['safety']['event_contributions'];
        $this->assertEquals(1.0, (float) $contributions[0]['repetition_multiplier']);
        $this->assertEqualsWithDelta(0.7, (float) $contributions[1]['repetition_multiplier'], 0.0001);
    }

    public function test_overlap_skips_secondary_harsh_braking_with_sudden_stop(): void
    {
        $evaluation = $this->mutableEvaluation();
        $this->addEvent($evaluation, DriverEvaluationEventCode::SUDDEN_STOP, [100, 101, 102]);
        $this->addEvent($evaluation, DriverEvaluationEventCode::HARSH_BRAKING, [101, 102, 103]);

        $result = $this->scoring->scoreAndFinalize(
            $evaluation->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        // Only sudden_stop (14) applies; harsh braking overlapped.
        $this->assertEquals(86.0, (float) $result->category_scores['safety']['score']);
        $this->assertCount(1, $result->category_scores['safety']['event_contributions']);
        $this->assertNotEmpty($result->category_scores['safety']['skipped_overlaps']);
    }

    public function test_late_arrival_suppresses_abnormal_duration(): void
    {
        $evaluation = $this->mutableEvaluation();
        $this->addEvent($evaluation, DriverEvaluationEventCode::LATE_ARRIVAL, []);
        $this->addEvent($evaluation, DriverEvaluationEventCode::ABNORMAL_DURATION, []);

        $result = $this->scoring->scoreAndFinalize(
            $evaluation->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertEquals(88.0, (float) $result->category_scores['timeliness']['score']); // 100-12
        $this->assertCount(1, $result->category_scores['timeliness']['event_contributions']);
    }

    public function test_duplicate_processing_is_deterministic_and_idempotent_events(): void
    {
        $evaluation = $this->mutableEvaluation();
        $this->addEvent($evaluation, DriverEvaluationEventCode::SPEEDING, [1, 2, 3]);

        $a = $this->engine->score(
            $evaluation->fresh()->events,
            new EvaluationScoringContext(20, true, true, true),
        );
        $b = $this->engine->score(
            $evaluation->fresh()->events,
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertEquals($a->overallScore, $b->overallScore);
        $this->assertEquals($a->categoryScoresPayload(), $b->categoryScoresPayload());
    }

    public function test_unsupported_event_code_does_not_affect_score(): void
    {
        $evaluation = $this->mutableEvaluation();
        // Persist via raw insert bypassing supported check is not allowed; score ignores unmapped.
        AssignmentEvaluationEvent::query()->create([
            'assignment_evaluation_id' => $evaluation->id,
            'assignment_id' => $evaluation->assignment_id,
            'shipment_id' => $evaluation->shipment_id,
            'driver_id' => $evaluation->driver_id,
            'vehicle_id' => $evaluation->vehicle_id,
            'event_code' => DriverEvaluationEventCode::HARSH_CORNERING,
            'category' => DriverEvaluationEventCode::HARSH_CORNERING->category(),
            'severity' => DriverEvaluationEventCode::HARSH_CORNERING->defaultSeverity(),
            'occurred_at' => now(),
            'evidence_source' => DriverEvaluationEvidenceSource::LIVE,
            'detector_code' => 'eval.corner',
            'detector_version' => '0.0.0',
            'idempotency_key' => 'unsupported-corner-'.$evaluation->id,
            'evidence_tracking_log_ids' => [1],
        ]);

        $result = $this->scoring->scoreAndFinalize(
            $evaluation->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertEquals(100.0, (float) $result->overall_score);
    }

    public function test_confidence_levels_and_do_not_alter_score(): void
    {
        $evaluation = $this->mutableEvaluation();
        $high = $this->engine->score($evaluation->events, new EvaluationScoringContext(20, true, true, true));
        $medium = $this->engine->score($evaluation->events, new EvaluationScoringContext(20, false, true, true));
        $low = $this->engine->score($evaluation->events, new EvaluationScoringContext(0, false, false, true));

        $this->assertSame(AssignmentEvaluationConfidence::HIGH, $high->confidence);
        $this->assertSame(AssignmentEvaluationConfidence::MEDIUM, $medium->confidence);
        $this->assertSame(AssignmentEvaluationConfidence::LOW, $low->confidence);
        $this->assertEquals($high->overallScore, $medium->overallScore);
    }

    public function test_finalized_evaluation_immutable_and_revision_reprocess(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());
        $final = $this->scoring->scoreAndFinalize(
            $evaluation->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->expectException(DriverEvaluationException::class);
        $this->scoring->scoreAndFinalize($final->fresh());
    }

    public function test_reprocess_creates_new_revision_with_new_score(): void
    {
        $assignment = $this->makeAssignment();
        $first = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($first->fresh());
        $this->addEvent($first, DriverEvaluationEventCode::SPEEDING, [1, 2, 3]);
        $final1 = $this->scoring->scoreAndFinalize(
            $first->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $second = $this->persistence->createForAssignment($assignment, AssignmentEvaluationMode::LIVE, [], $final1);
        $this->persistence->markProcessing($second->fresh());
        $final2 = $this->scoring->scoreAndFinalize(
            $second->fresh(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertSame(1, (int) $final1->revision);
        $this->assertSame(2, (int) $final2->revision);
        $this->assertSame($final1->id, $final2->supersedes_evaluation_id);
        $this->assertEquals(100.0, (float) $final2->overall_score);
        $this->assertLessThan(100.0, (float) $final1->overall_score);
        $this->assertEquals((float) $final1->fresh()->overall_score, (float) $final1->overall_score);
    }

    public function test_scoring_version_persisted_and_driver_projection_refreshed(): void
    {
        $evaluation = $this->mutableEvaluation();
        $final = $this->scoring->scoreAndFinalize(
            $evaluation,
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertSame('1.0.0', $final->scoring_version);
        $this->assertSame('eval.score', $final->scoring_metadata['scoring_code']);
        $this->assertArrayHasKey('configured_weights', $final->scoring_metadata);

        $projection = DriverScore::query()->where('driver_id', $final->driver_id)->first();
        $this->assertNotNull($projection);
        $this->assertEquals(100.0, (float) $projection->score);
        $this->assertSame('1.0.0', $projection->aggregation_version);
    }

    public function test_event_order_does_not_change_final_score(): void
    {
        $evaluation = $this->mutableEvaluation();
        $this->addEvent($evaluation, DriverEvaluationEventCode::SPEEDING, [1], now()->addMinute());
        $this->addEvent($evaluation, DriverEvaluationEventCode::HARSH_BRAKING, [2], now());

        $forward = $this->engine->score(
            $evaluation->fresh()->events()->orderBy('id')->get(),
            new EvaluationScoringContext(20, true, true, true),
        );
        $reverse = $this->engine->score(
            $evaluation->fresh()->events()->orderByDesc('id')->get(),
            new EvaluationScoringContext(20, true, true, true),
        );

        $this->assertEquals($forward->overallScore, $reverse->overallScore);
        $this->assertEquals(
            $forward->categoryScoresPayload()['safety']['score'],
            $reverse->categoryScoresPayload()['safety']['score'],
        );
    }

    private function mutableEvaluation(): AssignmentEvaluation
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());

        return $evaluation->fresh();
    }

    private function addEvent(
        AssignmentEvaluation $evaluation,
        DriverEvaluationEventCode $code,
        array $logIds,
        ?Carbon $occurredAt = null,
    ): void {
        $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: $code,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.test',
            detectorVersion: '1.0.0',
            occurredAt: $occurredAt ?? now(),
            trackingLogIds: $logIds,
            windowStart: now()->subSeconds(10),
            windowEnd: now(),
        );
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
            'shipment_id' => Shipment::factory()->create([
                'planned_pickup_date' => '2026-08-01',
                'planned_delivery_date' => '2026-08-02',
            ])->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => null,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
            'ended_at' => null,
            'is_active' => true,
        ], $overrides));
    }
}
