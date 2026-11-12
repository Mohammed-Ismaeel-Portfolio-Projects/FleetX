<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatcherTrackingSessionQueryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\ShipmentOrderStatusesSeeder::class);

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $company = Company::factory()->create();

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $company->id,
        ]);

        $this->clientUser = User::factory()->create([
            'role_id' => $clientRole->id,
            'company_id' => $company->id,
        ]);
    }

    private function createOrderWithSession(): array
    {
        $shipment = Shipment::factory()->create();
        $driver = Driver::factory()->create([
            'first_name' => 'Nabil',
            'last_name' => 'Al-Ali',
        ]);
        $vehicle = Vehicle::factory()->create([
            'plate_number' => 'FLEETX-TRK-01',
        ]);
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'is_active' => true,
        ]);

        $order = ShipmentOrder::factory()->create([
            'converted_shipment_id' => $shipment->id,
        ]);

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'assignment_id' => $assignment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
            'forced_speed_kmh' => 120.00,
        ]);

        return [$order, $session, $driver, $vehicle];
    }

    public function test_dispatcher_can_retrieve_active_tracking_session(): void
    {
        [$order, $session] = $this->createOrderWithSession();

        $response = $this->actingAs($this->dispatcherUser)
            ->getJson("/api/v1/dispatcher/tracking-sessions/shipment-order/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.shipment_order_id', $order->id);
    }

    public function test_dispatcher_receives_session_details_with_driver_and_vehicle(): void
    {
        [$order, $session, $driver, $vehicle] = $this->createOrderWithSession();

        $response = $this->actingAs($this->dispatcherUser)
            ->getJson("/api/v1/dispatcher/tracking-sessions/shipment-order/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.driver.id', (string) $driver->id)
            ->assertJsonPath('data.driver.name', 'Nabil Al-Ali')
            ->assertJsonPath('data.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.vehicle.plate_number', 'FLEETX-TRK-01')
            ->assertJsonPath('data.simulation.forced_speed_kmh', 120);
    }

    public function test_returns_404_when_shipment_has_no_session(): void
    {
        $order = ShipmentOrder::factory()->create();

        $response = $this->actingAs($this->dispatcherUser)
            ->getJson("/api/v1/dispatcher/tracking-sessions/shipment-order/{$order->id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_unauthorized_user_cannot_access_endpoint(): void
    {
        [$order] = $this->createOrderWithSession();

        $response = $this->actingAs($this->clientUser)
            ->getJson("/api/v1/dispatcher/tracking-sessions/shipment-order/{$order->id}");

        $response->assertStatus(403);
    }
}
