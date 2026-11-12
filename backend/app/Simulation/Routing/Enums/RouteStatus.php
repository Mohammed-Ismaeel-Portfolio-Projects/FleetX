<?php

namespace App\Simulation\Routing\Enums;

/**
 * RouteStatus Enum.
 * Represents the status of a route.
 */
enum RouteStatus: string
{
    case PENDING = 'pending';
    case CALCULATING = 'calculating';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case IN_PROGRESS = 'in_progress';
}
