<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\ShipmentOrder\ApproveShipmentOrderAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\TrackingState;
use App\Models\User;
use App\Models\Vehicle;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveTrackingShipmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Role $dispatcherRole;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
    }

    public function test_full_shipment_request_to_active_tracking_lifecycle(): void
    {
        $company = Company::factory()->create(['company_name' => 'Acme Corp']);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'email' => 'dispatcher@fleetx.io',
        ]);

        // 1. Client creates Shipment Request (ShipmentOrder) in SUBMITTED / UNDER_REVIEW status
        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'pickup_city' => 'Berlin',
            'delivery_city' => 'Paris',
            'status_id' => Status::where('name', ShipmentOrderStatus::UNDER_REVIEW)->where('type', ShipmentOrderStatus::TYPE)->first()->id,
        ]);

        // 2. Dispatcher approves request
        $approveAction = app(ApproveShipmentOrderAction::class);
        $approveResult = $approveAction->execute($dispatcher, $order->id);
        $this->assertTrue($approveResult->success);
        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);

        // 3. Dispatcher converts request to Shipment
        $convertAction = app(ConvertShipmentOrderToShipmentAction::class);
        $convertResult = $convertAction->execute($dispatcher, $order->id);
        $this->assertTrue($convertResult->success);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $shipmentId = $order->converted_shipment_id;
        $this->assertNotNull($shipmentId);

        $shipment = Shipment::findOrFail($shipmentId);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->status->name);

        // 4. Before assignment, Active Tracking query excludes the unassigned shipment
        $responseBefore = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/tracking/live-fleet');

        $responseBefore->assertOk();
        $vehiclesBefore = $responseBefore->json('data.vehicles');
        $shipmentIdsBefore = array_column($vehiclesBefore, 'shipment_id');
        $this->assertNotContains($shipmentId, $shipmentIdsBefore);

        // 5. Verify PlannedRouteCache was bootstrapped for the new shipment
        $cache = app(PlannedRouteCache::class)->get($shipmentId);
        $this->assertNotNull($cache);
        $this->assertGreaterThanOrEqual(2, count($cache['coordinates'] ?? []));

        // 6. Create Driver, Vehicle, and Assignment for the Shipment
        $driver = Driver::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $vehicle = Vehicle::factory()->create(['operational_code' => 'TRK-101']);
        $assignmentStatus = Status::firstOrCreate(['name' => 'Active', 'type' => 'assignment']);

        $assignmentAction = app(CreateAssignmentAction::class);
        $assignmentResult = $assignmentAction->execute([
            'shipment_id' => $shipmentId,
            'assignment_code' => 'ASG-TEST-001',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'is_active' => true,
        ]);

        $this->assertTrue($assignmentResult->success);
        $assignment = $assignmentResult->data;

        // Create tracking state position for vehicle
        TrackingState::create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $shipmentId,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 65.5,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
            'last_update_at' => now(),
        ]);

        // 7. Active Tracking query (/tracking/live-fleet) NOW INCLUDES the active shipment and vehicle
        $responseAfter = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/tracking/live-fleet');

        $responseAfter->assertOk();
        $vehiclesAfter = $responseAfter->json('data.vehicles');
        $matching = array_filter($vehiclesAfter, fn ($v) => $v['shipment_id'] === $shipmentId);
        $this->assertNotEmpty($matching);

        $first = array_values($matching)[0];
        $this->assertSame((string) $order->id, $first['shipment']['shipment_order_id']);
        $this->assertSame((string) $vehicle->id, $first['vehicle_id']);
        $this->assertSame((string) $driver->id, $first['driver_id']);
        $this->assertSame('John Doe', $first['driver']['full_name']);
        $this->assertSame(52.5200, $first['telemetry']['latitude']);
        $this->assertSame(13.4050, $first['telemetry']['longitude']);
    }
}
