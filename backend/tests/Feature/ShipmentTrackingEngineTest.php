<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\TrackingLog;
use App\Models\TrackingSession;
use App\Models\Vehicle;
use App\Services\Tracking\ShipmentTrackingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingEngineTest extends TestCase
{
    use RefreshDatabase;

    private function createTrackableSessionFixture(array $sessionAttributes = []): array
    {
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'is_active' => true,
        ]);

        $routeGeometry = [
            [33.5138, 36.2765],
            [34.7324, 36.7137],
            [36.2021, 37.1343],
        ];

        $order = ShipmentOrder::factory()->create([
            'converted_shipment_id' => $shipment->id,
            'planned_route_geometry' => $routeGeometry,
            'planned_route_status' => 'AVAILABLE',
        ]);

        $session = TrackingSession::create(array_merge([
            'shipment_order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'assignment_id' => $assignment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'status' => TrackingSession::STATUS_ACTIVE,
            'started_at' => now(),
        ], $sessionAttributes));

        return [$session, $order, $shipment, $assignment];
    }

    public function test_inactive_session_does_not_generate_telemetry(): void
    {
        [$session, $order, $shipment] = $this->createTrackableSessionFixture([
            'status' => TrackingSession::STATUS_INACTIVE,
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);
        $result = $engine->tick($session);

        $this->assertNull($result);
        $this->assertDatabaseMissing('tracking_logs', [
            'shipment_id' => $shipment->id,
        ]);
    }

    public function test_active_session_creates_tracking_log(): void
    {
        [$session, $order, $shipment] = $this->createTrackableSessionFixture();

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);
        $state = $engine->tick($session);

        $this->assertNotNull($state);
        $this->assertEquals($shipment->id, $state->shipment_id);

        $this->assertDatabaseHas('tracking_logs', [
            'shipment_id' => $shipment->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);
    }

    public function test_position_advances_along_planned_route(): void
    {
        [$session, $order, $shipment] = $this->createTrackableSessionFixture();

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);

        // First tick -> point 0 [33.5138, 36.2765]
        $state1 = $engine->tick($session);
        $this->assertEquals(33.5138, (float) $state1->latitude);
        $this->assertEquals(36.2765, (float) $state1->longitude);

        // Second tick -> point 1 [34.7324, 36.7137]
        $state2 = $engine->tick($session);
        $this->assertEquals(34.7324, (float) $state2->latitude);
        $this->assertEquals(36.7137, (float) $state2->longitude);
    }

    public function test_forced_speed_override_works(): void
    {
        [$session, $order, $shipment] = $this->createTrackableSessionFixture([
            'forced_speed_kmh' => 120.00,
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);
        $state = $engine->tick($session);

        $this->assertEquals(120.00, (float) $state->speed);
        $this->assertDatabaseHas('tracking_logs', [
            'shipment_id' => $shipment->id,
            'speed' => 120.00,
        ]);
    }

    public function test_forced_route_deviation_creates_different_coordinates(): void
    {
        [$session, $order, $shipment] = $this->createTrackableSessionFixture([
            'forced_route_deviation' => true,
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);
        $state = $engine->tick($session);

        // Expected point 0 [33.5138, 36.2765] + offset 0.015 = [33.5288, 36.2915]
        $this->assertEquals(33.5288, round((float) $state->latitude, 4));
        $this->assertEquals(36.2915, round((float) $state->longitude, 4));
    }

    public function test_session_completes_at_route_end(): void
    {
        [$session, $order, $shipment] = $this->createTrackableSessionFixture();

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);

        // Tick 1 (point 0)
        $engine->tick($session);
        // Tick 2 (point 1)
        $engine->tick($session);
        // Tick 3 (point 2 - final point)
        $state = $engine->tick($session);

        $this->assertEquals(TrackingSession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->ended_at);
        $this->assertEquals(36.2021, (float) $state->latitude);
        $this->assertEquals(37.1343, (float) $state->longitude);
    }
}
