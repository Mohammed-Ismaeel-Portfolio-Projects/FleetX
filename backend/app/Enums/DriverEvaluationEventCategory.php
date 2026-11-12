<?php

declare(strict_types=1);

namespace App\Enums;

enum DriverEvaluationEventCategory: string
{
    case SAFETY = 'safety';
    case OPERATIONAL = 'operational';
    case ROUTE_COMPLIANCE = 'route_compliance';
    case TIMING = 'timing';
}
