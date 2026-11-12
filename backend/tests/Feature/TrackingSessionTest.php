<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tracking_session_for_shipment_order(): void
    {
        $order = ShipmentOrder::factory()->create();

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('tracking_sessions', [
            'id' => $session->id,
            'shipment_order_id' => $order->id,
        ]);

        $this->assertTrue($order->trackingSession->is($session));
    }

    public function test_default_status_is_inactive(): void
    {
        $order = ShipmentOrder::factory()->create();

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
        ]);

        $this->assertEquals(TrackingSession::STATUS_INACTIVE, $session->status);
        $this->assertEquals(TrackingSession::STATUS_INACTIVE, $session->fresh()->status);
    }

    public function test_session_belongs_to_assignment_driver_and_vehicle(): void
    {
        $order = ShipmentOrder::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $assignment = Assignment::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'assignment_id' => $assignment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->assertTrue($session->assignment->is($assignment));
        $this->assertTrue($session->driver->is($driver));
        $this->assertTrue($session->vehicle->is($vehicle));
        $this->assertTrue($assignment->trackingSession->is($session));
        $this->assertTrue($vehicle->trackingSessions->contains($session));
    }

    public function test_can_update_status_to_active(): void
    {
        $order = ShipmentOrder::factory()->create();

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
        ]);

        $session->update([
            'status' => TrackingSession::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->assertEquals(TrackingSession::STATUS_ACTIVE, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->started_at);
    }

    public function test_simulation_override_fields_persist_correctly(): void
    {
        $order = ShipmentOrder::factory()->create();

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'forced_speed_kmh' => 120.00,
            'forced_deceleration' => true,
            'forced_route_deviation' => true,
        ]);

        $session->refresh();

        $this->assertEquals('120.00', (string) $session->forced_speed_kmh);
        $this->assertTrue($session->forced_deceleration);
        $this->assertTrue($session->forced_route_deviation);
    }
}
