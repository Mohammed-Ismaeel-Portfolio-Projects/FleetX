<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D15-D — History counts consume inbox episodes (no second detector).
 */
class DriverMotionSafetySummaryTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $completedStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->completedStatus = $this->assignmentStatus('Completed');
    }

    public function test_historical_summary_counts_read_and_unread_motion_episodes(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15d-sum@fleetx.test');
        $assignment = $this->makeCompleted($driver);

        $this->writeEpisode($driver, $assignment, DriverNotificationCode::HARSH_BRAKING, 'h1');
        $read = $this->writeEpisode($driver, $assignment, DriverNotificationCode::HARSH_BRAKING, 'h2');
        $read->update(['read_at' => now()]);
        $this->writeEpisode($driver, $assignment, DriverNotificationCode::SUDDEN_STOP, 's1');
        $this->writeEpisode($driver, $assignment, DriverNotificationCode::SPEED_EXCESS, 'v1');

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertOk()
            ->assertJsonPath('data.safety.speed_alert_count', 1)
            ->assertJsonPath('data.safety.harsh_braking_count', 2)
            ->assertJsonPath('data.safety.sudden_stop_count', 1)
            ->assertJsonPath('data.safety.motion_alert_count', 3);
    }

    public function test_counts_are_assignment_and_driver_scoped(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d15d-iso-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d15d-iso-b@fleetx.test');
        $assignmentA = $this->makeCompleted($driverA);
        $otherA = $this->makeCompleted($driverA);
        $assignmentB = $this->makeCompleted($driverB);

        $this->writeEpisode($driverA, $assignmentA, DriverNotificationCode::HARSH_BRAKING, 'a1');
        $this->writeEpisode($driverA, $otherA, DriverNotificationCode::HARSH_BRAKING, 'a2');
        $this->writeEpisode($driverB, $assignmentB, DriverNotificationCode::SUDDEN_STOP, 'b1');

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignmentA->id.'/summary')
            ->assertOk()
            ->assertJsonPath('data.safety.harsh_braking_count', 1)
            ->assertJsonPath('data.safety.sudden_stop_count', 0)
            ->assertJsonPath('data.safety.motion_alert_count', 1)
            ->assertJsonPath('data.safety.speed_alert_count', 0);
    }

    public function test_simulator_tracking_log_does_not_increment_counts(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15d-sim@fleetx.test');
        $assignment = $this->makeCompleted($driver);

        \App\Models\TrackingLog::query()->create([
            'shipment_id' => $assignment->shipment_id,
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 80,
            'accuracy' => 8,
            'recorded_at' => now(),
            'received_at' => null,
        ]);

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertOk()
            ->assertJsonPath('data.safety.harsh_braking_count', 0)
            ->assertJsonPath('data.safety.sudden_stop_count', 0)
            ->assertJsonPath('data.safety.motion_alert_count', 0);
    }

    public function test_active_assignment_summary_remains_conflict(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15d-active@fleetx.test');
        $assignment = Assignment::factory()->create([
            'shipment_id' => Shipment::factory()->create()->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
        ]);
        $this->writeEpisode($driver, $assignment, DriverNotificationCode::HARSH_BRAKING, 'live');

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertStatus(409);
    }

    public function test_foreign_assignment_summary_is_not_found(): void
    {
        [$userA] = $this->makeLinkedDriver('d15d-for-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d15d-for-b@fleetx.test');
        $other = $this->makeCompleted($driverB);
        $this->writeEpisode($driverB, $other, DriverNotificationCode::SUDDEN_STOP, 'secret');

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$other->id.'/summary')
            ->assertNotFound();
    }

    public function test_summary_does_not_expose_telemetry_or_scores(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15d-shape@fleetx.test');
        $assignment = $this->makeCompleted($driver);
        $this->writeEpisode($driver, $assignment, DriverNotificationCode::HARSH_BRAKING, 'h');

        $token = $this->loginToken($user->email);
        $payload = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertOk()
            ->json('data.safety');

        $this->assertArrayHasKey('speed_alert_count', $payload);
        $this->assertArrayHasKey('harsh_braking_count', $payload);
        $this->assertArrayHasKey('sudden_stop_count', $payload);
        $this->assertArrayHasKey('motion_alert_count', $payload);
        $this->assertCount(4, $payload);
        $this->assertArrayNotHasKey('score', $payload);
        $this->assertArrayNotHasKey('acceleration', $payload);
    }

    private function writeEpisode(
        Driver $driver,
        Assignment $assignment,
        string $code,
        string $suffix
    ): DriverNotification {
        return DriverNotification::query()->create([
            'driver_id' => $driver->id,
            'code' => $code,
            'title' => $code === DriverNotificationCode::SUDDEN_STOP
                ? 'Rapid stop detected'
                : ($code === DriverNotificationCode::HARSH_BRAKING
                    ? 'Sharp slowdown detected'
                    : 'Speed safety alert'),
            'body' => 'Operational notice.',
            'severity' => 'warning',
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'action_kind' => DriverNotificationAction::KIND_ACTIVE_TRIP,
            'action_target' => (string) $assignment->id,
            'dedupe_key' => $code.':'.$assignment->id.':'.$suffix,
        ]);
    }

    private function makeCompleted(Driver $driver): Assignment
    {
        return Assignment::factory()->create([
            'shipment_id' => Shipment::factory()->create()->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->completedStatus->id,
            'is_active' => false,
            'execution_phase' => DriverExecutionPhase::POD_SUBMITTED,
            'ended_at' => now(),
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

        return [$user, Driver::factory()->create(['user_id' => $user->id])];
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
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
