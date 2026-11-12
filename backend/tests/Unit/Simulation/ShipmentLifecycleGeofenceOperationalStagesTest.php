<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Lifecycle\ShipmentLifecycleTransitionAdapter;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 4 — operational geofence stages (Engine-internal; adapter mocked).
 */
class ShipmentLifecycleGeofenceOperationalStagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Log::spy();
    }

    public function test_approaches_then_enters_pickup_then_loading(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->twice();

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        // Outside pickup zone while approaching
        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'p1', $this->cacheBase(
            'p1',
            ShipmentLifecycleStage::EnRoutePickup,
            lastTravelledM: 200.0,
        ), 3600);

        $outside = $engine->evaluate(
            'p1',
            'moving',
            $this->telemetry(40),
            $this->progress('p1', travelled: 250, remaining: 49750, total: 50000),
            now()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::EnRoutePickup->value, $outside->currentStage);

        // Enter origin geofence
        $arrived = $engine->evaluate(
            'p1',
            'moving',
            $this->telemetry(40),
            $this->progress('p1', travelled: 80, remaining: 49920, total: 50000),
            now()->addSecond()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::ArrivedPickup->value, $arrived->currentStage);
        $this->assertTrue($arrived->pickupGeofenceReached);

        $loading = $engine->evaluate(
            'p1',
            'idle',
            $this->telemetry(0),
            $this->progress('p1', travelled: 80, remaining: 49920, total: 50000),
            now()->addSeconds(2)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Loading->value, $loading->currentStage);
    }

    public function test_pickup_geofence_crossing_on_large_step(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->once()
            ->withArgs(fn ($id, $stage) => $stage === ShipmentLifecycleStage::ArrivedPickup);

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'px', $this->cacheBase(
            'px',
            ShipmentLifecycleStage::EnRoutePickup,
            lastTravelledM: 50.0,
        ), 3600);

        // Jump from 50m → 5000m (skips landing inside 150m)
        $snap = $engine->evaluate(
            'px',
            'moving',
            $this->telemetry(90),
            $this->progress('px', travelled: 5000, remaining: 45000, total: 50000),
            now()->toIso8601String()
        );

        $this->assertSame(ShipmentLifecycleStage::ArrivedPickup->value, $snap->currentStage);
        $this->assertTrue($snap->pickupGeofenceReached);
    }

    public function test_repeated_ticks_inside_pickup_do_not_re_emit_arrival(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->once()
            ->withArgs(fn ($id, $stage) => $stage === ShipmentLifecycleStage::Loading);

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'p2', $this->cacheBase(
            'p2',
            ShipmentLifecycleStage::ArrivedPickup,
            lastTravelledM: 40.0,
            pickupGeofenceReached: true,
        ), 3600);

        $first = $engine->evaluate(
            'p2',
            'idle',
            $this->telemetry(0),
            $this->progress('p2', travelled: 40, remaining: 49960, total: 50000),
            now()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Loading->value, $first->currentStage);

        for ($i = 0; $i < 5; $i++) {
            $tick = $engine->evaluate(
                'p2',
                'idle',
                $this->telemetry(0),
                $this->progress('p2', travelled: 40, remaining: 49960, total: 50000),
                now()->addSeconds(10 + $i)->toIso8601String()
            );
            $this->assertSame(ShipmentLifecycleStage::Loading->value, $tick->currentStage);
            $this->assertFalse($tick->stageChangedThisTick);
        }
    }

    public function test_departure_requests_in_transit_exactly_once(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')
            ->once()
            ->withArgs(fn ($id, $stage) => $stage === ShipmentLifecycleStage::Departed);

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'p3', $this->cacheBase(
            'p3',
            ShipmentLifecycleStage::Loaded,
            lastTravelledM: 40.0,
            pickupGeofenceReached: true,
            loadingCompleted: now()->subMinute()->toIso8601String(),
        ), 3600);

        $departed = $engine->evaluate(
            'p3',
            'moving',
            $this->telemetry(30),
            $this->progress('p3', travelled: 200, remaining: 49800, total: 50000),
            now()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Departed->value, $departed->currentStage);

        // Next tick → EnRouteDelivery should also call adapter once more for EnRouteDelivery stage
        $adapter2 = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter2->shouldReceive('applyOperationalStage')
            ->once()
            ->withArgs(fn ($id, $stage) => $stage === ShipmentLifecycleStage::EnRouteDelivery);

        $engine2 = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter2);
        // reuse cache written by first engine
        $enRoute = $engine2->evaluate(
            'p3',
            'moving',
            $this->telemetry(40),
            $this->progress('p3', travelled: 400, remaining: 49600, total: 50000),
            now()->addSeconds(5)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $enRoute->currentStage);
    }

    public function test_delivery_approach_unload_then_delivered(): void
    {
        $stages = [];
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')
            ->andReturnUsing(function ($id, $stage) use (&$stages) {
                $stages[] = $stage;
            });

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 30, $adapter);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'d1', $this->cacheBase(
            'd1',
            ShipmentLifecycleStage::EnRouteDelivery,
            lastTravelledM: 49000.0,
            lastRemainingDistanceM: 1000.0,
            pickupGeofenceReached: true,
        ), 3600);

        $arrived = $engine->evaluate(
            'd1',
            'moving',
            $this->telemetry(50),
            $this->progress('d1', travelled: 49920, remaining: 80, total: 50000),
            now()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::ArrivedDelivery->value, $arrived->currentStage);

        $unload = $engine->evaluate(
            'd1',
            'idle',
            $this->telemetry(0),
            $this->progress('d1', travelled: 49920, remaining: 80, total: 50000),
            now()->addSeconds(5)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Unloading->value, $unload->currentStage);

        // Stay unloading — no Delivered yet
        $still = $engine->evaluate(
            'd1',
            'idle',
            $this->telemetry(0),
            $this->progress('d1', travelled: 49920, remaining: 80, total: 50000),
            now()->addSeconds(10)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Unloading->value, $still->currentStage);

        $delivered = $engine->evaluate(
            'd1',
            'idle',
            $this->telemetry(0),
            $this->progress('d1', travelled: 50000, remaining: 0, total: 50000, completed: true),
            now()->addSeconds(40)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Delivered->value, $delivered->currentStage);

        $this->assertContains(ShipmentLifecycleStage::Delivered, $stages);
        $this->assertContains(ShipmentLifecycleStage::ArrivedDelivery, $stages);
        $this->assertContains(ShipmentLifecycleStage::Unloading, $stages);
    }

    public function test_delivery_geofence_crossing_jump(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->once();

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'dx', $this->cacheBase(
            'dx',
            ShipmentLifecycleStage::EnRouteDelivery,
            lastTravelledM: 49000.0,
            lastRemainingDistanceM: 800.0,
            pickupGeofenceReached: true,
        ), 3600);

        $snap = $engine->evaluate(
            'dx',
            'moving',
            $this->telemetry(90),
            $this->progress('dx', travelled: 50000, remaining: 0, total: 50000, completed: true),
            now()->toIso8601String()
        );

        $this->assertSame(ShipmentLifecycleStage::ArrivedDelivery->value, $snap->currentStage);
        $this->assertTrue($snap->deliveryGeofenceReached);
    }

    public function test_does_not_regress_from_en_route_delivery_to_pickup(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->never();

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'nr', $this->cacheBase(
            'nr',
            ShipmentLifecycleStage::EnRouteDelivery,
            lastTravelledM: 20000.0,
            lastRemainingDistanceM: 30000.0,
            pickupGeofenceReached: true,
        ), 3600);

        $snap = $engine->evaluate(
            'nr',
            'moving',
            $this->telemetry(60),
            $this->progress('nr', travelled: 20100, remaining: 29900, total: 50000),
            now()->toIso8601String()
        );

        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $snap->currentStage);
        $this->assertFalse($snap->stageChangedThisTick);
    }

    public function test_bootstrap_assigned_does_not_fast_forward_from_progress(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->once(); // Assigned→Accepted

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter);

        $snap = $engine->evaluate(
            'missing-shipment-id-bootstrap',
            'moving',
            $this->telemetry(60),
            $this->progress('missing-shipment-id-bootstrap', travelled: 25000, remaining: 25000, total: 50000),
            now()->toIso8601String()
        );

        // Must not jump to EnRouteDelivery from ≥2% progress.
        $this->assertSame(ShipmentLifecycleStage::Accepted->value, $snap->currentStage);
        $this->assertNotSame(ShipmentLifecycleStage::EnRouteDelivery->value, $snap->currentStage);
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheBase(
        string $id,
        ShipmentLifecycleStage $stage,
        ?float $lastTravelledM = null,
        ?float $lastRemainingDistanceM = null,
        bool $pickupGeofenceReached = false,
        bool $deliveryGeofenceReached = false,
        ?string $loadingCompleted = null,
    ): array {
        return [
            'shipment_id' => $id,
            'current_stage' => $stage->value,
            'previous_stage' => null,
            'transition_reason' => null,
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => $loadingCompleted,
            'departure_time' => null,
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => $lastTravelledM,
            'last_remaining_distance_m' => $lastRemainingDistanceM,
            'pickup_geofence_reached' => $pickupGeofenceReached,
            'delivery_geofence_reached' => $deliveryGeofenceReached,
        ];
    }

    private function progress(
        string $shipmentId,
        float $travelled,
        float $remaining,
        float $total,
        bool $completed = false,
    ): RouteProgressState {
        return new RouteProgressState(
            routeId: 'r-'.$shipmentId,
            shipmentId: $shipmentId,
            vehicleId: 'v-'.$shipmentId,
            polylineIndex: 1,
            segmentIndex: 1,
            distanceTravelledM: $travelled,
            remainingDistanceM: $remaining,
            completionPercentage: $total > 0 ? ($travelled / $total) * 100 : 0,
            currentLatitude: 48.1,
            currentLongitude: 11.5,
            heading: 90,
            completed: $completed,
            lastTick: now()->toIso8601String(),
            totalDistanceM: $total,
        );
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
