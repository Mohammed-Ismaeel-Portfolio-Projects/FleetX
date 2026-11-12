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
use App\Support\DriverSelfServiceAvailability;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D13 — Race / stale-state protection for availability mutations.
 */
class DriverAvailabilityConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $availableStatus;

    private Status $offlineStatus;

    private Status $inOperationStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->availableStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Available', 'type' => 'driver']
        );
        $this->offlineStatus = Status::firstOrCreate(
            ['name' => 'Offline', 'type' => 'driver'],
            ['name' => 'Offline', 'type' => 'driver']
        );
        $this->inOperationStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );
        Status::firstOrCreate(
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment'],
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment']
        );
    }

    public function test_stale_client_blocked_when_assignment_becomes_active(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-stale@fleetx.test');
        $shipment = $this->makeShipment();
        $vehicleId = Vehicle::factory()->create()->id;
        $trailerId = Trailer::factory()->create()->id;
        $assignedBy = User::factory()->create()->id;
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.can_change', true);

        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicleId,
            'trailer_id' => $trailerId,
            'assigned_by' => $assignedBy,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', DriverSelfServiceAvailability::BLOCKED_DURING_TRIP);

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_sequential_conflicting_mutations_last_write_wins(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-last@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'available');

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_overlapping_unavailable_requests_are_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-overlap@fleetx.test');
        $token = $this->loginToken($user->email);

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable']);
        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable']);

        $first->assertOk()->assertJsonPath('data.availability', 'unavailable');
        $second->assertOk()->assertJsonPath('data.availability', 'unavailable');
        $this->assertSame($this->offlineStatus->id, $driver->fresh()->status_id);
        $this->assertSame(1, Driver::query()->where('status_id', $this->offlineStatus->id)->count());
    }

    public function test_lock_for_update_serializes_writer_against_held_row_lock(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-lock@fleetx.test');
        $token = $this->loginToken($user->email);

        $results = [];
        DB::transaction(function () use ($driver, $token, &$results) {
            Driver::query()->whereKey($driver->id)->lockForUpdate()->first();
            $results[] = $this->withHeader('Authorization', 'Bearer '.$token)
                ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
                ->status();
        });

        $this->assertContains($results[0], [200]);
        $this->assertSame($this->offlineStatus->id, $driver->fresh()->status_id);
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

        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status_id' => $this->availableStatus->id,
            'is_active' => true,
        ]);

        return [$user, $driver];
    }

    private function makeShipment(): Shipment
    {
        $shipmentStatus = Status::where('name', ShipmentStatus::ASSIGNED)->where('type', 'shipment')->firstOrFail();

        return Shipment::factory()->create(['status_id' => $shipmentStatus->id]);
    }

    private function makeInOperation(Driver $driver): Assignment
    {
        $shipment = $this->makeShipment();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
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
