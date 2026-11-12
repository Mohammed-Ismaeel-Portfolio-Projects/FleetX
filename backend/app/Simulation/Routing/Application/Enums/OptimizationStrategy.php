<?php

namespace App\Simulation\Routing\Application\Enums;

/**
 * OptimizationStrategy Enum.
 * Represents route optimization strategies.
 */
enum OptimizationStrategy: string
{
    case FASTEST = 'fastest';
    case SHORTEST = 'shortest';
    case ECO = 'eco';
    case BALANCED = 'balanced';
    case TRUCK_OPTIMIZED = 'truck_optimized';
}
