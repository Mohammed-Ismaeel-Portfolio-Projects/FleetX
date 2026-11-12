<?php

namespace App\Simulation\Runtime;

use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Services\TrackingSimulator\VehicleMovementService;
use App\Simulation\Adapters\TrackingSimulationAdapter;
use App\Simulation\Core\SimulationEngine;
use App\Simulation\Domain\Vehicle\Services\VehicleStateMachineService;
use App\Simulation\Dynamics\VehicleDynamicsEngine;
use App\Simulation\Environment\EnvironmentEngine;
use App\Simulation\Driver\DriverIntelligenceEngine;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Modules\DriverIntelligenceModule;
use App\Simulation\Modules\EnvironmentModule;
use App\Simulation\Modules\RouteProgressModule;
use App\Simulation\Modules\ShipmentLifecycleModule;
use App\Simulation\Modules\TelemetryModule;
use App\Simulation\Modules\TrackingSimulationModule;
use App\Simulation\Modules\VehicleDynamicsModule;
use App\Simulation\Modules\VehicleHealthModule;
use App\Simulation\Modules\VehicleStateMachineModule;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Telemetry\FleetTelemetryEngine;
use Illuminate\Support\Facades\Log;

/**
 * SimulationRuntime
 *
 * Registers: … → Telemetry → Shipment Lifecycle → Tracking.
 */
class SimulationRuntime
{
    private ?SimulationEngine $engine = null;

    private const TICK_INTERVAL_MS = 60_000;

    public function __construct(
        private TrackingSimulationAdapter $adapter,
        private VehicleStateMachineService $vehicleStateMachineService,
        private EuropeanFleetLocations $fleetLocations,
        private RouteProgressEngine $routeProgressEngine,
        private VehicleDynamicsEngine $vehicleDynamicsEngine,
        private FleetTelemetryEngine $fleetTelemetryEngine,
        private VehicleMovementService $movementService,
        private EnvironmentEngine $environmentEngine,
        private DriverIntelligenceEngine $driverIntelligenceEngine,
        private VehicleHealthEngine $vehicleHealthEngine,
        private ShipmentLifecycleEngine $shipmentLifecycleEngine,
    ) {}

    public function start(): void
    {
        $this->adapter->clearLastResult();

        $this->engine = new SimulationEngine();
        $this->engine->initialize([
            'tick_interval_ms' => self::TICK_INTERVAL_MS,
            'time_scale' => 1.0,
        ]);

        $this->engine->registerModule(new VehicleStateMachineModule(
            $this->vehicleStateMachineService,
            $this->fleetLocations,
            $this->routeProgressEngine,
        ));
        $this->engine->registerModule(new EnvironmentModule(
            $this->environmentEngine,
            $this->routeProgressEngine,
        ));
        $this->engine->registerModule(new DriverIntelligenceModule(
            $this->driverIntelligenceEngine,
        ));
        $this->engine->registerModule(new VehicleHealthModule(
            $this->vehicleHealthEngine,
        ));
        $this->engine->registerModule(new VehicleDynamicsModule(
            $this->vehicleDynamicsEngine,
            $this->routeProgressEngine,
            $this->vehicleHealthEngine,
        ));
        $this->engine->registerModule(new RouteProgressModule(
            $this->routeProgressEngine,
            $this->fleetLocations,
            $this->movementService,
        ));
        $this->engine->registerModule(new TelemetryModule(
            $this->fleetTelemetryEngine,
        ));
        $this->engine->registerModule(new ShipmentLifecycleModule(
            $this->shipmentLifecycleEngine,
            $this->routeProgressEngine,
        ));
        $this->engine->registerModule(new TrackingSimulationModule($this->adapter));
        $this->engine->start();

        Log::info('SimulationEngine started');
    }

    /**
     * @return array{
     *     vehicles_processed: int,
     *     tracking_logs_created: int,
     *     vehicles_moved: int,
     *     idle_vehicles: int,
     *     stopped_vehicles: int,
     *     offline_vehicles: int
     * }
     */
    public function executeTick(): array
    {
        if ($this->engine === null || !$this->engine->isRunning()) {
            throw new \RuntimeException('SimulationEngine is not started');
        }

        $startedAt = hrtime(true);

        $this->engine->tick();

        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        Log::info('Tick executed');
        Log::info('Tick duration', [
            'duration_ms' => round($durationMs, 2),
            'tick_count' => $this->engine->getContext()->getTickCount(),
            'simulation_time' => $this->engine->getClock()->getCurrentTime()->format(\DateTime::ATOM),
        ]);

        return $this->adapter->getLastResult();
    }

    public function stop(): void
    {
        if ($this->engine === null) {
            return;
        }

        $this->engine->stop();
        $this->engine->shutdown();
        $this->engine = null;

        Log::info('SimulationEngine stopped');
    }

    /**
     * @return array{
     *     vehicles_processed: int,
     *     tracking_logs_created: int,
     *     vehicles_moved: int,
     *     idle_vehicles: int,
     *     stopped_vehicles: int,
     *     offline_vehicles: int
     * }
     */
    public function runSingleCycle(): array
    {
        $this->start();

        try {
            return $this->executeTick();
        } finally {
            $this->stop();
        }
    }

    public function isRunning(): bool
    {
        return $this->engine !== null && $this->engine->isRunning();
    }

    public function getEngine(): ?SimulationEngine
    {
        return $this->engine;
    }
}
