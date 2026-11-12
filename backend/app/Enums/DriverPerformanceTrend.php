<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lightweight trend classification (DRIVER-EVAL-05). Not ML.
 */
enum DriverPerformanceTrend: string
{
    case IMPROVING = 'improving';
    case STABLE = 'stable';
    case DECLINING = 'declining';
    case INSUFFICIENT_DATA = 'insufficient_data';
}
