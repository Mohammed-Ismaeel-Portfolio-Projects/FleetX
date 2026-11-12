<?php

namespace App\Simulation\Domain\Vehicle\Services;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;

/**
 * Evaluates the next desired VehicleSimulationState from current runtime + tick context.
 * Deterministic — no random state selection.
 */
class VehicleStateTransitionEvaluator
{
    /**
     * @param array{
     *     has_active_assignment: bool,
     *     arrived_at_destination: bool,
     *     has_gps_fix: bool,
     *     is_resting?: bool
     * } $context
     */
    public function evaluateDesiredState(VehicleRuntime $runtime, array $context): ?VehicleSimulationState
    {
        $current = $runtime->getCurrentState();
        $hasAssignment = (bool) ($context['has_active_assignment'] ?? false);
        $arrived = (bool) ($context['arrived_at_destination'] ?? false);
        $isResting = (bool) ($context['is_resting'] ?? false);

        return match ($current) {
            VehicleSimulationState::OFF => VehicleSimulationState::IGNITION_ON,
            VehicleSimulationState::IGNITION_ON => VehicleSimulationState::STARTING,
            VehicleSimulationState::STARTING => VehicleSimulationState::IDLE,
            VehicleSimulationState::IDLE => $this->fromIdle($hasAssignment, $arrived, $isResting),
            VehicleSimulationState::MOVING => ($arrived || $isResting)
                ? VehicleSimulationState::STOPPING
                : null,
            VehicleSimulationState::STOPPING => $arrived
                ? VehicleSimulationState::PARKED
                : VehicleSimulationState::IDLE,
            VehicleSimulationState::PARKED => ($hasAssignment && !$arrived && !$isResting)
                ? VehicleSimulationState::IGNITION_ON
                : null,
            VehicleSimulationState::BROKEN_DOWN => VehicleSimulationState::MAINTENANCE,
            VehicleSimulationState::MAINTENANCE => VehicleSimulationState::OFF,
        };
    }

    public function reasonForTransition(
        VehicleSimulationState $from,
        VehicleSimulationState $to,
        array $context
    ): string {
        $arrived = (bool) ($context['arrived_at_destination'] ?? false);
        $hasAssignment = (bool) ($context['has_active_assignment'] ?? false);
        $isResting = (bool) ($context['is_resting'] ?? false);

        return match (true) {
            $from === VehicleSimulationState::OFF && $to === VehicleSimulationState::IGNITION_ON
                => 'Active fleet assignment requires ignition',
            $from === VehicleSimulationState::IGNITION_ON && $to === VehicleSimulationState::STARTING
                => 'Engine start sequence',
            $from === VehicleSimulationState::STARTING && $to === VehicleSimulationState::IDLE
                => 'Engine running; awaiting departure',
            $from === VehicleSimulationState::IDLE && $to === VehicleSimulationState::MOVING
                => 'Active assignment en route',
            $from === VehicleSimulationState::IDLE && $to === VehicleSimulationState::PARKED
                => 'Arrived at destination; parking',
            $from === VehicleSimulationState::MOVING && $to === VehicleSimulationState::STOPPING && $isResting
                => 'Planned rest stop; initiating stop',
            $from === VehicleSimulationState::MOVING && $to === VehicleSimulationState::STOPPING
                => 'Destination reached; initiating stop',
            $from === VehicleSimulationState::STOPPING && $to === VehicleSimulationState::PARKED
                => 'Stop complete; vehicle parked',
            $from === VehicleSimulationState::STOPPING && $to === VehicleSimulationState::IDLE
                => $isResting ? 'Rest stop active' : 'Temporary stop complete',
            $from === VehicleSimulationState::PARKED && $to === VehicleSimulationState::IGNITION_ON
                => 'Resuming assigned route',
            $from === VehicleSimulationState::BROKEN_DOWN && $to === VehicleSimulationState::MAINTENANCE
                => 'Vehicle entered maintenance',
            $from === VehicleSimulationState::MAINTENANCE && $to === VehicleSimulationState::OFF
                => 'Maintenance complete; power down',
            default => sprintf(
                'Condition-driven transition (assignment=%s, arrived=%s, resting=%s)',
                $hasAssignment ? 'yes' : 'no',
                $arrived ? 'yes' : 'no',
                $isResting ? 'yes' : 'no'
            ),
        };
    }

    private function fromIdle(bool $hasAssignment, bool $arrived, bool $isResting): ?VehicleSimulationState
    {
        if ($arrived) {
            return VehicleSimulationState::PARKED;
        }

        if ($isResting) {
            return null;
        }

        if ($hasAssignment) {
            return VehicleSimulationState::MOVING;
        }

        return null;
    }
}
