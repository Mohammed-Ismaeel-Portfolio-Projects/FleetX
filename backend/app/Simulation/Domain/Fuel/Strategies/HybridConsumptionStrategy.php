<?php

namespace App\Simulation\Domain\Fuel\Strategies;

use App\Simulation\Domain\Fuel\DTOs\FuelConsumptionOutput;
use App\Simulation\Domain\Fuel\Models\FuelTank;
use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Core\SimulationClock;

/**
 * HybridConsumptionStrategy.
 * Calculates fuel consumption for hybrid engines.
 */
class HybridConsumptionStrategy implements FuelConsumptionStrategy
{
    public function calculate(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
        FuelTank $fuelTank,
        SimulationClock $clock,
        float $elapsedMs
    ): FuelConsumptionOutput {
        // TODO: Implement realistic hybrid consumption formulas
        // This is architecture preparation only
        
        // Placeholder: return zero consumption with energy metrics
        return new FuelConsumptionOutput(
            fuelFlow: 0.0,
            litersPerHour: 0.0,
            consumptionRate: 0.0,
            estimatedRemainingRange: 0.0,
            strategy: $this->getName(),
            energyConsumed: 0.0, // kWh equivalent
            energyUnit: 'kWh',
        );
    }

    public function getName(): string
    {
        return 'hybrid_consumption';
    }

    public function getFuelType(): string
    {
        return 'hybrid';
    }
}
