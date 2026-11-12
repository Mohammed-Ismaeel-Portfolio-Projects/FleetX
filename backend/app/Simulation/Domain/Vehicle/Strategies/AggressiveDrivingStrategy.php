<?php

namespace App\Simulation\Domain\Vehicle\Strategies;

use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Domain\Vehicle\DTOs\VehicleDynamicsOutput;
use App\Simulation\Core\SimulationClock;

/**
 * AggressiveDrivingStrategy - High-performance driving behavior.
 * Faster acceleration, higher speeds, lower fuel efficiency.
 */
class AggressiveDrivingStrategy implements DrivingDynamicsStrategy
{
    public function calculateDynamics(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
        SimulationClock $clock,
        float $elapsedMs
    ): VehicleDynamicsOutput {
        // TODO: Implement realistic driving dynamics formulas
        // This is architecture preparation only
        
        // Placeholder: return current state values as target with metadata
        return VehicleDynamicsOutput::fromCurrentState(
            $state->currentSpeed,
            $state->rpm,
            $state->currentGear,
            $state->throttlePosition,
            $state->engineLoad,
            generatedBy: $this->getName(),
            reason: 'Aggressive driving behavior',
        );
    }

    public function getName(): string
    {
        return 'aggressive_driving';
    }
}
