<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\DriverNotification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationCode;
use App\Support\DriverSpeedSafetyPolicy;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D14 — Server-authoritative speed safety alerts.
 */
class DriverSpeedSafetyTest extends TestCase
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
            'driver_safety.speed.enabled' => true,
            'driver_safety.speed.threshold_kmh' => 110,
            'driver_safety.speed.min_samples' => 3,
            'driver_safety.speed.min_duration_seconds' => 10,
            'driver_safety.speed.cooldown_seconds' => 300,
            'driver_safety.speed.recovery_kmh' => 95,
            'driver_safety.speed.max_accuracy_meters' => 50,
            'driver_safety.speed.max_sample_age_seconds' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_below_threshold_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-below@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 42.5);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_single_sample_above_threshold_does_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-single@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postTracking($token, $assignment, 0, 130, now()->toIso8601String());

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_sustained_excess_creates_one_notification(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-sustained@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);
        $statusId = $driver->status_id;

        $this->postSpeedBurst($token, $assignment, 130);

        $this->assertSame(1, $this->alertCount($driver, $assignment));
        $row = DriverNotification::query()->where('driver_id', $driver->id)->first();
        $this->assertSame(DriverNotificationCode::SPEED_EXCESS, $row->code);
        $this->assertSame('warning', $row->severity);
        $this->assertSame('active_trip', $row->action_kind);
        $this->assertSame((string) $assignment->id, $row->action_target);
        $this->assertStringNotContainsString('law', strtolower($row->body));
        $this->assertSame($statusId, $driver->fresh()->status_id);
        $this->assertTrue((bool) $driver->fresh()->is_active);
    }

    public function test_repeat_evaluate_does_not_duplicate_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-race@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 130);
        $this->assertSame(1, $this->alertCount($driver, $assignment));

        $logId = (int) \App\Models\TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->orderByDesc('id')
            ->value('id');

        app(\App\Actions\Driver\EvaluateDriverSpeedSafetyAction::class)
            ->execute((int) $assignment->id, $logId);
        app(\App\Actions\Driver\EvaluateDriverSpeedSafetyAction::class)
            ->execute((int) $assignment->id, $logId);

        $this->assertSame(1, $this->alertCount($driver, $assignment));
    }

    public function test_insufficient_samples_do_not_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-two@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postTracking($token, $assignment, 0, 130, now()->subSeconds(6)->toIso8601String());
        $this->postTracking($token, $assignment, 1, 130, now()->toIso8601String());

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_poor_accuracy_is_ignored(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-acc@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 130, 80);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_stale_telemetry_is_ignored(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-stale@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postTracking($token, $assignment, 0, 130, now()->subSeconds(120)->toIso8601String());
        $this->postTracking($token, $assignment, 1, 130, now()->subSeconds(114)->toIso8601String());
        $this->postTracking($token, $assignment, 2, 130, now()->subSeconds(108)->toIso8601String());

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_duplicate_telemetry_does_not_confirm(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-dup@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);
        $at = now()->toIso8601String();

        $this->postTracking($token, $assignment, 0, 130, $at);
        $this->postTracking($token, $assignment, 0, 130, $at);
        $this->postTracking($token, $assignment, 0, 130, $at);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_impossible_jump_is_ignored(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-jump@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'accuracy' => 8,
                'speed' => 130,
                'recorded_at' => now()->subSeconds(12)->toIso8601String(),
            ])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 53.52,
                'longitude' => 13.405,
                'accuracy' => 8,
                'speed' => 130,
                'recorded_at' => now()->subSeconds(6)->toIso8601String(),
            ])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 54.52,
                'longitude' => 13.405,
                'accuracy' => 8,
                'speed' => 130,
                'recorded_at' => now()->toIso8601String(),
            ])
            ->assertOk();

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_cooldown_suppresses_repeat_while_still_speeding(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-cool@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 130, 8, 0);
        $this->assertSame(1, $this->alertCount($driver, $assignment));

        $this->postSpeedBurst($token, $assignment, 135, 8, 3);
        $this->assertSame(1, $this->alertCount($driver, $assignment));
    }

    public function test_recovery_then_new_excess_after_cooldown_creates_second_alert(): void
    {
        config(['driver_safety.speed.cooldown_seconds' => 1]);
        [$user, $driver] = $this->makeLinkedDriver('d14-recover@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $t0 = Carbon::parse('2026-08-17 12:00:00');
        Carbon::setTestNow($t0);
        $this->postSpeedBurst($token, $assignment, 130, 8, 0);
        $this->assertSame(1, $this->alertCount($driver, $assignment));

        Carbon::setTestNow($t0->copy()->addSeconds(2));
        $this->postTracking($token, $assignment, 3, 40, now()->toIso8601String());

        // now-12 must be after the recovery sample so the new episode is consecutive.
        Carbon::setTestNow($t0->copy()->addSeconds(20));
        $this->postSpeedBurst($token, $assignment, 140, 8, 4);
        Carbon::setTestNow();

        $this->assertSame(2, $this->alertCount($driver, $assignment));
    }

    public function test_feature_disabled_does_not_alert(): void
    {
        config(['driver_safety.speed.enabled' => false]);
        [$user, $driver] = $this->makeLinkedDriver('d14-off@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 160);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_configuration_threshold_is_respected(): void
    {
        config(['driver_safety.speed.threshold_kmh' => 180]);
        [$user, $driver] = $this->makeLinkedDriver('d14-thr@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 130);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
        $this->assertSame(180.0, DriverSpeedSafetyPolicy::thresholdKmh());
    }

    public function test_missing_speed_does_not_infer_alert(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-null@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        foreach ([0, 1, 2] as $i) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                    'latitude' => 52.52 + ($i * 0.0004),
                    'longitude' => 13.405,
                    'accuracy' => 8,
                    'recorded_at' => now()->subSeconds(12 - ($i * 6))->toIso8601String(),
                ])
                ->assertOk();
        }

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_completed_trip_ignored(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-done@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->completedStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 160,
                'accuracy' => 8,
            ])
            ->assertStatus(409);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_cancelled_trip_ignored(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-can@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->cancelledStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 160,
                'accuracy' => 8,
            ])
            ->assertStatus(409);
    }

    public function test_client_violation_flag_is_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-spoof@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 40,
                'accuracy' => 8,
                'violation' => true,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $this->alertCount($driver, $assignment));
    }

    public function test_push_failure_does_not_fail_telemetry(): void
    {
        config(['driver_push.enabled' => true]);
        [$user, $driver] = $this->makeLinkedDriver('d14-push@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d14pushfail0001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        Http::fake(['exp.host/*' => Http::response('nope', 500)]);
        $token = $this->loginToken($user->email);

        $this->postSpeedBurst($token, $assignment, 150);

        $this->assertSame(1, $this->alertCount($driver, $assignment));
        $this->assertDatabaseHas('tracking_logs', ['shipment_id' => $assignment->shipment_id]);
    }

    public function test_summary_includes_speed_alert_count(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-sum@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);
        $this->postSpeedBurst($token, $assignment, 130);
        $assignment->update([
            'status_id' => $this->completedStatus->id,
            'is_active' => false,
            'ended_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertOk()
            ->assertJsonPath('data.safety.speed_alert_count', 1)
            ->assertJsonPath('data.safety.harsh_braking_count', 0)
            ->assertJsonPath('data.safety.sudden_stop_count', 0)
            ->assertJsonPath('data.safety.motion_alert_count', 0);
    }

    private function postSpeedBurst(
        string $token,
        Assignment $assignment,
        float $speed,
        float $accuracy = 8,
        int $indexOffset = 0
    ): void {
        foreach ([0, 1, 2] as $i) {
            $n = $indexOffset + $i;
            $this->postTracking(
                $token,
                $assignment,
                $n,
                $speed,
                now()->subSeconds(12 - ($i * 6))->toIso8601String(),
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

    private function alertCount(Driver $driver, Assignment $assignment): int
    {
        return DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->where('assignment_id', $assignment->id)
            ->where('code', DriverNotificationCode::SPEED_EXCESS)
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

    private function makeInOperation(Driver $driver): Assignment
    {
        return $this->makeAssignmentFor($driver, $this->inOperationStatus, true, DriverExecutionPhase::PICKUP_PENDING);
    }

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $phase = null
    ): Assignment {
        $shipment = Shipment::factory()->create();
        $vehicle = Vehicle::factory()->create();
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
