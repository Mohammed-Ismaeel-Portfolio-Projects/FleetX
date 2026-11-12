<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Evidence severity classification — NOT a score or penalty (DRIVER-EVAL-02).
 */
enum DriverEvaluationEventSeverity: string
{
    case INFO = 'info';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
