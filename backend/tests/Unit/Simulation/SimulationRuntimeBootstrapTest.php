<?php

namespace Tests\Unit\Simulation;

use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Services\TrackingSimulator\TrackingSimulatorService;
use App\Services\TrackingSimulator\VehicleMovementService;
use App\Simulation\Adapters\TrackingSimulationAdapter;
use App\Simulation\Domain\Vehicle\Services\VehicleStateMachineService;
use App\Simulation\Domain\Vehicle\Services\VehicleStateTransitionEvaluator;
use App\Simulation\Dynamics\AccelerationModel;
use App\Simulation\Dynamics\BrakingModel;
use App\Simulation\Dynamics\SpeedController;
use App\Simulation\Dynamics\VehicleDynamicsEngine;
use App\Simulation\Environment\EnvironmentEngine;
use App\Simulation\Driver\DriverIntelligenceEngine;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Runtime\SimulationRuntime;
use App\Simulation\Telemetry\FleetTelemetryEngine;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SimulationRuntimeBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_single_cycle_invokes_tracking_simulator_once_via_engine(): void
    {
        $expected = [
            'vehicles_processed' => 3,
            'tracking_logs_created' => 3,
            'vehicles_moved' => 2,
            'idle_vehicles' => 1,
            'stopped_vehicles' => 0,
            'offline_vehicles' => 0,
        ];

        $simulator = Mockery::mock(TrackingSimulatorService::class);
        $simulator->shouldReceive('simulate')->once()->andReturn($expected);

        Log::shouldReceive('info')->withArgs(fn ($msg) => is_string($msg))->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $routeProgress = Mockery::mock(RouteProgressEngine::class);
        $routeProgress->shouldReceive('getProgress')->andReturn(null);
        $routeProgress->shouldReceive('advance')->andReturn(null);
        $routeProgress->shouldReceive('isCompleted')->andReturn(false);

        $adapter = new TrackingSimulationAdapter($simulator);
        $runtime = new SimulationRuntime(
            $adapter,
            new VehicleStateMachineService(new VehicleStateTransitionEvaluator()),
            app(EuropeanFleetLocations::class),
            $routeProgress,
            new VehicleDynamicsEngine(new SpeedController(new AccelerationModel(), new BrakingModel())),
            new FleetTelemetryEngine(),
            app(VehicleMovementService::class),
            new EnvironmentEngine(),
            new DriverIntelligenceEngine(),
            new VehicleHealthEngine(),
            new ShipmentLifecycleEngine(),
        );

        $result = $runtime->runSingleCycle();

        $this->assertSame($expected, $result);
        $this->assertFalse($runtime->isRunning());
    }

    public function test_adapter_passes_telemetry_snapshots_to_simulator(): void
    {
        $snapshot = new TelemetrySnapshot(
            vehicleId: 'veh-1',
            shipmentId: 'ship-1',
            timestamp: now()->toIso8601String(),
            latitude: 48.1,
            longitude: 11.5,
            heading: 90.0,
            currentSpeedKmh: 40.0,
            targetSpeedKmh: 80.0,
            acceleration: 0.06,
            vehicleState: 'moving',
            distanceTravelledMeters: 1000,
            remainingDistanceMeters: 50000,
            completionPercentage: 2.0,
            currentSegment: 1,
            polylineIndex: 10,
            drivingTimeSeconds: 60,
            idleTimeSeconds: 0,
            engineRuntimeSeconds: 60,
            averageSpeedKmh: 40.0,
            odometerMeters: 1000,
            gpsAccuracy: 5.0,
            signalQuality: 'good',
        );

        $simulator = Mockery::mock(TrackingSimulatorService::class);
        $simulator->shouldReceive('simulate')
            ->once()
            ->withArgs(function (array $telemetry, array $lifecycle = []) use ($snapshot) {
                $entry = $telemetry['veh-1'] ?? null;

                return $entry instanceof TelemetrySnapshot
                    && $entry->vehicleId === $snapshot->vehicleId
                    && $entry->vehicleState === 'moving';
            })
            ->andReturn([
                'vehicles_processed' => 1,
                'tracking_logs_created' => 1,
                'vehicles_moved' => 1,
                'idle_vehicles' => 0,
                'stopped_vehicles' => 0,
                'offline_vehicles' => 0,
            ]);

        $adapter = new TrackingSimulationAdapter($simulator);
        $adapter->setTelemetryByVehicleId(['veh-1' => $snapshot]);
        $result = $adapter->execute();

        $this->assertSame(1, $result['vehicles_moved']);
    }
}
