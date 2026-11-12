<?php

namespace App\Simulation\Domain\Fuel\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\Fuel\Calculators\FuelConsumptionCalculator;
use App\Simulation\Domain\Fuel\Strategies\DieselConsumptionStrategy;
use App\Simulation\Domain\Fuel\Strategies\PetrolConsumptionStrategy;
use App\Simulation\Domain\Fuel\Strategies\HybridConsumptionStrategy;
use App\Simulation\Domain\Fuel\Configuration\FuelConfiguration;
use App\Simulation\Domain\Fuel\Events\FuelLevelChanged;
use App\Simulation\Domain\Fuel\Models\FuelTank;
use App\Simulation\Domain\Vehicle\Repositories\VehicleRepository;
use App\Simulation\Core\SimulationClock;

/**
 * FuelModule - Fuel Simulation Orchestrator.
 * Calculates fuel consumption and updates fuel levels.
 */
class FuelModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private VehicleRepository $vehicleRepository;
    private SimulationClock $clock;
    private FuelConfiguration $configuration;
    private EventBusInterface $eventBus;
    
    // Fuel tanks storage (keyed by vehicle ID)
    private array $fuelTanks = [];
    
    // Calculator with strategies
    private FuelConsumptionCalculator $calculator;

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
        
        // Get VehicleStore from context
        $vehicleStore = $context->getVehicleStore();
        $this->vehicleRepository = new VehicleRepository($vehicleStore);
        
        // Get EventBus from context
        $this->eventBus = $context->getConfig('event_bus');
        
        // Get SimulationClock from context
        $this->clock = $context->getConfig('simulation_clock') ?? new SimulationClock();
        
        // Load configuration
        $configArray = $context->getConfig('fuel_config', []);
        $this->configuration = FuelConfiguration::fromArray($configArray);
        
        // Initialize calculator and strategies
        $this->initializeCalculator();
        
        // Initialize fuel tanks for all vehicles
        $this->initializeFuelTanks();
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processFuelConsumption();
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
        return 'fuel';
    }

    public function getPriority(): int
    {
        return 85; // Execute after telemetry (90)
    }

    public function getDependencies(): array
    {
        return ['vehicle_dynamics', 'telemetry'];
    }

    public function getExecutionInterval(): int
    {
        return $this->configuration->getCalculationIntervalMs();
    }

    /**
     * Initialize calculator with strategies.
     */
    private function initializeCalculator(): void
    {
        $this->calculator = new FuelConsumptionCalculator();
        
        // Register default strategies
        $this->calculator->registerStrategy('diesel', new DieselConsumptionStrategy());
        $this->calculator->registerStrategy('petrol', new PetrolConsumptionStrategy());
        $this->calculator->registerStrategy('hybrid', new HybridConsumptionStrategy());
    }

    /**
     * Initialize fuel tanks for all vehicles.
     */
    private function initializeFuelTanks(): void
    {
        $vehicles = $this->vehicleRepository->getAll();
        
        foreach ($vehicles as $vehicle) {
            $fuelType = $vehicle->fuelType ?? 'petrol';
            $capacity = $vehicle->fuelCapacity ?? 60.0; // Default 60 liters
            
            $this->fuelTanks[$vehicle->id] = FuelTank::createInitial(
                $vehicle->id,
                $capacity,
                $fuelType,
            );
        }
    }

    /**
     * Process fuel consumption for all vehicles.
     */
    private function processFuelConsumption(): void
    {
        $vehicles = $this->vehicleRepository->getAll();
        
        foreach ($vehicles as $vehicle) {
            $this->processVehicleFuelConsumption($vehicle);
        }
    }

    /**
     * Process fuel consumption for a single vehicle.
     */
    private function processVehicleFuelConsumption($vehicle): void
    {
        $state = $this->vehicleRepository->getVehicleState($vehicle->id);
        $runtime = $this->vehicleRepository->getVehicleRuntime($vehicle->id);
        
        if (!$state || !$runtime) {
            return;
        }

        // Get fuel tank
        $fuelTank = $this->fuelTanks[$vehicle->id] ?? null;
        if (!$fuelTank) {
            return;
        }

        // Calculate elapsed time
        $elapsedMs = $this->calculateElapsedMs($runtime);

        // Calculate consumption using strategy
        $consumptionOutput = $this->calculator->calculate(
            $vehicle,
            $state,
            $runtime,
            $fuelTank,
            $this->clock,
            $elapsedMs,
        );

        // Apply consumption to fuel tank
        $previousLevel = $fuelTank->getRemainingFuel();
        $previousPercentage = $fuelTank->getFuelPercentage();
        
        $fuelConsumed = $consumptionOutput->fuelFlow * ($elapsedMs / 1000);
        $fuelTank->consumeFuel($fuelConsumed);

        // Update VehicleState fuel level
        $state->fuelLevel = $fuelTank->getFuelPercentage();
        $this->vehicleRepository->updateVehicleState($vehicle->id, $state);

        // Publish fuel level changed event if level changed
        if ($previousLevel !== $fuelTank->getRemainingFuel()) {
            $this->publishFuelLevelChanged(
                $vehicle->id,
                $previousLevel,
                $fuelTank->getRemainingFuel(),
                $previousPercentage,
                $fuelTank->getFuelPercentage(),
                'consumption',
            );
        }
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
     * Publish fuel level changed event.
     */
    private function publishFuelLevelChanged(
        string $vehicleId,
        float $previousLevel,
        float $newLevel,
        float $previousPercentage,
        float $newPercentage,
        string $reason,
    ): void {
        if ($this->eventBus) {
            $event = FuelLevelChanged::fromTankData(
                $vehicleId,
                $previousLevel,
                $newLevel,
                $previousPercentage,
                $newPercentage,
                $reason,
            );
            
            $this->eventBus->publish('fuel.level_changed', $event->toArray());
        }
    }

    /**
     * Get fuel tank for vehicle.
     */
    public function getFuelTank(string $vehicleId): ?FuelTank
    {
        return $this->fuelTanks[$vehicleId] ?? null;
    }

    /**
     * Set fuel configuration.
     */
    public function setConfiguration(FuelConfiguration $config): void
    {
        $this->configuration = $config;
    }

    /**
     * Get fuel configuration.
     */
    public function getConfiguration(): FuelConfiguration
    {
        return $this->configuration;
    }

    /**
     * Register custom strategy.
     */
    public function registerStrategy(string $fuelType, $strategy): void
    {
        $this->calculator->registerStrategy($fuelType, $strategy);
    }
}
