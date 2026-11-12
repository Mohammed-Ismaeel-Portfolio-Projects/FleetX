<?php

namespace App\Simulation\Modules;

use App\Simulation\Adapters\TrackingSimulationAdapter;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;

/**
 * TrackingSimulationModule
 *
 * Consumes Fleet Telemetry + Shipment Lifecycle and persists TrackingLog / TrackingState.
 */
class TrackingSimulationModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;

    public function __construct(
        private TrackingSimulationAdapter $adapter
    ) {}

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
    }

    public function start(): void
    {
        //
    }

    public function tick(): void
    {
        $telemetry = $this->context->getRegistry(TelemetryModule::CONTEXT_KEY, []);
        if (!is_array($telemetry)) {
            $telemetry = [];
        }

        $lifecycle = $this->context->getRegistry(ShipmentLifecycleModule::CONTEXT_KEY, []);
        if (!is_array($lifecycle)) {
            $lifecycle = [];
        }

        $this->adapter->setTelemetryByVehicleId($telemetry);
        $this->adapter->setLifecycleByShipmentId($lifecycle);
        $this->adapter->execute();
    }

    public function pause(): void
    {
        //
    }

    public function resume(): void
    {
        //
    }

    public function stop(): void
    {
        //
    }

    public function shutdown(): void
    {
        //
    }

    public function getName(): string
    {
        return 'tracking_simulation';
    }

    public function getPriority(): int
    {
        // After ShipmentLifecycleModule (65)
        return 70;
    }

    public function getDependencies(): array
    {
        return ['telemetry', 'shipment_lifecycle'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
