<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Journey\JourneyPlanStore;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Journey\RestStopPlanner;
use App\Simulation\Journey\RestStopStatus;
use App\Simulation\Lifecycle\GeofenceProximityDetector;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 5 — rest-stop planning & journey rest engine.
 */
class RestStopPlannerAndJourneyRestEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_short_route_has_no_rest_stops(): void
    {
        $planner = new RestStopPlanner(80_000, 900, 20_000, 80);
        $plan = $planner->plan('s-short', 50_000);

        $this->assertSame([], $plan->restStops);
        $this->assertGreaterThan(0, $plan->estimatedTravelSeconds);
    }

    public function test_exactly_threshold_without_tail_skips_rest(): void
    {
        $planner = new RestStopPlanner(80_000, 900, 20_000, 80);
        $plan = $planner->plan('s-eq', 80_000);
        $this->assertSame([], $plan->restStops);

        // Remaining after first rest must exceed min_remaining_after_rest_m
        $plan2 = $planner->plan('s-eq2', 100_001);
        $this->assertCount(1, $plan2->restStops);
        $this->assertSame(80_000.0, $plan2->restStops[0]->routeOffsetM);
    }

    public function test_long_route_multiple_rest_stops(): void
    {
        $planner = new RestStopPlanner(80_000, 900, 20_000, 80);
        $plan = $planner->plan('s-long', 300_000);

        $this->assertCount(3, $plan->restStops);
        $this->assertSame([80_000.0, 160_000.0, 240_000.0], array_map(
            fn ($s) => $s->routeOffsetM,
            $plan->restStops
        ));
    }

    public function test_ensure_plan_is_idempotent(): void
    {
        $engine = $this->engine(80_000, 60);
        $a = $engine->ensurePlan('s1', 250_000);
        $b = $engine->ensurePlan('s1', 250_000);

        $this->assertSame($a->plannedAt, $b->plannedAt);
        $this->assertCount(count($a->restStops), $b->restStops);
        $this->assertSame(1, substr_count(json_encode((new JourneyPlanStore())->get('s1')?->toArray()), 's1-rest-1'));
    }

    public function test_clamp_prevents_skipping_rest_and_begin_rest(): void
    {
        $engine = $this->engine(80_000, 120);
        $engine->ensurePlan('s2', 250_000);

        $cap = $engine->maxTravelledCapM('s2', 70_000);
        $this->assertSame(80_000.0, $cap);

        $plan = $engine->syncWithProgress('s2', 80_000, 70_000, now()->toIso8601String(), 250_000);
        $this->assertTrue($plan->isResting);
        $this->assertSame(RestStopStatus::Active, $plan->restStops[0]->status);

        // Cap while resting holds position
        $this->assertSame(80_000.0, $engine->maxTravelledCapM('s2', 80_000));
    }

    public function test_rest_timer_completes_and_resets_driving(): void
    {
        $engine = $this->engine(80_000, 60);
        $engine->ensurePlan('s3', 250_000);
        $t0 = now()->toIso8601String();
        $engine->syncWithProgress('s3', 80_000, 79_000, $t0, 250_000);
        $this->assertTrue($engine->isResting('s3'));

        $still = $engine->syncWithProgress('s3', 80_000, 80_000, now()->addSeconds(30)->toIso8601String(), 250_000);
        $this->assertTrue($still->isResting);

        $done = $engine->syncWithProgress('s3', 80_000, 80_000, now()->addSeconds(90)->toIso8601String(), 250_000);
        $this->assertFalse($done->isResting);
        $this->assertSame(RestStopStatus::Completed, $done->restStops[0]->status);
        $this->assertSame(0.0, $done->drivingSinceRestM);

        // Second rest still available
        $this->assertSame(160_000.0, $done->nextIncompleteStop()?->routeOffsetM);
    }

    public function test_waypoint_jump_over_rest_is_detected(): void
    {
        $detector = new GeofenceProximityDetector();
        $this->assertTrue($detector->waypointReached(90_000, 80_000, 150, 70_000));
        $this->assertTrue($detector->waypointReached(80_050, 80_000, 150, 79_000));
        $this->assertFalse($detector->waypointReached(50_000, 80_000, 150, 40_000));
    }

    public function test_repeated_begin_rest_is_idempotent(): void
    {
        $engine = $this->engine(80_000, 300);
        $engine->ensurePlan('s4', 200_000);
        $t0 = now()->toIso8601String();
        $engine->syncWithProgress('s4', 80_000, 79_000, $t0, 200_000);
        $engine->syncWithProgress('s4', 80_000, 80_000, now()->addSeconds(10)->toIso8601String(), 200_000);
        $plan = $engine->getPlan('s4');
        $this->assertTrue($plan?->isResting);
        $this->assertSame($t0, $plan?->restStartedAt);
    }

    private function engine(float $maxDrive, int $restSeconds): JourneyRestEngine
    {
        return new JourneyRestEngine(
            store: new JourneyPlanStore(),
            planner: new RestStopPlanner($maxDrive, $restSeconds, 20_000, 80),
            geofence: new GeofenceProximityDetector(),
            routeProgressEngine: null,
            approachDistanceM: 1500,
            geofenceRadiusM: 150,
        );
    }
}
