<?php

namespace App\Simulation\Domain\Vehicle\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Domain\Vehicle\Repositories\VehicleRepository;
use App\Simulation\Domain\Vehicle\Strategies\DrivingDynamicsStrategy;
use App\Simulation\Domain\Vehicle\Strategies\NormalDrivingStrategy;
use App\Simulation\Domain\Vehicle\Strategies\AggressiveDrivingStrategy;
use App\Simulation\Domain\Vehicle\Strategies\EcoDrivingStrategy;
use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;
use App\Simulation\Core\SimulationClock;

/**
 * VehicleDynamicsModule - Vehicle Dynamics Simulation Module.
 * Calculates and updates physical vehicle driving behavior.
 * Only executes when vehicle simulation state is MOVING.
 */
class VehicleDynamicsModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private VehicleRepository $vehicleRepository;
    private SimulationClock $clock;
    
    // Available strategies
    private array $strategies = [];

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
        
        // Get VehicleStore from context
        $vehicleStore = $context->getVehicleStore();
        $this->vehicleRepository = new VehicleRepository($vehicleStore);
        
        // Get SimulationClock from context
        $this->clock = $context->getConfig('simulation_clock') ?? new SimulationClock();
        
        // Register available strategies
        $this->registerStrategies();
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processVehicleDynamics();
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
        return 'vehicle_dynamics';
    }

    public function getPriority(): int
    {
        return 95; // Execute after vehicle_state (100)
    }

    public function getDependencies(): array
    {
        return ['vehicle_state'];
    }

    public function getExecutionInterval(): int
    {
        return 100; // 100 ms
    }

    /**
     * Register available driving strategies.
     */
    private function registerStrategies(): void
    {
        $this->strategies = [
            'normal_driving' => new NormalDrivingStrategy(),
            'aggressive_driving' => new AggressiveDrivingStrategy(),
            'eco_driving' => new EcoDrivingStrategy(),
        ];
    }

    /**
     * Process vehicle dynamics for all moving vehicles.
     */
    private function processVehicleDynamics(): void
    {
        $vehicles = $this->vehicleRepository->getAll();
        
        foreach ($vehicles as $vehicle) {
            $runtime = $this->vehicleRepository->getVehicleRuntime($vehicle->id);
            if (!$runtime) {
                continue;
            }

            // Only process when vehicle is MOVING
            if ($runtime->getCurrentState() !== VehicleSimulationState::MOVING) {
                continue;
            }

            $this->calculateVehicleDynamics($vehicle, $runtime);
        }
    }

    /**
     * Calculate dynamics for a single vehicle.
     */
    private function calculateVehicleDynamics($vehicle, $runtime): void
    {
        $state = $this->vehicleRepository->getVehicleState($vehicle->id);
        if (!$state) {
            return;
        }

        // Get selected strategy
        $strategyName = $runtime->getDrivingStrategy();
        $strategy = $this->strategies[$strategyName] ?? $this->strategies['normal_driving'];

        // Calculate elapsed time in milliseconds
        $elapsedMs = $this->calculateElapsedMs($runtime);

        // Delegate to strategy to get target values
        $dynamicsOutput = $strategy->calculateDynamics(
            $vehicle,
            $state,
            $runtime,
            $this->clock,
            $elapsedMs
        );

        // Apply dynamics output to vehicle state
        $updatedState = $this->applyDynamicsToState($state, $dynamicsOutput);

        // Update vehicle state
        $this->vehicleRepository->updateVehicleState($vehicle->id, $updatedState);
    }

    /**
     * Apply dynamics output to vehicle state.
     * This is where future modules can intervene to modify the output.
     * Preserves all metadata from VehicleDynamicsOutput.
     */
    private function applyDynamicsToState($state, $dynamicsOutput)
    {
        // Apply target values to state
        $state->currentSpeed = $dynamicsOutput->targetSpeed;
        $state->rpm = $dynamicsOutput->targetRPM;
        $state->currentGear = $dynamicsOutput->targetGear;
        $state->throttlePosition = $dynamicsOutput->targetThrottlePosition;
        $state->engineLoad = $dynamicsOutput->targetEngineLoad;
        $state->lastUpdateTime = new \DateTime();

        // Preserve metadata by storing in registry for debugging/audit
        // This allows tracking which strategy generated the values and what modifiers were applied
        $metadata = [
            'generated_by' => $dynamicsOutput->getGeneratedBy(),
            'generation_time' => $dynamicsOutput->getGenerationTime()->format('Y-m-d H:i:s.u'),
            'applied_modifiers' => $dynamicsOutput->getModifiers(),
            'reason' => $dynamicsOutput->getReason(),
        ];

        // Store metadata in engine context registry for this vehicle
        $registryKey = "vehicle_dynamics_metadata_{$state->vehicleId}";
        $this->context->setRegistry($registryKey, $metadata);

        return $state;
    }

    /**
     * Calculate elapsed time since last tick in milliseconds.
     */
    private function calculateElapsedMs($runtime): float
    {
        $currentTime = $this->clock->getCurrentTime();
        $lastTime = $runtime->currentSimulationTime;
        
        $diff = $lastTime->diff($currentTime);
        return ($diff->h * 3600000) + ($diff->i * 60000) + ($diff->s * 1000) + ($diff->f * 1000000);
    }

    /**
     * Register a custom strategy.
     */
    public function registerStrategy(string $name, DrivingDynamicsStrategy $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }

    /**
     * Get available strategies.
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Get strategy by name.
     */
    public function getStrategy(string $name): ?DrivingDynamicsStrategy
    {
        return $this->strategies[$name] ?? null;
    }
}
