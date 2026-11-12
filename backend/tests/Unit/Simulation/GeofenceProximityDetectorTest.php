<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Lifecycle\GeofenceProximityDetector;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 4 — geofence containment / crossing helpers.
 */
class GeofenceProximityDetectorTest extends TestCase
{
    private GeofenceProximityDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new GeofenceProximityDetector();
    }

    public function test_is_inside_radius(): void
    {
        $this->assertTrue($this->detector->isInside(150.0, 150.0));
        $this->assertTrue($this->detector->isInside(80.0, 150.0));
        $this->assertFalse($this->detector->isInside(151.0, 150.0));
    }

    public function test_crossed_into_from_outside(): void
    {
        $this->assertTrue($this->detector->crossedInto(200.0, 100.0, 150.0));
        $this->assertFalse($this->detector->crossedInto(100.0, 80.0, 150.0));
        $this->assertFalse($this->detector->crossedInto(null, 100.0, 150.0));
    }

    public function test_pickup_reached_by_containment(): void
    {
        $this->assertTrue(
            $this->detector->pickupReached(50.0, 50000.0, 150.0, null, false)
        );
    }

    public function test_pickup_reached_when_tick_jumps_past_origin_zone(): void
    {
        // Mid-route fast-forward: previous near origin, current already far past 150m.
        $this->assertTrue(
            $this->detector->pickupReached(5000.0, 50000.0, 150.0, 100.0, false)
        );
        // First sample already mid-route (null previous) while still approaching — preserve milestone.
        $this->assertTrue(
            $this->detector->pickupReached(5000.0, 50000.0, 150.0, null, false)
        );
    }

    public function test_pickup_not_re_triggered_after_past_stages(): void
    {
        $this->assertFalse(
            $this->detector->pickupReached(5000.0, 50000.0, 150.0, 4000.0, true)
        );
    }

    public function test_delivery_reached_by_remaining_distance(): void
    {
        $this->assertTrue(
            $this->detector->deliveryReached(80.0, false, 150.0, 500.0)
        );
    }

    public function test_delivery_reached_on_route_completed(): void
    {
        $this->assertTrue(
            $this->detector->deliveryReached(500.0, true, 150.0, 500.0)
        );
    }

    public function test_delivery_crossing_jump_over_zone(): void
    {
        // Remaining went from outside zone to completed (0) in one tick.
        $this->assertTrue(
            $this->detector->deliveryReached(0.0, false, 150.0, 400.0)
        );
    }
}
