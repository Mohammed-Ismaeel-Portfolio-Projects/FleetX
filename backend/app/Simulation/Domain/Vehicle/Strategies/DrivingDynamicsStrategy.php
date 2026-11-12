<?php

namespace App\Simulation\Domain\Vehicle\Strategies;

use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Domain\Vehicle\DTOs\VehicleDynamicsOutput;
use App\Simulation\Core\SimulationClock;

/**
 * DrivingDynamicsStrategy Interface.
 * Defines contract for vehicle dynamics calculation strategies.
 */
interface DrivingDynamicsStrategy
{
    /**
     * Calculate vehicle driving dynamics.
     * Returns target values as VehicleDynamicsOutput DTO.
     * 
     * @param Vehicle $vehicle Static vehicle information
     * @param VehicleState $state Current vehicle state
     * @param VehicleRuntime $runtime Vehicle runtime information
     * @param SimulationClock $clock Simulation clock for time-based calculations
     * @param float $elapsedMs Elapsed time since last tick in milliseconds
     * 
     * @return VehicleDynamicsOutput Target driving values
     */
    public function calculateDynamics(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
        SimulationClock $clock,
        float $elapsedMs
    ): VehicleDynamicsOutput;

    /**
     * Get strategy name.
     */
    public function getName(): string;
}
