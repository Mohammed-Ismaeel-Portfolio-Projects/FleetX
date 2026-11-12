<?php

namespace App\Simulation\Domain\Fuel\Strategies;

use App\Simulation\Domain\Fuel\DTOs\FuelConsumptionOutput;
use App\Simulation\Domain\Fuel\Models\FuelTank;
use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Core\SimulationClock;

/**
 * PetrolConsumptionStrategy.
 * Calculates fuel consumption for petrol engines.
 */
class PetrolConsumptionStrategy implements FuelConsumptionStrategy
{
    public function calculate(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
        FuelTank $fuelTank,
        SimulationClock $clock,
        float $elapsedMs
    ): FuelConsumptionOutput {
        // TODO: Implement realistic petrol consumption formulas
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
        return 'petrol_consumption';
    }

    public function getFuelType(): string
    {
        return 'petrol';
    }
}
