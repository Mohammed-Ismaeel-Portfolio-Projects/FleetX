<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sample-size maturity for driver-level performance (DRIVER-EVAL-05).
 */
enum DriverPerformanceMaturity: string
{
    /** No eligible scored evaluations. */
    case UNAVAILABLE = 'unavailable';

    /** Some scored evaluations, below established sample threshold. */
    case PROVISIONAL = 'provisional';

    /** At or above configured sample threshold. */
    case ESTABLISHED = 'established';
}
