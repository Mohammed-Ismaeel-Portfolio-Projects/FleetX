<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation;

use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;

/**
 * Read-only resolver for the current AssignmentEvaluation (DRIVER-EVAL-08A).
 *
 * Never creates evaluations. Never scores, detects, aggregates, or loads telemetry.
 *
 * Selection:
 *  1. Latest mutable LIVE revision (PENDING / PROCESSING)
 *  2. Else latest finalized LIVE eligible revision (COMPLETED / INCOMPLETE)
 */
class CurrentAssignmentEvaluationReader
{
    public function resolve(
        Assignment $assignment,
        AssignmentEvaluationMode $mode = AssignmentEvaluationMode::LIVE,
    ): ?AssignmentEvaluation {
        $mutable = AssignmentEvaluation::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_mode', $mode->value)
            ->whereIn('status', [
                AssignmentEvaluationStatus::PENDING->value,
                AssignmentEvaluationStatus::PROCESSING->value,
            ])
            ->whereNull('finalized_at')
            ->orderByDesc('revision')
            ->first();

        if ($mutable !== null) {
            return $mutable;
        }

        return AssignmentEvaluation::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_mode', $mode->value)
            ->whereIn('status', [
                AssignmentEvaluationStatus::COMPLETED->value,
                AssignmentEvaluationStatus::INCOMPLETE->value,
            ])
            ->whereNotNull('finalized_at')
            ->orderByDesc('revision')
            ->first();
    }
}
