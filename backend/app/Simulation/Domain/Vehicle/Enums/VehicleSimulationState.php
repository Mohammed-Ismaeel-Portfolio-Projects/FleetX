<?php

namespace App\Simulation\Domain\Vehicle\Enums;

/**
 * VehicleSimulationState Enum.
 * Defines all possible simulation states for a vehicle.
 */
enum VehicleSimulationState: string
{
    case OFF = 'off';
    case IGNITION_ON = 'ignition_on';
    case STARTING = 'starting';
    case IDLE = 'idle';
    case MOVING = 'moving';
    case STOPPING = 'stopping';
    case PARKED = 'parked';
    case BROKEN_DOWN = 'broken_down';
    case MAINTENANCE = 'maintenance';

    /**
     * Check if state is a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PARKED, self::BROKEN_DOWN, self::MAINTENANCE]);
    }

    /**
     * Check if state is an active state.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::STARTING, self::IDLE, self::MOVING, self::STOPPING]);
    }

    /**
     * Check if state is an inactive state.
     */
    public function isInactive(): bool
    {
        return in_array($this, [self::OFF, self::IGNITION_ON, self::PARKED, self::BROKEN_DOWN, self::MAINTENANCE]);
    }
}
