<?php

namespace App\Simulation\Routing\Application\Enums;

/**
 * PlanningStatus Enum.
 * Represents the status of route planning.
 */
enum PlanningStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILURE = 'failure';
    case CANCELLED = 'cancelled';
    case TIMEOUT = 'timeout';
}
