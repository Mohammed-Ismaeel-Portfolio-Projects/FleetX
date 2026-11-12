<?php

namespace App\Simulation\Domain\Vehicle\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\Vehicle\Repositories\VehicleRepository;
use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;
use App\Simulation\Domain\Vehicle\Configuration\VehicleLifecycleConfiguration;
use App\Simulation\Domain\Vehicle\Events\VehicleStateChanged;
use App\Simulation\Core\SimulationClock;

/**
 * VehicleStateModule - Vehicle State Simulation Module.
 * Manages vehicle lifecycle state transitions based on requests.
 */
class VehicleStateModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private VehicleRepository $vehicleRepository;
    private SimulationClock $clock;
    private VehicleLifecycleConfiguration $lifecycleConfig;
    private EventBusInterface $eventBus;

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
        
        // Get VehicleStore from context
        $vehicleStore = $context->getVehicleStore();
        $this->vehicleRepository = new VehicleRepository($vehicleStore);
        
        // Get EventBus from context
        $this->eventBus = $context->getConfig('event_bus');
        
        // Get SimulationClock from context (will be injected by engine)
        $this->clock = $context->getConfig('simulation_clock') ?? new SimulationClock();
        
        // Load lifecycle configuration
        $configArray = $context->getConfig('vehicle_lifecycle_config', []);
        $this->lifecycleConfig = VehicleLifecycleConfiguration::fromArray($configArray);
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processPendingTransitions();
    }

    public function pause(): void
    {
        // Pause module
    }

    public function resume(): void
    {
        // Resume module
    }

    public function stop(): void
    {
        // Stop module
    }

    public function shutdown(): void
    {
        // Cleanup module
    }

    public function getName(): string
    {
        return 'vehicle_state';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getExecutionInterval(): int
    {
        return 100; // 100 ms
    }

    /**
     * Process pending transition requests.
     */
    private function processPendingTransitions(): void
    {
        $vehicles = $this->vehicleRepository->getAll();
        
        foreach ($vehicles as $vehicle) {
            $runtime = $this->vehicleRepository->getVehicleRuntime($vehicle->id);
            if (!$runtime) {
                continue;
            }

            // Skip if no pending transition
            if (!$runtime->hasPendingTransition()) {
                continue;
            }

            $this->evaluateAndExecuteTransition($vehicle->id, $runtime);
        }
    }

    /**
     * Evaluate and execute pending transition if requirements met.
     */
    private function evaluateAndExecuteTransition(string $vehicleId, $runtime): void
    {
        $pendingTransition = $runtime->getPendingTransition();
        $requestedBy = $runtime->getRequestedBy();
        
        if ($pendingTransition === null) {
            return;
        }

        // Validate transition is still valid
        if (!$runtime->canTransitionTo($pendingTransition)) {
            // Clear invalid transition
            $runtime->clearPendingTransition();
            $this->vehicleRepository->updateVehicleRuntime($vehicleId, $runtime);
            return;
        }

        // Check duration requirements
        $timeInStateMs = $runtime->getTimeInCurrentStateMs();
        $requiredDuration = $this->lifecycleConfig->getDurationForState($runtime->getCurrentState()->value);

        if ($timeInStateMs < $requiredDuration) {
            return; // Not enough time elapsed
        }

        // Execute transition
        $previousState = $runtime->getCurrentState();
        $requestedAt = $runtime->getRequestedAt();
        
        $runtime->executePendingTransition();
        $this->vehicleRepository->updateVehicleRuntime($vehicleId, $runtime);

        // Publish state changed event
        $this->publishStateChangedEvent(
            $vehicleId,
            $previousState,
            $pendingTransition,
            $requestedBy,
            $requestedAt
        );
    }

    /**
     * Publish vehicle state changed event.
     */
    private function publishStateChangedEvent(
        string $vehicleId,
        VehicleSimulationState $previousState,
        VehicleSimulationState $newState,
        string $requestedBy,
        ?\DateTime $requestedAt,
    ): void {
        if ($this->eventBus) {
            $event = VehicleStateChanged::fromRuntime(
                $vehicleId,
                $previousState,
                $newState,
                $requestedBy,
                $requestedAt,
            );
            
            $this->eventBus->publish('vehicle.state_changed', $event->toArray());
        }
    }

    /**
     * Set lifecycle configuration.
     */
    public function setLifecycleConfiguration(VehicleLifecycleConfiguration $config): void
    {
        $this->lifecycleConfig = $config;
    }

    /**
     * Get lifecycle configuration.
     */
    public function getLifecycleConfiguration(): VehicleLifecycleConfiguration
    {
        return $this->lifecycleConfig;
    }
}
