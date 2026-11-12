<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Evidence completeness for an AssignmentEvaluation (DRIVER-EVAL-02).
 * Does not imply score outcome.
 */
enum AssignmentEvaluationEvidenceQuality: string
{
    case SUFFICIENT = 'sufficient';
    case PARTIAL = 'partial';
    case INSUFFICIENT = 'insufficient';
    case AMBIGUOUS = 'ambiguous';
}
