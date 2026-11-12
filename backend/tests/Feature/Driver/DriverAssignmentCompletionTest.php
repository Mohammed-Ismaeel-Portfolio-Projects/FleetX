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
 * D7 — Assignment completion after POD.
 */
class DriverAssignmentCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $completedStatus;

    private Status $cancelledStatus;

    private Status $deliveredShipmentStatus;

    private Status $completedShipmentStatus;

    private Status $driverAvailableStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->completedStatus = $this->assignmentStatus('Completed');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->deliveredShipmentStatus = $this->shipmentStatus(ShipmentStatus::DELIVERED);
        $this->completedShipmentStatus = $this->shipmentStatus(ShipmentStatus::COMPLETED);
        $this->driverAvailableStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Available', 'type' => 'driver']
        );
        Status::firstOrCreate(['name' => 'Busy', 'type' => 'driver']);
    }

    public function test_valid_completion_after_pod(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-cmp-own@fleetx.test');
        $busy = Status::firstOrCreate(['name' => 'Busy', 'type' => 'driver']);
        $driver->update(['status_id' => $busy->id]);

        $assignment = $this->makeReadyToComplete($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.assignment.is_active', false)
            ->assertJsonPath('data.assignment.status.name', 'Completed')
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::COMPLETED);

        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertSame($this->driverAvailableStatus->id, $driver->fresh()->status_id);
    }

    public function test_completion_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-cmp-idem@fleetx.test');
        $assignment = $this->makeReadyToComplete($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.completed', true);
    }

    public function test_missing_pod_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-cmp-nopod@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_VERIFIED,
            $this->deliveredShipmentStatus,
            false
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertStatus(409);
    }

    public function test_missing_verification_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-cmp-nover@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_VERIFYING,
            $this->shipmentStatus(ShipmentStatus::IN_TRANSIT),
            false
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertStatus(409);
    }

    public function test_other_driver_denied(): void
    {
        [, $owner] = $this->makeLinkedDriver('d7-cmp-owner@fleetx.test');
        [$other] = $this->makeLinkedDriver('d7-cmp-other@fleetx.test');
        $assignment = $this->makeReadyToComplete($owner);
        $token = $this->loginToken($other->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertNotFound();
    }

    public function test_cancelled_assignment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-cmp-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->cancelledStatus,
            false,
            DriverExecutionPhase::POD_SUBMITTED,
            $this->deliveredShipmentStatus,
            true
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertStatus(409);
    }

    private function makeReadyToComplete(Driver $driver): Assignment
    {
        return $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::POD_SUBMITTED,
            $this->deliveredShipmentStatus,
            true
        );
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

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $phase,
        Status $shipmentStatus,
        bool $withProof
    ): Assignment {
        $shipment = Shipment::factory()->create(['status_id' => $shipmentStatus->id]);
        if ($withProof) {
            DeliveryProof::query()->create([
                'shipment_id' => $shipment->id,
                'image_url' => 'driver-pod/'.$shipment->id.'/photo.jpg',
                'signature' => 'driver-pod/'.$shipment->id.'/signature.png',
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
