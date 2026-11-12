<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D6 — Driver telemetry ingest + trip map.
 */
class DriverTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $cancelledStatus;

    private Status $completedStatus;

    private Status $readyStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->completedStatus = $this->assignmentStatus('Completed');
        $this->readyStatus = $this->assignmentStatus('Ready');
    }

    public function test_own_active_assignment_telemetry_passes(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-tel-own@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'accuracy' => 8,
                'speed' => 42.5,
                'heading' => 90,
                'recorded_at' => now()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.position.source', 'server_last_known')
            ->assertJsonPath('data.deduped', false);

        $this->assertDatabaseHas('tracking_logs', [
            'shipment_id' => $assignment->shipment_id,
        ]);
        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $assignment->id,
            'driver_id' => $driver->id,
        ]);
    }

    public function test_own_active_assignment_trip_map_passes(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-map-own@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/trip-map')
            ->assertOk()
            ->assertJsonPath('data.assignment.id', $assignment->id)
            ->assertJsonPath('data.tracking_status', 'unavailable')
            ->assertJsonStructure(['data' => ['shipment', 'route_available', 'freshness', 'execution']]);
    }

    public function test_other_driver_telemetry_denied(): void
    {
        [$userA] = $this->makeLinkedDriver('d6-tel-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d6-tel-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertNotFound();
    }

    public function test_other_driver_trip_map_denied(): void
    {
        [$userA] = $this->makeLinkedDriver('d6-map-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d6-map-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignmentB->id.'/trip-map')
            ->assertNotFound();
    }

    public function test_client_role_denied(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd6-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_denied(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/tracking', [
            'latitude' => 52.52,
            'longitude' => 13.405,
        ])->assertUnauthorized();
    }

    public function test_unlinked_driver_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'd6-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertForbidden();
    }

    public function test_completed_assignment_telemetry_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-complete@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->completedStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertStatus(409);
    }

    public function test_cancelled_assignment_telemetry_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->cancelledStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertStatus(409);
    }

    public function test_reassigned_assignment_denied(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d6-reas-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d6-reas-b@fleetx.test');
        $assignment = $this->makeInOperation($driverA);
        $assignment->update(['driver_id' => $driverB->id]);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertNotFound();
    }

    public function test_ready_assignment_telemetry_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-ready@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->readyStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertStatus(409);
    }

    public function test_driver_cannot_use_ops_tracking_endpoint(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-ops@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/tracking-logs', [
                'shipment_id' => $assignment->shipment_id,
                'latitude' => 52.52,
                'longitude' => 13.405,
            ])
            ->assertForbidden();
    }

    public function test_driver_cannot_use_client_tracking_endpoint(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-cli-trk@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/client/shipments/'.$assignment->shipment_id.'/tracking')
            ->assertForbidden();
    }

    public function test_foreign_ids_cannot_override_binding(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-probe@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'driver_id' => 999,
                'shipment_id' => '00000000-0000-0000-0000-000000000099',
            ])
            ->assertStatus(422);
    }

    public function test_invalid_latitude_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-bad-lat@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 91,
                'longitude' => 13.405,
            ])
            ->assertStatus(422);
    }

    public function test_null_island_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-zero@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 0,
                'longitude' => 0,
            ])
            ->assertStatus(422);
    }

    public function test_future_timestamp_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-future@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'recorded_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertStatus(422);
    }

    public function test_invalid_speed_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-speed@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => -5,
            ])
            ->assertStatus(422);
    }

    public function test_repeated_telemetry_dedupes_safely(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d6-dedupe@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);
        $payload = [
            'latitude' => 52.52001,
            'longitude' => 13.40501,
            'recorded_at' => now()->toIso8601String(),
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', $payload)
            ->assertOk()
            ->assertJsonPath('data.deduped', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', $payload)
            ->assertOk()
            ->assertJsonPath('data.deduped', true);

        $this->assertEquals(1, \App\Models\TrackingLog::query()->where('shipment_id', $assignment->shipment_id)->count());
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
