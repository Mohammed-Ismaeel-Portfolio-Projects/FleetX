<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ShipmentLifecycleEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_en_route_delivery_arrives_when_near_destination_and_slow(): void
    {
        Log::spy();
        $engine = new ShipmentLifecycleEngine(150, 8, 180, 180);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX . 's1', [
            'shipment_id' => 's1',
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'En route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => now()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
        ], 3600);

        $progress = new RouteProgressState(
            routeId: 'r1',
            shipmentId: 's1',
            vehicleId: 'v1',
            polylineIndex: 99,
            segmentIndex: 10,
            distanceTravelledM: 99000,
            remainingDistanceM: 80,
            completionPercentage: 99.9,
            currentLatitude: 48.1,
            currentLongitude: 11.5,
            heading: 90,
            completed: false,
            lastTick: now()->toIso8601String(),
            totalDistanceM: 99080,
        );

        $telemetry = $this->telemetry(4.0);

        $snap = $engine->evaluate('s1', 'moving', $telemetry, $progress);

        $this->assertSame(ShipmentLifecycleStage::ArrivedDelivery->value, $snap->currentStage);
        $this->assertTrue($snap->stageChangedThisTick);
        $this->assertNotNull($snap->arrivalTime);
    }

    public function test_unload_timer_then_delivered_then_completed(): void
    {
        Log::spy();
        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60);

        $start = now()->subMinutes(2)->toIso8601String();
        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX . 's2', [
            'shipment_id' => 's2',
            'current_stage' => ShipmentLifecycleStage::Unloading->value,
            'previous_stage' => ShipmentLifecycleStage::ArrivedDelivery->value,
            'transition_reason' => 'Unloading started',
            'arrival_time' => $start,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => null,
            'delivery_time' => $start,
            'completion_time' => null,
            'timestamp' => $start,
            'stage_changed_this_tick' => false,
        ], 3600);

        $progress = new RouteProgressState(
            'r2', 's2', 'v2', 100, 10, 100000, 0, 100,
            48.2, 11.6, 0, true, now()->toIso8601String(), 100000
        );

        $delivered = $engine->evaluate('s2', 'idle', $this->telemetry(0), $progress, now()->toIso8601String());
        $this->assertSame(ShipmentLifecycleStage::Delivered->value, $delivered->currentStage);

        $completed = $engine->evaluate('s2', 'idle', $this->telemetry(0), $progress, now()->toIso8601String());
        $this->assertSame(ShipmentLifecycleStage::Completed->value, $completed->currentStage);
        $this->assertNotNull($completed->completionTime);
    }

    public function test_loading_requires_idle_timer(): void
    {
        Log::spy();
        $engine = new ShipmentLifecycleEngine(150, 8, 120, 120);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX . 's3', [
            'shipment_id' => 's3',
            'current_stage' => ShipmentLifecycleStage::Loading->value,
            'previous_stage' => ShipmentLifecycleStage::ArrivedPickup->value,
            'transition_reason' => 'Loading started',
            'arrival_time' => now()->toIso8601String(),
            'loading_started' => now()->toIso8601String(),
            'loading_completed' => null,
            'departure_time' => null,
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
        ], 3600);

        $progress = new RouteProgressState(
            'r3', 's3', 'v3', 0, 0, 20, 50000, 0.04,
            48.0, 11.0, 0, false, now()->toIso8601String(), 50020
        );

        $stillLoading = $engine->evaluate(
            's3',
            'idle',
            $this->telemetry(0),
            $progress,
            now()->addSeconds(30)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Loading->value, $stillLoading->currentStage);

        $loaded = $engine->evaluate(
            's3',
            'idle',
            $this->telemetry(0),
            $progress,
            now()->addSeconds(150)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Loaded->value, $loaded->currentStage);
    }

    private function telemetry(float $speed): TelemetrySnapshot
    {
        return new TelemetrySnapshot(
            vehicleId: 'v',
            shipmentId: 's',
            timestamp: now()->toIso8601String(),
            latitude: 48.1,
            longitude: 11.5,
            heading: 90,
            currentSpeedKmh: $speed,
            targetSpeedKmh: $speed,
            acceleration: 0,
            vehicleState: $speed > 1 ? 'moving' : 'idle',
            distanceTravelledMeters: 1000,
            remainingDistanceMeters: 100,
            completionPercentage: 50,
            currentSegment: 1,
            polylineIndex: 10,
            drivingTimeSeconds: 60,
            idleTimeSeconds: 0,
            engineRuntimeSeconds: 60,
            averageSpeedKmh: $speed,
            odometerMeters: 1000,
            gpsAccuracy: 5,
            signalQuality: 'good',
        );
    }
}
