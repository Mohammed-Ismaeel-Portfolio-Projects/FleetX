<?php

namespace Tests\Feature\Driver;

use App\Actions\Driver\EvaluateDriverMotionSafetyAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\DriverNotification;
use App\Models\Issue;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Simulation\Dynamics\VehicleConstraints;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Support\DriverExecutionPhase;
use App\Support\DriverMotionSafetyPolicy;
use App\Support\DriverNotificationCode;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D15-C — Server-authoritative harsh braking and sudden stop from live Driver speed.
 */
class DriverMotionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $cancelledStatus;

    private Status $completedStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->completedStatus = $this->assignmentStatus('Completed');
        config([
            'driver_safety.motion.enabled' => true,
            'driver_safety.motion.min_samples' => 3,
            'driver_safety.motion.min_duration_seconds' => 10,
            'driver_safety.motion.cooldown_seconds' => 300,
            'driver_safety.motion.max_accuracy_meters' => 50,
            'driver_safety.motion.max_sample_age_seconds' => 30,
            'driver_safety.speed.enabled' => true,
            'driver_safety.speed.threshold_kmh' => 110,
            'driver_safety.speed.min_samples' => 3,
            'driver_safety.speed.min_duration_seconds' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_below_threshold_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-below@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.2, 5));

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_exactly_threshold_confirms_harsh_braking(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-exact@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);
        $statusId = $driver->status_id;

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5));

        $this->assertSame(1, $this->harshCount($driver, $assignment));
        $row = DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->where('code', DriverNotificationCode::HARSH_BRAKING)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('warning', $row->severity);
        $this->assertSame('active_trip', $row->action_kind);
        $this->assertSame('Sharp slowdown detected', $row->title);
        $this->assertSame((string) $assignment->id, $row->action_target);
        $this->assertStringNotContainsString('52.52', $row->body);
        $this->assertStringNotContainsString('crashed', strtolower($row->body));
        $this->assertStringNotContainsString('law', strtolower($row->body));
        $this->assertSame($statusId, $driver->fresh()->status_id);
        $this->assertTrue((bool) $driver->fresh()->is_active);
    }

    public function test_above_threshold_is_a_candidate_and_confirms_with_three_samples(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-above@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(90, 2.0, 5));

        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_one_noisy_drop_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-noise@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [80.0, 40.0, 80.0]);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_two_samples_only_do_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-two@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [80.0, 40.0]);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_duration_below_minimum_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-shortdur@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 4), 4);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_duration_meeting_minimum_alerts(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-mindur@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5), 5);

        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_van_threshold_is_used(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-van@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_VAN);
        $token = $this->loginToken($user->email);
        $t0 = Carbon::parse('2026-08-17 12:00:00');

        Carbon::setTestNow($t0);
        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(90, 2.0, 5));
        $this->assertSame(0, $this->harshCount($driver, $assignment));

        Carbon::setTestNow($t0->copy()->addSeconds(30));
        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(90, 2.5, 5), 5, 8, 3);
        Carbon::setTestNow();
        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_light_truck_threshold_is_used(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-light@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_LIGHT_TRUCK);
        $token = $this->loginToken($user->email);
        $t0 = Carbon::parse('2026-08-17 12:00:00');

        Carbon::setTestNow($t0);
        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(90, 1.6, 5));
        $this->assertSame(0, $this->harshCount($driver, $assignment));

        Carbon::setTestNow($t0->copy()->addSeconds(30));
        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(90, 2.0, 5), 5, 8, 3);
        Carbon::setTestNow();
        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_heavy_truck_threshold_is_used(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-heavy@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);
        $t0 = Carbon::parse('2026-08-17 12:00:00');

        Carbon::setTestNow($t0);
        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.2, 5));
        $this->assertSame(0, $this->harshCount($driver, $assignment));

        Carbon::setTestNow($t0->copy()->addSeconds(30));
        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5), 5, 8, 3);
        Carbon::setTestNow();
        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_simulation_max_braking_is_not_used_as_live_threshold(): void
    {
        $simHeavy = VehicleConstraints::fromVehicleProfile(VehicleProfileFactory::defaultTruck());
        $this->assertEqualsWithDelta(0.15, $simHeavy->maxBrakingMs2, 1e-9);
        $this->assertEqualsWithDelta(1.5, DriverMotionSafetyPolicy::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_HEAVY_TRUCK), 1e-9);
        $this->assertNotEquals($simHeavy->maxBrakingMs2, DriverMotionSafetyPolicy::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_HEAVY_TRUCK));

        [$user, $driver] = $this->makeLinkedDriver('d15-simthr@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 0.15, 5));

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_null_speed_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-null@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $end = now();
        foreach ([0, 1, 2] as $i) {
            $this->postTracking(
                $token,
                $assignment,
                $i,
                null,
                $end->copy()->subSeconds(10 - ($i * 5))->toIso8601String()
            )->assertOk();
        }

        $this->assertSame(0, $this->harshCount($driver, $assignment));
        $this->assertSame(0, $this->stopCount($driver, $assignment));
    }

    public function test_poor_accuracy_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-acc@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5), 5, 80);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_stale_sample_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-stale@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $end = now()->subSeconds(120);
        $speeds = $this->speedsForDeceleration(80, 1.5, 5);
        foreach ($speeds as $i => $speed) {
            $this->postTracking(
                $token,
                $assignment,
                $i,
                $speed,
                $end->copy()->subSeconds(10 - ($i * 5))->toIso8601String()
            )->assertOk();
        }

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_invalid_delta_t_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-dt@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 1), 1);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_implausible_motion_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-impl@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [150.0, 5.0, 0.0]);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
        $this->assertSame(0, $this->stopCount($driver, $assignment));
    }

    public function test_sudden_stop_moving_to_stopped_is_a_candidate(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-stop-cand@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [80.0, 40.0, 3.0]);

        $this->assertSame(1, $this->stopCount($driver, $assignment));
    }

    public function test_single_zero_speed_sample_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-zero@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postTracking($token, $assignment, 0, 0.0, now()->toIso8601String())->assertOk();

        $this->assertSame(0, $this->stopCount($driver, $assignment));
        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_gps_teleport_does_not_create_sudden_stop(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-tele@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);
        $end = now();

        $this->postTrackingAt($token, $assignment, 52.52, 13.405, 80.0, $end->copy()->subSeconds(10)->toIso8601String());
        $this->postTrackingAt($token, $assignment, 52.5204, 13.405, 40.0, $end->copy()->subSeconds(5)->toIso8601String());
        $this->postTrackingAt($token, $assignment, 53.52, 13.405, 3.0, $end->toIso8601String());

        $this->assertSame(0, $this->stopCount($driver, $assignment));
        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_insufficient_sudden_stop_confirmation_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-stop-two@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [80.0, 3.0]);

        $this->assertSame(0, $this->stopCount($driver, $assignment));
    }

    public function test_normal_slowdown_is_not_a_sudden_stop(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-slow@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [50.0, 40.0, 30.0]);

        $this->assertSame(0, $this->stopCount($driver, $assignment));
        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_valid_sudden_stop_creates_one_alert_without_issue(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-stop@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [35.0, 20.0, 4.0]);

        $this->assertSame(1, $this->stopCount($driver, $assignment));
        $this->assertSame(0, $this->harshCount($driver, $assignment));
        $this->assertSame(0, Issue::query()->count());
        $row = DriverNotification::query()
            ->where('code', DriverNotificationCode::SUDDEN_STOP)
            ->first();
        $this->assertSame('warning', $row->severity);
        $this->assertSame('active_trip', $row->action_kind);
        $this->assertSame('Rapid stop detected', $row->title);
        $this->assertStringNotContainsString('accident', strtolower($row->body));
        $this->assertStringNotContainsString('52.52', $row->body);
    }

    public function test_repeat_evaluate_of_same_tracking_log_does_not_duplicate(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-repeat@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5));
        $this->assertSame(1, $this->harshCount($driver, $assignment));

        $logId = (int) TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->orderByDesc('id')
            ->value('id');

        app(EvaluateDriverMotionSafetyAction::class)->execute((int) $assignment->id, $logId);
        app(EvaluateDriverMotionSafetyAction::class)->execute((int) $assignment->id, $logId);

        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_continuous_event_during_cooldown_does_not_duplicate(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-cool@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, [100.0, 73.0, 46.0]);
        $this->assertSame(1, $this->harshCount($driver, $assignment));

        $this->postSpeedSeries($token, $assignment, [19.0], 5, 8, 3);
        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_no_recovery_blocks_second_episode_after_cooldown(): void
    {
        config(['driver_safety.motion.cooldown_seconds' => 1]);
        [$user, $driver] = $this->makeLinkedDriver('d15-norec@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $t0 = Carbon::parse('2026-08-17 12:00:00');
        Carbon::setTestNow($t0);
        $this->postSpeedSeries($token, $assignment, [100.0, 73.0, 46.0]);
        $this->assertSame(1, $this->harshCount($driver, $assignment));

        Carbon::setTestNow($t0->copy()->addSeconds(6));
        $this->postSpeedSeries($token, $assignment, [19.0], 5, 8, 3);
        Carbon::setTestNow();

        $this->assertSame(1, $this->harshCount($driver, $assignment));
    }

    public function test_recovery_allows_later_episode(): void
    {
        config(['driver_safety.motion.cooldown_seconds' => 1]);
        [$user, $driver] = $this->makeLinkedDriver('d15-rec@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $t0 = Carbon::parse('2026-08-17 12:00:00');
        Carbon::setTestNow($t0);
        $this->postSpeedSeries($token, $assignment, [100.0, 73.0, 46.0]);
        $this->assertSame(1, $this->harshCount($driver, $assignment));

        Carbon::setTestNow($t0->copy()->addSeconds(6));
        $this->postSpeedSeries($token, $assignment, [46.0], 5, 8, 3);

        Carbon::setTestNow($t0->copy()->addSeconds(20));
        $this->postSpeedSeries($token, $assignment, [100.0, 73.0, 46.0], 5, 8, 4);
        Carbon::setTestNow();

        $this->assertSame(2, $this->harshCount($driver, $assignment));
    }

    public function test_assignment_row_locking_is_used_during_emit(): void
    {
        $src = file_get_contents(app_path('Actions/Driver/EvaluateDriverMotionSafetyAction.php'));
        $this->assertIsString($src);
        $this->assertStringContainsString('lockForUpdate', $src);
        $this->assertStringContainsString('DriverNotificationWriter::forAssignment', $src);
    }

    public function test_completed_assignment_does_not_create_event(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-done@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->completedStatus, false, null, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 3,
                'accuracy' => 8,
            ])
            ->assertStatus(409);

        $this->assertSame(0, $this->stopCount($driver, $assignment));
        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_cancelled_assignment_does_not_create_event(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-can@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->cancelledStatus, false, null, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 80,
                'accuracy' => 8,
            ])
            ->assertStatus(409);

        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_simulator_tracking_log_without_received_at_cannot_confirm(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-sim@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);
        $end = now();

        TrackingLog::query()->create([
            'shipment_id' => $assignment->shipment_id,
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 80,
            'accuracy' => 8,
            'recorded_at' => $end->copy()->subSeconds(10),
            'received_at' => null,
        ]);
        TrackingLog::query()->create([
            'shipment_id' => $assignment->shipment_id,
            'latitude' => 52.5204,
            'longitude' => 13.405,
            'speed' => 53,
            'accuracy' => 8,
            'recorded_at' => $end->copy()->subSeconds(5),
            'received_at' => null,
        ]);

        $this->postTracking($token, $assignment, 2, 26.0, $end->toIso8601String())->assertOk();

        $this->assertSame(0, $this->harshCount($driver, $assignment));

        $simId = (int) TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->whereNull('received_at')
            ->orderByDesc('id')
            ->value('id');
        app(EvaluateDriverMotionSafetyAction::class)->execute((int) $assignment->id, $simId);
        $this->assertSame(0, $this->harshCount($driver, $assignment));
    }

    public function test_client_event_flags_are_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-spoof@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        foreach (['violation' => true, 'safety_event' => true, 'event_type' => 'harsh_braking', 'freight_class' => 'van'] as $field => $value) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                    'latitude' => 52.52,
                    'longitude' => 13.405,
                    'speed' => 40,
                    'accuracy' => 8,
                    $field => $value,
                ])
                ->assertStatus(422);
        }

        $this->assertSame(0, $this->harshCount($driver, $assignment));
        $this->assertSame(0, $this->stopCount($driver, $assignment));
    }

    public function test_push_failure_does_not_fail_telemetry(): void
    {
        config(['driver_push.enabled' => true]);
        [$user, $driver] = $this->makeLinkedDriver('d15-push@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d15pushfail0001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        Http::fake(['exp.host/*' => Http::response('nope', 500)]);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5));

        $this->assertSame(1, $this->harshCount($driver, $assignment));
        $this->assertDatabaseHas('tracking_logs', ['shipment_id' => $assignment->shipment_id]);
        $this->assertTrue((bool) $assignment->fresh()->is_active);
    }

    public function test_push_disabled_still_writes_inbox_and_does_not_mutate_availability(): void
    {
        config(['driver_push.enabled' => false]);
        [$user, $driver] = $this->makeLinkedDriver('d15e-pushoff@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);
        $statusId = $driver->status_id;
        $assignmentStatusId = $assignment->status_id;

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5));

        $this->assertSame(1, $this->harshCount($driver, $assignment));
        $this->assertSame($statusId, $driver->fresh()->status_id);
        $this->assertTrue((bool) $driver->fresh()->is_active);
        $this->assertSame($assignmentStatusId, $assignment->fresh()->status_id);
        $this->assertTrue((bool) $assignment->fresh()->is_active);
    }

    public function test_d14_speed_safety_remains_independent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-d14@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        foreach ([0, 1, 2] as $i) {
            $this->postTracking(
                $token,
                $assignment,
                $i,
                130.0,
                now()->subSeconds(12 - ($i * 6))->toIso8601String()
            )->assertOk();
        }

        $this->assertSame(1, DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->where('code', DriverNotificationCode::SPEED_EXCESS)
            ->count());
        $this->assertSame(0, $this->harshCount($driver, $assignment));
        $this->assertSame(0, $this->stopCount($driver, $assignment));
    }

    public function test_feature_disabled_does_not_alert(): void
    {
        config(['driver_safety.motion.enabled' => false]);
        [$user, $driver] = $this->makeLinkedDriver('d15-off@fleetx.test');
        $assignment = $this->makeInOperation($driver, Vehicle::ROUTING_CLASS_HEAVY_TRUCK);
        $token = $this->loginToken($user->email);

        $this->postSpeedSeries($token, $assignment, $this->speedsForDeceleration(80, 1.5, 5));

        $this->assertSame(0, $this->harshCount($driver, $assignment));
        $this->assertSame(0, $this->stopCount($driver, $assignment));
    }

    /**
     * @return list<float>
     */
    private function speedsForDeceleration(float $startKmh, float $ms2, float $intervalSeconds, int $samples = 3): array
    {
        $dvKmh = $ms2 * $intervalSeconds * 3.6;
        $speeds = [];
        for ($i = 0; $i < $samples; $i++) {
            $speeds[] = round($startKmh - ($dvKmh * $i), 2);
        }

        return $speeds;
    }

    /**
     * @param  list<float>  $speeds
     */
    private function postSpeedSeries(
        string $token,
        Assignment $assignment,
        array $speeds,
        int $intervalSeconds = 5,
        float $accuracy = 8,
        int $indexOffset = 0
    ): void {
        $end = now();
        $n = count($speeds);
        foreach ($speeds as $i => $speed) {
            $this->postTracking(
                $token,
                $assignment,
                $indexOffset + $i,
                $speed,
                $end->copy()->subSeconds(($n - 1 - $i) * $intervalSeconds)->toIso8601String(),
                $accuracy
            )->assertOk();
        }
    }

    private function postTracking(
        string $token,
        Assignment $assignment,
        int $index,
        ?float $speed,
        string $recordedAt,
        float $accuracy = 8
    ) {
        $payload = [
            'latitude' => 52.52 + ($index * 0.0004),
            'longitude' => 13.405,
            'accuracy' => $accuracy,
            'recorded_at' => $recordedAt,
        ];
        if ($speed !== null) {
            $payload['speed'] = $speed;
        }

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', $payload);
    }

    private function postTrackingAt(
        string $token,
        Assignment $assignment,
        float $lat,
        float $lng,
        float $speed,
        string $recordedAt
    ): void {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => 8,
                'speed' => $speed,
                'recorded_at' => $recordedAt,
            ])
            ->assertOk();
    }

    private function harshCount(Driver $driver, Assignment $assignment): int
    {
        return DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->where('assignment_id', $assignment->id)
            ->where('code', DriverNotificationCode::HARSH_BRAKING)
            ->count();
    }

    private function stopCount(Driver $driver, Assignment $assignment): int
    {
        return DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->where('assignment_id', $assignment->id)
            ->where('code', DriverNotificationCode::SUDDEN_STOP)
            ->count();
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
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

        return [$user, Driver::factory()->create(['user_id' => $user->id])];
    }

    private function makeInOperation(Driver $driver, string $routingClass): Assignment
    {
        return $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::PICKUP_PENDING,
            $routingClass
        );
    }

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $phase,
        string $routingClass
    ): Assignment {
        $shipment = Shipment::factory()->create();
        $vehicle = Vehicle::factory()->create(['routing_class' => $routingClass]);
        $trailer = Trailer::factory()->create();
        $dispatcher = User::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $status->id,
            'is_active' => $isActive,
            'execution_phase' => $phase,
        ]);
    }

    private function loginToken(string $email): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
