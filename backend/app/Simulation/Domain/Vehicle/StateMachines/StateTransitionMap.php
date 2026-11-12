<?php

namespace App\Simulation\Domain\Vehicle\StateMachines;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;

/**
 * StateTransitionMap - Defines valid state transitions.
 * Maps each state to its allowed next states.
 */
class StateTransitionMap
{
    private array $transitions = [
        VehicleSimulationState::OFF->value => [
            VehicleSimulationState::IGNITION_ON->value,
        ],
        VehicleSimulationState::IGNITION_ON->value => [
            VehicleSimulationState::STARTING->value,
            VehicleSimulationState::OFF->value,
        ],
        VehicleSimulationState::STARTING->value => [
            VehicleSimulationState::IDLE->value,
            VehicleSimulationState::OFF->value,
        ],
        VehicleSimulationState::IDLE->value => [
            VehicleSimulationState::MOVING->value,
            VehicleSimulationState::PARKED->value,
            VehicleSimulationState::OFF->value,
            VehicleSimulationState::BROKEN_DOWN->value,
            VehicleSimulationState::MAINTENANCE->value,
        ],
        VehicleSimulationState::MOVING->value => [
            VehicleSimulationState::STOPPING->value,
            VehicleSimulationState::BROKEN_DOWN->value,
        ],
        VehicleSimulationState::STOPPING->value => [
            VehicleSimulationState::IDLE->value,
            VehicleSimulationState::PARKED->value,
            VehicleSimulationState::BROKEN_DOWN->value,
        ],
        VehicleSimulationState::PARKED->value => [
            VehicleSimulationState::IGNITION_ON->value,
            VehicleSimulationState::OFF->value,
            VehicleSimulationState::MAINTENANCE->value,
        ],
        VehicleSimulationState::BROKEN_DOWN->value => [
            VehicleSimulationState::MAINTENANCE->value,
        ],
        VehicleSimulationState::MAINTENANCE->value => [
            VehicleSimulationState::OFF->value,
            VehicleSimulationState::IDLE->value,
        ],
    ];

    /**
     * Check if transition is valid.
     */
    public function canTransition(VehicleSimulationState $from, VehicleSimulationState $to): bool
    {
        $allowedTransitions = $this->transitions[$from->value] ?? [];
        return in_array($to->value, $allowedTransitions);
    }

    /**
     * Get allowed transitions from a state.
     */
    public function getAllowedTransitions(VehicleSimulationState $from): array
    {
        $allowed = $this->transitions[$from->value] ?? [];
        return array_map(fn($state) => VehicleSimulationState::from($state), $allowed);
    }

    /**
     * Get all transitions.
     */
    public function getAllTransitions(): array
    {
        return $this->transitions;
    }
}
