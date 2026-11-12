<?php

declare(strict_types=1);

namespace Tests\Feature\DriverEvaluation;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationEventCategory;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEventSeverity;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Enums\DriverEvaluationScoreLabel;
use App\Enums\DriverPerformanceMaturity;
use App\Enums\DriverPerformanceTrend;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverAssignmentTransitionMap;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DRIVER-EVAL-08A — shared overall + current assignment evaluation read contracts.
 */
class DriverEvaluationSurfaceReadApiTest extends TestCase
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

    public function test_batch_summary_returns_compact_projections_without_n_plus_one_scoring(): void
    {
        $driverA = Driver::factory()->create();
        $driverB = Driver::factory()->create();
        $driverC = Driver::factory()->create(); // no projection

        $this->seedProjection($driverA, 88.0);
        $this->seedProjection($driverB, 72.5);

        $beforeA = DriverScore::query()->where('driver_id', $driverA->id)->first()?->aggregated_at?->toIso8601String();
        $scoreCount = DriverScore::query()->count();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('dispatcher'))
            ->getJson("/api/v1/drivers/performance/summary?ids={$driverA->id},{$driverB->id},{$driverC->id}")
            ->assertOk();

        $rows = collect($response->json('data.data'));
        $this->assertCount(3, $rows);

        $map = $rows->keyBy('driver_id');
        $this->assertSame(88.0, (float) $map[$driverA->id]['overall_score']);
        $this->assertSame(88.0, (float) $map[$driverA->id]['score']);
        $this->assertTrue($map[$driverA->id]['score_available']);
        $this->assertSame(72.5, (float) $map[$driverB->id]['overall_score']);
        $this->assertNull($map[$driverC->id]['overall_score']);
        $this->assertFalse($map[$driverC->id]['score_available']);
        $this->assertSame('unavailable', $map[$driverC->id]['maturity']);

        $this->assertSame($beforeA, DriverScore::query()->where('driver_id', $driverA->id)->first()?->aggregated_at?->toIso8601String());
        $this->assertSame($scoreCount, DriverScore::query()->count());
    }

    public function test_batch_summary_rbac_and_validation(): void
    {
        $driver = Driver::factory()->create();
        $this->seedProjection($driver, 80.0);

        $this->getJson("/api/v1/drivers/performance/summary?ids={$driver->id}")->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('client'))
            ->getJson("/api/v1/drivers/performance/summary?ids={$driver->id}")
            ->assertForbidden();

        [$user] = $this->makeLinkedDriver('batch-driver@fleetx.test');
        $this->withHeader('Authorization', 'Bearer '.$this->loginToken($user->email))
            ->getJson("/api/v1/drivers/performance/summary?ids={$driver->id}")
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson('/api/v1/drivers/performance/summary')
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/drivers/performance/summary?ids={$driver->id}")
            ->assertOk()
            ->assertJsonPath('data.data.0.driver_id', $driver->id);
    }

    public function test_current_evaluation_not_started_and_evaluating_null_score(): void
    {
        $driver = Driver::factory()->create();
        $assignment = $this->makeAssignment(['driver_id' => $driver->id, 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertOk()
            ->assertJsonPath('data.evaluation_available', false)
            ->assertJsonPath('data.ui_state', 'not_started')
            ->assertJsonPath('data.overall_score', null)
            ->assertJsonPath('data.score_available', false);

        $evaluation = AssignmentEvaluation::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $driver->id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::PROCESSING,
            'revision' => 1,
            'overall_score' => null,
            'started_at' => now(),
            'finalized_at' => null,
        ]);

        $this->createEvent($evaluation, DriverEvaluationEventCode::SPEEDING);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('dispatcher'))
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertOk()
            ->assertJsonPath('data.evaluation_available', true)
            ->assertJsonPath('data.evaluation_id', $evaluation->id)
            ->assertJsonPath('data.ui_state', 'evaluating')
            ->assertJsonPath('data.is_mutable', true)
            ->assertJsonPath('data.overall_score', null)
            ->assertJsonPath('data.score_available', false)
            ->assertJsonPath('data.event_count', 1);

        $this->assertNull($response->json('data.overall_score'));
        $this->assertNotSame(0, $response->json('data.overall_score'));
        $this->assertSame('speeding', $response->json('data.recent_events.0.event_code'));
    }

    public function test_current_evaluation_finalized_exposes_persisted_score_only(): void
    {
        $driver = Driver::factory()->create();
        $assignment = $this->makeAssignment(['driver_id' => $driver->id]);

        AssignmentEvaluation::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $driver->id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'revision' => 1,
            'overall_score' => 91.25,
            'score_label' => DriverEvaluationScoreLabel::fromScore(91.25)->value,
            'confidence' => AssignmentEvaluationConfidence::HIGH,
            'scoring_version' => '1.0.0',
            'category_scores' => ['safety' => ['evaluable' => true, 'score' => 100.0]],
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'finalized_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertOk()
            ->assertJsonPath('data.ui_state', 'completed')
            ->assertJsonPath('data.is_finalized', true)
            ->assertJsonPath('data.overall_score', 91.25)
            ->assertJsonPath('data.score_available', true)
            ->assertJsonPath('data.scoring_version', '1.0.0');
    }

    public function test_mutable_preferred_over_older_finalized_revision(): void
    {
        $driver = Driver::factory()->create();
        $assignment = $this->makeAssignment(['driver_id' => $driver->id, 'is_active' => true]);

        AssignmentEvaluation::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $driver->id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'revision' => 1,
            'overall_score' => 50.0,
            'score_label' => DriverEvaluationScoreLabel::fromScore(50.0)->value,
            'confidence' => AssignmentEvaluationConfidence::MEDIUM,
            'scoring_version' => '1.0.0',
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'finalized_at' => now()->subDay(),
        ]);

        $mutable = AssignmentEvaluation::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $driver->id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::PENDING,
            'revision' => 2,
            'overall_score' => null,
            'started_at' => now(),
            'finalized_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('dispatcher'))
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertOk()
            ->assertJsonPath('data.evaluation_id', $mutable->id)
            ->assertJsonPath('data.overall_score', null)
            ->assertJsonPath('data.ui_state', 'evaluating');
    }

    public function test_driver_self_current_evaluation_own_only(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('cur-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('cur-b@fleetx.test');

        $own = $this->makeAssignment(['driver_id' => $driverA->id, 'is_active' => true]);
        $other = $this->makeAssignment(['driver_id' => $driverB->id, 'is_active' => true]);

        AssignmentEvaluation::query()->create([
            'assignment_id' => $own->id,
            'shipment_id' => $own->shipment_id,
            'driver_id' => $driverA->id,
            'vehicle_id' => $own->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::PROCESSING,
            'revision' => 1,
            'started_at' => now(),
        ]);

        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/driver/assignments/{$own->id}/evaluation/current")
            ->assertOk()
            ->assertJsonPath('data.driver_id', $driverA->id)
            ->assertJsonPath('data.ui_state', 'evaluating')
            ->assertJsonPath('data.overall_score', null);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/driver/assignments/{$other->id}/evaluation/current")
            ->assertNotFound();
    }

    public function test_current_evaluation_rbac_client_denied_admin_allowed_without_assignment_crud(): void
    {
        $assignment = $this->makeAssignment(['is_active' => true]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('client'))
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertForbidden();

        // Admin cannot use assignment show (dispatcher-only), but can read current evaluation.
        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenForRole('admin'))
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertOk();
    }

    public function test_get_does_not_create_evaluation_or_mutate_domains(): void
    {
        $driver = Driver::factory()->create();
        $assignment = $this->makeAssignment(['driver_id' => $driver->id, 'is_active' => true]);
        $this->seedProjection($driver, 77.0);

        $evalCount = AssignmentEvaluation::query()->count();
        $trackingBefore = TrackingLog::query()->count();
        $assignmentBefore = DB::table('assignments')->where('id', $assignment->id)->first();
        $scoreBefore = DriverScore::query()->where('driver_id', $driver->id)->first()?->toArray();

        $token = $this->tokenForRole('admin');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/assignments/{$assignment->id}/evaluation/current")
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/drivers/performance/summary?ids={$driver->id}")
            ->assertOk();

        $this->assertSame($evalCount, AssignmentEvaluation::query()->count());
        $this->assertSame($trackingBefore, TrackingLog::query()->count());
        $this->assertEquals((array) $assignmentBefore, (array) DB::table('assignments')->where('id', $assignment->id)->first());
        $this->assertEquals($scoreBefore, DriverScore::query()->where('driver_id', $driver->id)->first()?->toArray());
    }

    private function seedProjection(Driver $driver, float $score): DriverScore
    {
        AssignmentEvaluation::query()->create([
            'assignment_id' => $this->makeAssignment(['driver_id' => $driver->id, 'is_active' => false, 'ended_at' => now()])->id,
            'shipment_id' => Shipment::factory()->create()->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'revision' => 1,
            'overall_score' => $score,
            'score_label' => DriverEvaluationScoreLabel::fromScore($score)->value,
            'confidence' => AssignmentEvaluationConfidence::HIGH,
            'scoring_version' => '1.0.0',
            'category_scores' => [
                'safety' => ['evaluable' => true, 'score' => 100.0, 'configured_weight' => 0.35, 'normalized_weight' => 0.35, 'event_contributions' => [], 'skipped_overlaps' => []],
            ],
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'finalized_at' => now(),
        ]);

        // Direct projection write — GET must not re-aggregate.
        return DriverScore::query()->updateOrCreate(
            ['driver_id' => $driver->id],
            [
                'score' => $score,
                'total_deliveries' => 1,
                'late_deliveries' => 0,
                'evaluated_assignments_count' => 1,
                'incomplete_assignments_count' => 0,
                'score_label' => DriverEvaluationScoreLabel::fromScore($score)->value,
                'confidence' => AssignmentEvaluationConfidence::HIGH,
                'maturity' => DriverPerformanceMaturity::PROVISIONAL,
                'trend' => DriverPerformanceTrend::STABLE,
                'recent_score' => $score,
                'previous_score' => null,
                'trend_delta' => null,
                'category_scores' => null,
                'aggregation_version' => '1.0.0',
                'aggregation_metadata' => ['scoring_versions_seen' => ['1.0.0']],
                'last_evaluated_at' => now(),
                'aggregated_at' => now(),
            ]
        );
    }

    private function createEvent(AssignmentEvaluation $evaluation, DriverEvaluationEventCode $code): AssignmentEvaluationEvent
    {
        return AssignmentEvaluationEvent::query()->create([
            'assignment_evaluation_id' => $evaluation->id,
            'assignment_id' => $evaluation->assignment_id,
            'shipment_id' => $evaluation->shipment_id,
            'driver_id' => $evaluation->driver_id,
            'vehicle_id' => $evaluation->vehicle_id,
            'event_code' => $code,
            'category' => DriverEvaluationEventCategory::SAFETY,
            'severity' => DriverEvaluationEventSeverity::MEDIUM,
            'occurred_at' => now(),
            'window_start_at' => now()->subMinutes(1),
            'window_end_at' => now(),
            'evidence_source' => DriverEvaluationEvidenceSource::LIVE,
            'detector_code' => 'test.detector',
            'detector_version' => '1.0.0',
            'idempotency_key' => '08a-'.$evaluation->id.'-'.$code->value.'-'.uniqid('', true),
            'evidence_tracking_log_ids' => [1],
            'evidence_metadata' => ['note' => 'test'],
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
            'email' => '08a-'.$roleName.'-'.uniqid('', true).'@fleetx.test',
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
            'ended_at' => null,
            'is_active' => true,
        ], $overrides));
    }
}
