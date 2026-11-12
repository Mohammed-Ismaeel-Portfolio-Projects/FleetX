<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\DeliveryProof;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D7 — Outcome read + cross-role authorization matrix.
 */
class DriverOutcomeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $deliveredShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->deliveredShipmentStatus = $this->shipmentStatus(ShipmentStatus::DELIVERED);
        $this->shipmentStatus(ShipmentStatus::COMPLETED);
        Status::firstOrCreate(['name' => 'Open', 'type' => 'issue']);
    }

    public function test_own_outcome_passes(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-out-own@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertOk()
            ->assertJsonPath('data.outcome.delivery_verified', true)
            ->assertJsonPath('data.outcome.pod_submitted', true)
            ->assertJsonPath('data.outcome.complete_eligible', true);
    }

    public function test_other_driver_outcome_denied(): void
    {
        [, $owner] = $this->makeLinkedDriver('d7-out-owner@fleetx.test');
        [$other] = $this->makeLinkedDriver('d7-out-other@fleetx.test');
        $assignment = $this->makeDeliveredVerified($owner, false);
        $token = $this->loginToken($other->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertNotFound();
    }

    public function test_unlinked_driver_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'd7-out-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        [, $driver] = $this->makeLinkedDriver('d7-out-linked@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertForbidden();
    }

    public function test_client_role_denied(): void
    {
        $clientRole = Role::where('name', 'client')->first()
            ?? Role::create(['name' => 'client', 'guard_name' => 'api']);
        $user = User::factory()->create([
            'email' => 'd7-out-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        [, $driver] = $this->makeLinkedDriver('d7-out-drv@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertForbidden();
    }

    public function test_unauthenticated_denied(): void
    {
        [, $driver] = $this->makeLinkedDriver('d7-out-unauth@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver, false);

        $this->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertUnauthorized();
    }

    public function test_driver_cannot_use_ops_assignment_status_change(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-out-ops@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver, true);
        $completed = $this->assignmentStatus('Completed');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/assignments/'.$assignment->id, [
                'status_id' => $completed->id,
            ])
            ->assertForbidden();
    }

    private function makeDeliveredVerified(Driver $driver, bool $withProof): Assignment
    {
        $shipment = Shipment::factory()->create(['status_id' => $this->deliveredShipmentStatus->id]);
        if ($withProof) {
            DeliveryProof::query()->create([
                'shipment_id' => $shipment->id,
                'image_url' => 'driver-pod/'.$shipment->id.'/photo.jpg',
                'signature' => 'driver-pod/'.$shipment->id.'/signature.json',
                'confirmed_at' => now(),
            ]);
        }
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();
        $dispatcher = User::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => $withProof
                ? DriverExecutionPhase::POD_SUBMITTED
                : DriverExecutionPhase::DELIVERY_VERIFIED,
        ]);
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
    }

    private function shipmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'shipment'],
            ['name' => $name, 'type' => 'shipment']
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
