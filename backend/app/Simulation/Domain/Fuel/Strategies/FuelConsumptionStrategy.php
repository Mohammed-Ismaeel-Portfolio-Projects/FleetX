<?php

namespace App\Simulation\Domain\Fuel\Strategies;

use App\Simulation\Domain\Fuel\DTOs\FuelConsumptionOutput;
use App\Simulation\Domain\Fuel\Models\FuelTank;
use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Core\SimulationClock;

/**
 * FuelConsumptionStrategy Interface.
 * Defines contract for fuel consumption calculation strategies.
 */
interface FuelConsumptionStrategy
{
    /**
     * Calculate fuel consumption metrics.
     * Returns FuelConsumptionOutput with calculated values.
     * 
     * @param Vehicle $vehicle Static vehicle information
     * @param VehicleState $state Current vehicle state
     * @param VehicleRuntime $runtime Vehicle runtime information
     * @param FuelTank $fuelTank Fuel tank state
     * @param SimulationClock $clock Simulation clock
     * @param float $elapsedMs Elapsed time since last tick in milliseconds
     * 
     * @return FuelConsumptionOutput Calculated consumption metrics
     */
    public function calculate(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
        FuelTank $fuelTank,
        SimulationClock $clock,
        float $elapsedMs
    ): FuelConsumptionOutput;

    /**
     * Get strategy name.
     */
    public function getName(): string;

    /**
     * Get supported fuel type.
     */
    public function getFuelType(): string;
}
