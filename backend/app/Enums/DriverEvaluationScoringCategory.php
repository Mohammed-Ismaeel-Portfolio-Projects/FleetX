<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Scoring-dimension categories (DRIVER-EVAL-04).
 * Distinct from DriverEvaluationEventCategory taxonomy storage.
 */
enum DriverEvaluationScoringCategory: string
{
    case SAFETY = 'safety';
    case DRIVING_BEHAVIOR = 'driving_behavior';
    case ROUTE_COMPLIANCE = 'route_compliance';
    case TIMELINESS = 'timeliness';
    case WORKFLOW_DISCIPLINE = 'workflow_discipline';
}
