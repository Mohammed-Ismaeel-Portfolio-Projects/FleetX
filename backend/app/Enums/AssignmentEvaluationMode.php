<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which telemetry evidence class this evaluation accepts (DRIVER-EVAL-02).
 *
 * Real-driver performance defaults to LIVE. Simulation must be an explicit policy choice.
 */
enum AssignmentEvaluationMode: string
{
    case LIVE = 'live';
    case SIMULATION = 'simulation';
    case MIXED = 'mixed';
}
