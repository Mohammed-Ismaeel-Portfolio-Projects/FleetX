<?php

namespace Tests\Feature\Vehicle;

use App\Actions\Assignment\ChangeAssignmentStatusAction;
use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\Vehicle\PairDriverToVehicleAction;
use App\Actions\Vehicle\UnpairDriverFromVehicleAction;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;

use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Database\Seeders\StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDriverPairingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $dispatcher;
    private User $client;
    private Status $driverAvailable;
    private Status $driverAssigned;
    private Status $vehicleAvailable;
    private Status $vehicleAssigned;
    private Status $asgAssignedStatus;
    private Status $asgCompletedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(StatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $clientRole = Role::where('name', 'client')->firstOrFail();

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->dispatcher = User::factory()->create(['role_id' => $dispatcherRole->id]);
        $this->client = User::factory()->create(['role_id' => $clientRole->id]);

        $this->driverAvailable = Status::where('type', 'driver')->where('name', 'Available')->first()
            ?? Status::firstOrCreate(['name' => 'Available', 'type' => 'driver']);
        $this->driverAssigned = Status::where('type', 'driver')->where('name', 'Assigned')->first()
            ?? Status::firstOrCreate(['name' => 'Assigned', 'type' => 'driver']);
        $this->vehicleAvailable = Status::whereIn('type', ['truck', 'vehicle'])->where('name', 'Available')->first()
            ?? Status::firstOrCreate(['name' => 'Available', 'type' => 'vehicle']);
        $this->vehicleAssigned = Status::whereIn('type', ['truck', 'vehicle'])->where('name', 'Assigned')->first()
            ?? Status::firstOrCreate(['name' => 'Assigned', 'type' => 'vehicle']);
        $this->asgAssignedStatus = Status::where('type', 'assignment')->where('name', 'Assigned')->first()
            ?? Status::firstOrCreate(['name' => 'Assigned', 'type' => 'assignment']);
        $this->asgCompletedStatus = Status::where('type', 'assignment')->where('name', 'Completed')->first()
            ?? Status::firstOrCreate(['name' => 'Completed', 'type' => 'assignment']);
    }

    public function test_admin_can_pair_driver_with_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['driver_id' => null]);
        $driver = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/pair-driver", [
                'driver_id' => $driver->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver.id', $driver->id);

        $this->assertEquals($driver->id, $vehicle->fresh()->driver_id);
    }

    public function test_non_admin_cannot_pair_driver(): void
    {
        $vehicle = Vehicle::factory()->create(['driver_id' => null]);
        $driver = Driver::factory()->create();

        $response = $this->actingAs($this->dispatcher, 'api')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/pair-driver", [
                'driver_id' => $driver->id,
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_unpair_driver(): void
    {
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/unpair-driver");

        $response->assertOk()
            ->assertJsonPath('data.driver', null);

        $this->assertNull($vehicle->fresh()->driver_id);
    }

    public function test_admin_can_move_driver_from_vehicle_a_to_vehicle_b_atomically(): void
    {
        $driver = Driver::factory()->create();
        $vehicleA = Vehicle::factory()->create(['driver_id' => $driver->id]);
        $vehicleB = Vehicle::factory()->create(['driver_id' => null]);

        $action = app(PairDriverToVehicleAction::class);
        $result = $action->execute($vehicleB, $driver->id);

        $this->assertTrue($result->success);
        $this->assertNull($vehicleA->fresh()->driver_id);
        $this->assertEquals($driver->id, $vehicleB->fresh()->driver_id);
    }

    public function test_historical_assignments_remain_unchanged_after_pairing_changes(): void
    {
        $driver1 = Driver::factory()->create();
        $driver2 = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver1->id]);
        $shipment = Shipment::factory()->create();

        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver1->id,
            'is_active' => false,
            'status_id' => $this->asgCompletedStatus->id,
        ]);

        // Move vehicle pairing to driver2
        app(PairDriverToVehicleAction::class)->execute($vehicle, $driver2->id);

        $this->assertEquals($driver1->id, $assignment->fresh()->driver_id);
        $this->assertEquals($driver2->id, $vehicle->fresh()->driver_id);
    }

    public function test_dispatcher_selecting_vehicle_resolves_paired_driver_automatically(): void
    {
        $driver = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $vehicle = Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'status_id' => $this->vehicleAvailable->id,
        ]);
        $shipment = Shipment::factory()->create();
        $trailer = Trailer::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $result = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-PAIR-101',
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgAssignedStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals($driver->id, $result->data->driver_id);
        $this->assertEquals($vehicle->id, $result->data->vehicle_id);
    }

    public function test_dispatcher_cannot_override_paired_driver_with_conflicting_driver(): void
    {
        $driverPaired = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $driverOther = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driverPaired->id]);
        $shipment = Shipment::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $result = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-CONFLICT-102',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverOther->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgAssignedStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals(422, $result->statusCode);
        $this->assertStringContainsString('match', $result->message);
    }

    public function test_vehicle_without_paired_driver_cannot_be_assigned(): void
    {
        $vehicle = Vehicle::factory()->create(['driver_id' => null]);
        $shipment = Shipment::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $result = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-NOPAIR-103',
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgAssignedStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals(422, $result->statusCode);
        $this->assertStringContainsString('no assigned driver', $result->message);
    }

    public function test_unavailable_vehicle_or_driver_cannot_be_assigned(): void
    {
        $driver = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id]);
        $shipment1 = Shipment::factory()->create();
        $shipment2 = Shipment::factory()->create();

        // Active assignment on vehicle & driver
        Assignment::factory()->create([
            'shipment_id' => $shipment1->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'is_active' => true,
            'status_id' => $this->asgAssignedStatus->id,
        ]);

        $action = app(CreateAssignmentAction::class);
        $result = $action->execute([
            'shipment_id' => $shipment2->id,
            'assignment_code' => 'ASG-BUSY-104',
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgAssignedStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals(422, $result->statusCode);
    }

    public function test_successful_assignment_sets_vehicle_and_driver_to_assigned_and_completion_restores_available(): void
    {
        $driver = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $vehicle = Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'status_id' => $this->vehicleAvailable->id,
        ]);
        $shipment = Shipment::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $createResult = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-STATUS-105',
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgAssignedStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($createResult->success);
        $this->assertEquals($this->driverAssigned->id, $driver->fresh()->status_id);
        $this->assertEquals($this->vehicleAssigned->id, $vehicle->fresh()->status_id);

        // Complete assignment
        $changeAction = app(ChangeAssignmentStatusAction::class);
        $changeResult = $changeAction->execute($createResult->data, $this->asgCompletedStatus->id, $this->dispatcher->id);

        $this->assertTrue($changeResult->success);
        $this->assertEquals('Available', $driver->fresh()->status?->name);
        $this->assertEquals('Available', $vehicle->fresh()->status?->name);
    }

    public function test_driver_app_active_assignment_endpoint_returns_the_assigned_vehicle_and_driver(): void
    {
        $userDriver = User::factory()->create();
        $driverRole = Role::where('name', 'driver')->firstOrFail();
        $userDriver->update(['role_id' => $driverRole->id]);

        $driver = Driver::factory()->create(['user_id' => $userDriver->id, 'status_id' => $this->driverAvailable->id]);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'status_id' => $this->vehicleAvailable->id]);
        $shipment = Shipment::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $asgResult = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-DRIVERAPP-106',
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgAssignedStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($asgResult->success);

        $response = $this->actingAs($userDriver, 'api')
            ->getJson('/api/v1/driver/work/active');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $asgResult->data->id)
            ->assertJsonPath('data.vehicle.id', $vehicle->id);
    }

    public function test_admin_cannot_change_pairing_or_unpair_when_vehicle_or_driver_has_active_assignment(): void
    {
        $driver1 = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $driver2 = Driver::factory()->create(['status_id' => $this->driverAvailable->id]);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver1->id, 'status_id' => $this->vehicleAvailable->id]);
        $shipment = Shipment::factory()->create();

        // Create active assignment
        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver1->id,
            'is_active' => true,
            'status_id' => $this->asgAssignedStatus->id,
        ]);

        // Attempt to unpair while active
        $responseUnpair = $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/unpair-driver");

        $responseUnpair->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot unpair driver while vehicle has an active assignment.');

        // Attempt to pair driver2 while vehicle active
        $responsePair = $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/pair-driver", [
                'driver_id' => $driver2->id,
            ]);

        $responsePair->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot modify driver pairing while vehicle has an active assignment.');
    }
}
