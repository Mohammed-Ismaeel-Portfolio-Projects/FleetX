<?php

declare(strict_types=1);

namespace Tests\Feature\DriverEvaluation;

use App\Actions\Driver\StartDriverAssignmentAction;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionService;
use App\Services\DriverEvaluation\DriverEvaluationLifecycleService;
use App\Support\DriverAssignmentTransitionMap;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DRIVER-EVAL-07 — operational lifecycle integration (Assignment + Tracking → Evaluation).
 */
class DriverEvaluationLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private DriverEvaluationLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->lifecycle = app(DriverEvaluationLifecycleService::class);

        config([
            'driver_evaluation.lifecycle.incremental_enabled' => true,
            'driver_evaluation.lifecycle.incremental_min_interval_seconds' => 30,
            'driver_safety.speed.enabled' => true,
            'driver_safety.speed.threshold_kmh' => 110,
            'driver_safety.speed.min_samples' => 3,
            'driver_safety.speed.min_duration_seconds' => 10,
        ]);

        Cache::flush();
    }

    public function test_assignment_in_operation_opens_mutable_evaluation_context(): void
    {
        [$user, $assignment] = $this->makeReadyAssignment();

        $result = app(StartDriverAssignmentAction::class)->execute($user, $assignment->id);
        $this->assertTrue($result->success);

        $evaluation = AssignmentEvaluation::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_mode', AssignmentEvaluationMode::LIVE->value)
            ->first();

        $this->assertNotNull($evaluation);
        $this->assertTrue($evaluation->isMutable());
        $this->assertNull($evaluation->overall_score);
        $this->assertNull($evaluation->finalized_at);
        $this->assertSame(0, DriverScore::query()->where('driver_id', $assignment->driver_id)->count());
    }

    public function test_live_telemetry_detected_via_incremental_and_events_are_idempotent(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $this->lifecycle->openForAssignment($assignment);
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 12:00:00'), [120, 125, 130], 5);

        $first = $this->lifecycle->processIncremental($assignment->fresh());
        $this->assertNotNull($first);
        $this->assertGreaterThan(0, $first->telemetrySampleCount);

        $speedingCount = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('event_code', DriverEvaluationEventCode::SPEEDING->value)
            ->count();
        $this->assertSame(1, $speedingCount);

        // Immediate second call is throttled.
        $throttled = $this->lifecycle->processIncremental($assignment->fresh());
        $this->assertNull($throttled);

        Cache::flush();
        $second = $this->lifecycle->processIncremental($assignment->fresh());
        $this->assertNotNull($second);
        $this->assertSame(0, count($second->eventsCreated));
        $this->assertSame(
            1,
            AssignmentEvaluationEvent::query()
                ->where('assignment_id', $assignment->id)
                ->where('event_code', DriverEvaluationEventCode::SPEEDING->value)
                ->count()
        );
        $this->assertNull($second->evaluation->finalized_at);
    }

    public function test_simulation_telemetry_does_not_enter_live_evaluation(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $this->lifecycle->openForAssignment($assignment);
        $this->seedSimSpeedSeries($assignment, Carbon::parse('2026-08-01 13:00:00'), [120, 125, 130], 5);
        Cache::flush();

        $result = $this->lifecycle->processIncremental($assignment->fresh());
        $this->assertNotNull($result);
        $this->assertSame(0, AssignmentEvaluationEvent::query()->where('assignment_id', $assignment->id)->count());
    }

    public function test_completion_finalizes_score_and_refreshes_driver_scores_idempotently(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $this->lifecycle->openForAssignment($assignment);
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 14:00:00'), [80, 85, 90], 5);

        $assignment->update([
            'is_active' => false,
            'ended_at' => Carbon::parse('2026-08-01 15:00:00'),
            'status_id' => Status::firstOrCreate(
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment'],
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment']
            )->id,
        ]);

        $first = $this->lifecycle->finalizeForCompletedAssignment($assignment->fresh());
        $this->assertNotNull($first);
        $this->assertTrue($first->evaluation->isFinalized());
        $this->assertContains(
            $first->evaluation->status,
            [AssignmentEvaluationStatus::COMPLETED, AssignmentEvaluationStatus::INCOMPLETE]
        );

        $score = DriverScore::query()->where('driver_id', $assignment->driver_id)->first();
        $this->assertNotNull($score);
        $evalId = $first->evaluation->id;
        $overall = $first->evaluation->overall_score;
        $revisionCount = AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count();

        $second = $this->lifecycle->finalizeForCompletedAssignment($assignment->fresh());
        $this->assertNull($second);
        $this->assertSame(1, AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count());
        $this->assertSame($revisionCount, AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count());
        $this->assertEquals(
            $overall,
            AssignmentEvaluation::query()->whereKey($evalId)->value('overall_score')
        );
        $this->assertSame(1, DriverScore::query()->where('driver_id', $assignment->driver_id)->count());
    }

    /** DRIVER-EVAL-08G FE-01: incremental after finalize must not create a second mutable revision. */
    public function test_incremental_after_finalize_does_not_create_orphan_mutable_revision(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $this->lifecycle->openForAssignment($assignment);
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 16:00:00'), [80, 85, 90], 5);

        $assignment->update([
            'is_active' => false,
            'ended_at' => Carbon::parse('2026-08-01 17:00:00'),
            'status_id' => Status::firstOrCreate(
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment'],
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment']
            )->id,
        ]);

        $final = $this->lifecycle->finalizeForCompletedAssignment($assignment->fresh());
        $this->assertNotNull($final);
        $countAfterFinalize = AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count();
        $this->assertSame(1, $countAfterFinalize);

        Cache::flush();
        // Force active so incremental gate would otherwise run (simulates stale queue job).
        $stale = $assignment->fresh();
        $stale->is_active = true;

        $incremental = $this->lifecycle->processIncremental($stale);
        $this->assertNull($incremental);
        $this->assertSame(
            1,
            AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count()
        );
        $this->assertSame(
            0,
            AssignmentEvaluation::query()
                ->where('assignment_id', $assignment->id)
                ->whereNull('finalized_at')
                ->count()
        );
    }

    public function test_insufficient_telemetry_finalizes_incomplete_without_penalty_score(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $this->lifecycle->openForAssignment($assignment);
        $assignment->update([
            'is_active' => false,
            'ended_at' => now(),
            'status_id' => Status::firstOrCreate(
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment'],
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment']
            )->id,
        ]);

        $result = $this->lifecycle->finalizeForCompletedAssignment($assignment->fresh());
        $this->assertNotNull($result);
        $this->assertSame(AssignmentEvaluationStatus::INCOMPLETE, $result->evaluation->status);
        $this->assertNull($result->evaluation->overall_score);

        $projection = DriverScore::query()->where('driver_id', $assignment->driver_id)->first();
        $this->assertNotNull($projection);
        $this->assertNull($projection->score);
    }

    public function test_reassignment_keeps_historical_driver_attribution(): void
    {
        $driverA = Driver::factory()->create();
        $driverB = Driver::factory()->create();
        $shipment = Shipment::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $a = $this->makeInOperationAssignment([
            'driver_id' => $driverA->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 12:00:00'),
            'is_active' => false,
        ]);
        $this->seedLiveSpeedSeries($a, Carbon::parse('2026-08-01 09:00:00'), [120, 125, 130], 5);

        // Reassign same shipment to driver B later.
        $b = $this->makeInOperationAssignment([
            'driver_id' => $driverB->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'assigned_at' => Carbon::parse('2026-08-01 13:00:00'),
            'ended_at' => null,
            'is_active' => true,
        ]);

        $this->lifecycle->openForAssignment($a);
        $resultA = $this->lifecycle->finalizeForCompletedAssignment($a->fresh());
        $this->assertNotNull($resultA);
        $event = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $a->id)
            ->where('event_code', DriverEvaluationEventCode::SPEEDING->value)
            ->first();
        $this->assertNotNull($event);
        $this->assertSame($driverA->id, $event->driver_id);
        $this->assertSame(DriverEvaluationEvidenceSource::LIVE, $event->evidence_source);

        Cache::flush();
        $this->lifecycle->openForAssignment($b);
        $resultB = $this->lifecycle->processIncremental($b->fresh());
        $this->assertNotNull($resultB);
        $this->assertSame(
            0,
            AssignmentEvaluationEvent::query()->where('assignment_id', $b->id)->count()
        );
    }

    public function test_ambiguous_overlapping_windows_exclude_evidence(): void
    {
        $shipment = Shipment::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $a1 = $this->makeInOperationAssignment([
            'driver_id' => $driver->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 12:00:00'),
            'is_active' => false,
        ]);
        $a2 = $this->makeInOperationAssignment([
            'driver_id' => Driver::factory()->create()->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'assigned_at' => Carbon::parse('2026-08-01 10:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 14:00:00'),
            'is_active' => false,
        ]);

        // Sample in overlap window for shipment.
        TrackingLog::query()->create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.52,
            'longitude' => 13.4,
            'speed' => 130,
            'heading' => 90,
            'accuracy' => 5,
            'recorded_at' => Carbon::parse('2026-08-01 11:00:00'),
            'received_at' => Carbon::parse('2026-08-01 11:00:01'),
        ]);

        $this->lifecycle->finalizeForCompletedAssignment($a1->fresh());
        $this->lifecycle->finalizeForCompletedAssignment($a2->fresh());

        $this->assertSame(
            0,
            AssignmentEvaluationEvent::query()
                ->whereIn('assignment_id', [$a1->id, $a2->id])
                ->count()
        );
    }

    public function test_finalize_failure_does_not_corrupt_tracking_or_assignment(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 16:00:00'), [70, 75, 80], 5);
        $logsBefore = TrackingLog::query()->count();
        $assignmentBefore = (array) DB::table('assignments')->where('id', $assignment->id)->first();
        $invoiceBefore = DB::table('monthly_invoices')->count();
        $shipmentBefore = (array) DB::table('shipments')->where('id', $assignment->shipment_id)->first();

        $this->mock(DriverEvaluationDetectionService::class, function ($mock) {
            $mock->shouldReceive('processAssignment')->andThrow(new \RuntimeException('forced evaluation failure'));
        });

        app(DriverEvaluationLifecycleService::class)->finalizeForCompletedAssignmentSafely($assignment->fresh());

        $this->assertSame($logsBefore, TrackingLog::query()->count());
        $this->assertEquals($assignmentBefore, (array) DB::table('assignments')->where('id', $assignment->id)->first());
        $this->assertEquals($shipmentBefore, (array) DB::table('shipments')->where('id', $assignment->shipment_id)->first());
        $this->assertSame($invoiceBefore, DB::table('monthly_invoices')->count());
    }

    public function test_lifecycle_does_not_create_operational_side_entities(): void
    {
        $assignment = $this->makeInOperationAssignment();
        $shipmentsBefore = DB::table('shipments')->count();
        $assignmentsBefore = DB::table('assignments')->count();
        $invoicesBefore = DB::table('monthly_invoices')->count();

        $this->lifecycle->openForAssignment($assignment);
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 17:00:00'), [100, 105, 108], 5);
        Cache::flush();
        $this->lifecycle->processIncremental($assignment->fresh());
        $assignment->update([
            'is_active' => false,
            'ended_at' => now(),
            'status_id' => Status::firstOrCreate(
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment'],
                ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment']
            )->id,
        ]);
        $this->lifecycle->finalizeForCompletedAssignment($assignment->fresh());

        $this->assertSame($shipmentsBefore, DB::table('shipments')->count());
        $this->assertSame($assignmentsBefore, DB::table('assignments')->count());
        $this->assertSame($invoicesBefore, DB::table('monthly_invoices')->count());
    }

    /**
     * @return array{0: User, 1: Assignment}
     */
    private function makeReadyAssignment(): array
    {
        $role = \App\Models\Role::where('name', 'driver')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'eval-lifecycle-'.uniqid('', true).'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $driver = Driver::factory()->create(['user_id' => $user->id]);
        $ready = Status::firstOrCreate(
            ['name' => DriverAssignmentTransitionMap::READY, 'type' => 'assignment'],
            ['name' => DriverAssignmentTransitionMap::READY, 'type' => 'assignment']
        );

        $assignment = $this->makeAssignment([
            'driver_id' => $driver->id,
            'status_id' => $ready->id,
            'is_active' => true,
            'ended_at' => null,
        ]);

        return [$user, $assignment];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInOperationAssignment(array $overrides = []): Assignment
    {
        $inOp = Status::firstOrCreate(
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment'],
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment']
        );

        return $this->makeAssignment(array_merge([
            'status_id' => $inOp->id,
            'is_active' => true,
            'ended_at' => null,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAssignment(array $overrides = []): Assignment
    {
        Status::firstOrCreate(
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment'],
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment']
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

    /**
     * @param  list<float>  $speeds
     */
    private function seedLiveSpeedSeries(Assignment $assignment, Carbon $base, array $speeds, int $intervalSeconds): void
    {
        foreach ($speeds as $i => $speed) {
            TrackingLog::query()->create([
                'shipment_id' => $assignment->shipment_id,
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => $speed,
                'heading' => 90,
                'accuracy' => 5,
                'recorded_at' => $base->copy()->addSeconds($i * $intervalSeconds),
                'received_at' => $base->copy()->addSeconds($i * $intervalSeconds)->addSecond(),
            ]);
        }
    }

    /**
     * @param  list<float>  $speeds
     */
    private function seedSimSpeedSeries(Assignment $assignment, Carbon $base, array $speeds, int $intervalSeconds): void
    {
        foreach ($speeds as $i => $speed) {
            TrackingLog::query()->create([
                'shipment_id' => $assignment->shipment_id,
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => $speed,
                'heading' => 90,
                'accuracy' => 5,
                'recorded_at' => $base->copy()->addSeconds($i * $intervalSeconds),
                'received_at' => null,
            ]);
        }
    }
}
