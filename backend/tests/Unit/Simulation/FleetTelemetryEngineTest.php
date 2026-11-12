<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Dynamics\DynamicsTickResult;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\FleetTelemetryEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FleetTelemetryEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_builds_snapshot_from_dynamics_and_route_progress(): void
    {
        Log::spy();

        $engine = new FleetTelemetryEngine();
        $dynamics = new DynamicsTickResult(
            vehicleId: 'v1',
            previousSpeedKmh: 0,
            targetSpeedKmh: 80,
            currentSpeedKmh: 12.96,
            accelerationMs2: 0.06,
            distanceMeters: 108,
            tickSeconds: 60,
        );
        $progress = new RouteProgressState(
            routeId: 'r1',
            shipmentId: 's1',
            vehicleId: 'v1',
            polylineIndex: 5,
            segmentIndex: 2,
            distanceTravelledM: 1500,
            remainingDistanceM: 98000,
            completionPercentage: 1.5,
            currentLatitude: 48.13,
            currentLongitude: 11.58,
            heading: 45.0,
            completed: false,
            lastTick: now()->toIso8601String(),
            totalDistanceM: 99500,
        );

        $snapshot = $engine->build(
            vehicleId: 'v1',
            shipmentId: 's1',
            vehicleState: 'moving',
            dynamics: $dynamics,
            routeProgress: $progress,
            tickSeconds: 60.0,
        );

        $this->assertSame('v1', $snapshot->vehicleId);
        $this->assertSame(12.96, $snapshot->currentSpeedKmh);
        $this->assertSame(80.0, $snapshot->targetSpeedKmh);
        $this->assertSame('moving', $snapshot->vehicleState);
        $this->assertSame(1.5, $snapshot->completionPercentage);
        $this->assertSame(98000.0, $snapshot->remainingDistanceMeters);
        $this->assertNull($snapshot->fuelLevel);
        $this->assertNull($snapshot->engineHealth);

        $cached = $engine->load('v1');
        $this->assertNotNull($cached);
        $this->assertSame(12.96, $cached->currentSpeedKmh);
        $this->assertTrue(Cache::has(FleetTelemetryEngine::CACHE_PREFIX . 'v1'));
    }

    public function test_accumulates_odometer_and_driving_time_across_ticks(): void
    {
        Log::spy();
        $engine = new FleetTelemetryEngine();

        $d1 = new DynamicsTickResult('v2', 0, 80, 13, 0.06, 100, 60);
        $engine->build('v2', 's2', 'moving', $d1, null, [
            'latitude' => 48.0,
            'longitude' => 11.0,
            'heading' => 10.0,
            'from_fallback' => true,
        ], 60.0);

        $d2 = new DynamicsTickResult('v2', 13, 80, 26, 0.06, 200, 60);
        $second = $engine->build('v2', 's2', 'moving', $d2, null, [
            'latitude' => 48.01,
            'longitude' => 11.01,
            'heading' => 12.0,
            'from_fallback' => true,
        ], 60.0);

        $this->assertSame(120.0, $second->drivingTimeSeconds);
        $this->assertSame(300.0, $second->odometerMeters);
        $this->assertTrue($second->averageSpeedKmh > 0);
    }
}
