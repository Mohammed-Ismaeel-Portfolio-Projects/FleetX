<?php

namespace App\Simulation\Domain\Fuel\Calculators;

use App\Simulation\Domain\Fuel\DTOs\FuelConsumptionOutput;
use App\Simulation\Domain\Fuel\Strategies\FuelConsumptionStrategy;
use App\Simulation\Domain\Fuel\Models\FuelTank;
use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Core\SimulationClock;

/**
 * FuelConsumptionCalculator.
 * Orchestrates fuel consumption calculation using strategies.
 */
class FuelConsumptionCalculator
{
    private array $strategies = [];

    public function __construct()
    {
        $this->registerDefaultStrategies();
    }

    /**
     * Register a fuel consumption strategy.
     */
    public function registerStrategy(string $fuelType, FuelConsumptionStrategy $strategy): void
    {
        $this->strategies[$fuelType] = $strategy;
    }

    /**
     * Get strategy for fuel type.
     */
    public function getStrategy(string $fuelType): ?FuelConsumptionStrategy
    {
        return $this->strategies[$fuelType] ?? null;
    }

    /**
     * Calculate fuel consumption.
     */
    public function calculate(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
        FuelTank $fuelTank,
        SimulationClock $clock,
        float $elapsedMs,
    ): FuelConsumptionOutput {
        $strategy = $this->getStrategy($fuelTank->fuelType);
        
        if (!$strategy) {
            // Default to petrol if no strategy found
            $strategy = $this->strategies['petrol'] ?? null;
        }

        if (!$strategy) {
            throw new \RuntimeException("No fuel consumption strategy available for fuel type: {$fuelTank->fuelType}");
        }

        return $strategy->calculate(
            $vehicle,
            $state,
            $runtime,
            $fuelTank,
            $clock,
            $elapsedMs,
        );
    }

    /**
     * Register default strategies.
     */
    private function registerDefaultStrategies(): void
    {
        // Strategies will be registered externally or via configuration
    }
}
