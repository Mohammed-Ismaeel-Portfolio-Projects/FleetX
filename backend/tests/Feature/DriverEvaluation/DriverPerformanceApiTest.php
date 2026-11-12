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
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverEvaluation\Aggregation\DriverPerformanceAggregationService;
use App\Support\DriverAssignmentTransitionMap;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DRIVER-EVAL-06 — performance read APIs + RBAC.
 */
class DriverPerformanceApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $driver = Driver::factory()->create();
        $this->getJson("/api/v1/drivers/{$driver->id}/performance")->assertUnauthorized();
        $this->getJson('/api/v1/driver/performance')->assertUnauthorized();
    }

    public function test_client_forbidden_on_ops_and_self(): void
    {
        $driver = Driver::factory()->create();
        $token = $this->tokenForRole('client');

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/drivers/{$driver->id}/performance")->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/driver/performance')->assertForbidden();
    }

    public function test_admin_and_dispatcher_can_view_performance(): void
    {
        $driver = Driver::factory()->create();
        $this->seedProjection($driver, 87.5);

        foreach (['admin', 'dispatcher'] as $role) {
            $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole($role))
                ->getJson("/api/v1/drivers/{$driver->id}/performance")
                ->assertOk()
                ->assertJsonPath('data.overall_score', 87.5)
                ->assertJsonPath('data.evaluated_assignments_count', 2)
                ->assertJsonPath('data.late_evaluations_count', 1)
                ->assertJsonPath('data.maturity', 'provisional');

            $this->assertSame(87.5, (float) $response->json('data.score'));
            $this->assertArrayHasKey('category_scores', $response->json('data'));
        }
    }

    public function test_driver_can_view_own_but_not_another(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('perf-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('perf-b@fleetx.test');
        $this->seedProjection($driverA, 91.0);
        $this->seedProjection($driverB, 55.0);

        $tokenA = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/driver/performance')
            ->assertOk()
            ->assertJsonPath('data.driver_id', $driverA->id)
            ->assertJsonPath('data.overall_score', 91);

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/drivers/{$driverB->id}/performance")
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/driver/performance')
            ->assertJsonMissing(['overall_score' => 55]);
    }

    public function test_no_data_driver_returns_null_score_unavailable(): void
    {
        $driver = Driver::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/drivers/{$driver->id}/performance")
            ->assertOk()
            ->assertJsonPath('data.overall_score', null)
            ->assertJsonPath('data.score', null)
            ->assertJsonPath('data.maturity', 'unavailable')
            ->assertJsonPath('data.score_available', false)
            ->assertJsonPath('data.trend', 'insufficient_data');
    }

    public function test_performance_matches_driver_scores_projection_without_reaggregation(): void
    {
        $driver = Driver::factory()->create();
        $row = $this->seedProjection($driver, 82.0);
        $before = $row->fresh()->aggregated_at?->toIso8601String();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/drivers/{$driver->id}/performance")
            ->assertOk()
            ->assertJsonPath('data.overall_score', 82)
            ->assertJsonPath('data.trend', $row->trend->value)
            ->assertJsonPath('data.aggregation_version', '1.0.0');

        $this->assertSame($before, $row->fresh()->aggregated_at?->toIso8601String());
        $this->assertSame(1, DriverScore::query()->where('driver_id', $driver->id)->count());
    }

    public function test_evaluation_history_pagination_and_exclusions(): void
    {
        $driver = Driver::factory()->create();
        $keep = $this->createFinalized($driver, 90.0, revision: 2);
        $this->createFinalized($driver, 40.0, revision: 1, assignment: Assignment::find($keep->assignment_id)); // superseded
        $this->createFinalized($driver, 10.0, status: AssignmentEvaluationStatus::INVALIDATED);
        $this->createFinalized($driver, null, status: AssignmentEvaluationStatus::INCOMPLETE);
        $this->createFinalized($driver, 5.0, status: AssignmentEvaluationStatus::PENDING, finalized: false);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('dispatcher'))
            ->getJson("/api/v1/drivers/{$driver->id}/performance/evaluations?per_page=10")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('evaluation_id')->all();
        $this->assertContains($keep->id, $ids);
        $this->assertCount(2, $ids); // revision2 completed + incomplete latest on other assignment
        $this->assertSame(1, (int) $response->json('data.meta.current_page'));
    }

    public function test_evaluation_detail_explainability_and_idor(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('detail-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('detail-b@fleetx.test');
        $evalA = $this->createFinalized($driverA, 88.0);
        $evalB = $this->createFinalized($driverB, 70.0);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/drivers/{$driverA->id}/performance/evaluations/{$evalA->id}")
            ->assertOk()
            ->assertJsonPath('data.overall_score', 88)
            ->assertJsonPath('data.scoring_version', '1.0.0')
            ->assertJsonStructure(['data' => ['category_scores', 'events', 'configured_weights']]);

        $tokenA = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/driver/performance/evaluations/{$evalA->id}")
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/driver/performance/evaluations/{$evalB->id}")
            ->assertNotFound();
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/drivers/{$driverB->id}/performance/evaluations/{$evalB->id}")
            ->assertForbidden();
    }

    public function test_get_is_read_only_no_domain_mutations(): void
    {
        $driver = Driver::factory()->create();
        $evaluation = $this->createFinalized($driver, 93.0);
        app(DriverPerformanceAggregationService::class)->aggregateForDriver((int) $driver->id);

        $trackingBefore = TrackingLog::query()->count();
        $assignmentBefore = DB::table('assignments')->where('id', $evaluation->assignment_id)->first();
        $shipmentBefore = DB::table('shipments')->where('id', $evaluation->shipment_id)->first();
        $invoiceBefore = DB::table('monthly_invoices')->count();
        $scoreBefore = DriverScore::query()->where('driver_id', $driver->id)->first()?->toArray();
        $evalBefore = $evaluation->fresh()->toArray();

        $token = $this->tokenForRole('admin');
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/drivers/{$driver->id}/performance")->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/drivers/{$driver->id}/performance/evaluations")->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/drivers/{$driver->id}/performance/evaluations/{$evaluation->id}")
            ->assertOk();

        $this->assertSame($trackingBefore, TrackingLog::query()->count());
        $this->assertEquals((array) $assignmentBefore, (array) DB::table('assignments')->where('id', $evaluation->assignment_id)->first());
        $this->assertEquals((array) $shipmentBefore, (array) DB::table('shipments')->where('id', $evaluation->shipment_id)->first());
        $this->assertSame($invoiceBefore, DB::table('monthly_invoices')->count());
        $this->assertEquals($scoreBefore, DriverScore::query()->where('driver_id', $driver->id)->first()?->toArray());
        $this->assertEquals($evalBefore, $evaluation->fresh()->toArray());
    }

    public function test_score_bounded_or_null(): void
    {
        $driver = Driver::factory()->create();
        $this->seedProjection($driver, 100.0);

        $score = $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/drivers/{$driver->id}/performance")
            ->assertOk()
            ->json('data.overall_score');

        $this->assertTrue($score === null || ($score >= 0 && $score <= 100));
    }

    private function seedProjection(Driver $driver, float $score): DriverScore
    {
        $this->createFinalized($driver, $score);
        $late = $this->createFinalized($driver, $score, categoryScores: [
            'timeliness' => [
                'evaluable' => true,
                'score' => 80.0,
                'configured_weight' => 0.15,
                'normalized_weight' => 0.15,
                'event_contributions' => [['event_code' => 'late_arrival', 'applied_impact' => 12]],
                'skipped_overlaps' => [],
            ],
        ]);

        return app(DriverPerformanceAggregationService::class)->aggregateForDriver((int) $driver->id);
    }

    /**
     * @param  array<string, mixed>|null  $categoryScores
     */
    private function createFinalized(
        Driver $driver,
        ?float $overallScore,
        AssignmentEvaluationStatus $status = AssignmentEvaluationStatus::COMPLETED,
        int $revision = 1,
        ?Assignment $assignment = null,
        bool $finalized = true,
        ?array $categoryScores = null,
    ): AssignmentEvaluation {
        $assignment ??= $this->makeAssignment(['driver_id' => $driver->id]);

        return AssignmentEvaluation::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $driver->id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => $status,
            'evidence_quality' => $status === AssignmentEvaluationStatus::INCOMPLETE
                ? AssignmentEvaluationEvidenceQuality::INSUFFICIENT
                : AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'revision' => $revision,
            'overall_score' => $overallScore,
            'score_label' => $overallScore !== null ? DriverEvaluationScoreLabel::fromScore($overallScore)->value : null,
            'confidence' => AssignmentEvaluationConfidence::HIGH,
            'scoring_version' => '1.0.0',
            'category_scores' => $categoryScores ?? [
                'safety' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.35, 'normalized_weight' => 0.35, 'event_contributions' => [], 'skipped_overlaps' => []],
                'driving_behavior' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.25, 'normalized_weight' => 0.25, 'event_contributions' => [], 'skipped_overlaps' => []],
                'route_compliance' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.15, 'normalized_weight' => 0.15, 'event_contributions' => [], 'skipped_overlaps' => []],
                'timeliness' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.15, 'normalized_weight' => 0.15, 'event_contributions' => [], 'skipped_overlaps' => []],
                'workflow_discipline' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.10, 'normalized_weight' => 0.10, 'event_contributions' => [], 'skipped_overlaps' => []],
            ],
            'scoring_metadata' => [
                'scoring_code' => 'eval.score',
                'scoring_version' => '1.0.0',
                'configured_weights' => config('driver_evaluation.scoring.categories'),
                'event_impacts' => config('driver_evaluation.scoring.event_impacts'),
            ],
            'started_at' => now()->subHour(),
            'completed_at' => $finalized ? now() : null,
            'finalized_at' => $finalized ? now() : null,
        ]);
    }

    /**
     * @return array{0: User, 1: Driver}
     */
    private function makeLinkedDriver(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $driver = Driver::factory()->create(['user_id' => $user->id, 'email' => $email]);

        return [$user, $driver];
    }

    private function tokenForRole(string $roleName): string
    {
        $role = match ($roleName) {
            'admin' => $this->adminRole,
            'dispatcher' => $this->dispatcherRole,
            'client' => $this->clientRole,
            default => $this->driverRole,
        };
        $user = User::factory()->create([
            'email' => "perf-{$roleName}-".uniqid('', true).'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);

        return $this->loginToken($user->email);
    }

    private function loginToken(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk();

        return (string) $response->json('data.access_token');
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
