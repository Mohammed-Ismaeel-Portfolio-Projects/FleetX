<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Journey\JourneyPlanStore;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Journey\RestStopPlanner;
use App\Simulation\Lifecycle\GeofenceProximityDetector;
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
 * C2.9-C2.8 Step 5 — lifecycle rest stages stay ops-only / In Transit boundary.
 */
class ShipmentLifecycleRestOperationalStagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Log::spy();
    }

    public function test_en_route_approaches_rest_then_rests_then_resumes(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->andReturnNull();

        $journey = new JourneyRestEngine(
            store: new JourneyPlanStore(),
            planner: new RestStopPlanner(80_000, 60, 20_000, 80),
            geofence: new GeofenceProximityDetector(),
            approachDistanceM: 2000,
            geofenceRadiusM: 150,
        );
        $journey->ensurePlan('r1', 250_000);

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter, null, $journey);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'r1', [
            'shipment_id' => 'r1',
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'en route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => now()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => 78_000.0,
            'last_remaining_distance_m' => 172_000.0,
            'pickup_geofence_reached' => true,
        ], 3600);

        $approach = $engine->evaluate(
            'r1',
            'moving',
            $this->telemetry(70),
            $this->progress('r1', 78_500, 171_500, 250_000),
            now()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::ApproachingRestStop->value, $approach->currentStage);

        $resting = $engine->evaluate(
            'r1',
            'idle',
            $this->telemetry(0),
            $this->progress('r1', 80_000, 170_000, 250_000),
            now()->addSeconds(5)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Resting->value, $resting->currentStage);
        $this->assertTrue($resting->isResting);

        $still = $engine->evaluate(
            'r1',
            'idle',
            $this->telemetry(0),
            $this->progress('r1', 80_000, 170_000, 250_000),
            now()->addSeconds(20)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::Resting->value, $still->currentStage);
        $this->assertFalse($still->stageChangedThisTick);

        $resume = $engine->evaluate(
            'r1',
            'idle',
            $this->telemetry(0),
            $this->progress('r1', 80_000, 170_000, 250_000),
            now()->addSeconds(90)->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $resume->currentStage);
        $this->assertFalse($resume->isResting);
    }

    public function test_short_route_skips_rest_stages(): void
    {
        $adapter = Mockery::mock(ShipmentLifecycleTransitionAdapter::class);
        $adapter->shouldReceive('applyOperationalStage')->never();

        $journey = new JourneyRestEngine(
            store: new JourneyPlanStore(),
            planner: new RestStopPlanner(80_000, 60, 20_000, 80),
            geofence: new GeofenceProximityDetector(),
        );
        $journey->ensurePlan('r2', 40_000);

        $engine = new ShipmentLifecycleEngine(150, 8, 60, 60, $adapter, null, $journey);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.'r2', [
            'shipment_id' => 'r2',
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'en route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => now()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => 20_000.0,
            'pickup_geofence_reached' => true,
        ], 3600);

        $snap = $engine->evaluate(
            'r2',
            'moving',
            $this->telemetry(60),
            $this->progress('r2', 25_000, 15_000, 40_000),
            now()->toIso8601String()
        );
        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $snap->currentStage);
        $this->assertFalse($snap->stageChangedThisTick);
        $this->assertNull($snap->nextRestOffsetM);
    }

    public function test_vehicle_state_evaluator_holds_idle_while_resting(): void
    {
        $evaluator = new \App\Simulation\Domain\Vehicle\Services\VehicleStateTransitionEvaluator();
        $runtime = \App\Simulation\Domain\Vehicle\Models\VehicleRuntime::createInitial('v1');
        $runtime->setState(\App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState::IGNITION_ON);
        $runtime->setState(\App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState::STARTING);
        $runtime->setState(\App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState::IDLE);

        $desired = $evaluator->evaluateDesiredState($runtime, [
            'has_active_assignment' => true,
            'arrived_at_destination' => false,
            'has_gps_fix' => true,
            'is_resting' => true,
        ]);
        $this->assertNull($desired);

        $runtime->setState(\App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState::MOVING);
        $stop = $evaluator->evaluateDesiredState($runtime, [
            'has_active_assignment' => true,
            'arrived_at_destination' => false,
            'has_gps_fix' => true,
            'is_resting' => true,
        ]);
        $this->assertSame(
            \App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState::STOPPING,
            $stop
        );
    }

    private function progress(
        string $id,
        float $travelled,
        float $remaining,
        float $total,
    ): RouteProgressState {
        return new RouteProgressState(
            'r', $id, 'v', 1, 1, $travelled, $remaining,
            ($travelled / $total) * 100, 48.1, 11.5, 90, false, now()->toIso8601String(), $total
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
