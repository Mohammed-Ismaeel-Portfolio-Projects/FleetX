<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Workflow violations proven from Assignment state only (no speculation).
 */
class WorkflowEvaluationDetector implements EvaluationEventDetector
{
    public function detect(Assignment $assignment, AssignmentEvaluation $evaluation, Collection $logs): array
    {
        unset($logs);

        $assignment->loadMissing(['status']);
        $status = $assignment->status?->name;
        $meta = config('driver_evaluation.detectors.workflow');

        // Completed without POD phase — CompleteDriverAssignmentAction requires pod_submitted.
        if (
            $status === DriverAssignmentTransitionMap::COMPLETED
            && $assignment->execution_phase !== null
            && $assignment->execution_phase !== DriverExecutionPhase::POD_SUBMITTED
        ) {
            return [new DetectedEvaluationCandidate(
                eventCode: DriverEvaluationEventCode::WORKFLOW_VIOLATION,
                evidenceSource: DriverEvaluationEvidenceSource::LIVE,
                detectorCode: $meta['code'],
                detectorVersion: $meta['version'],
                occurredAt: $assignment->ended_at
                    ? Carbon::parse($assignment->ended_at)
                    : ($assignment->updated_at ? Carbon::parse($assignment->updated_at) : now()),
                trackingLogIds: [],
                evidenceMetadata: [
                    'violation' => 'completed_without_pod_submitted_phase',
                    'status' => $status,
                    'execution_phase' => $assignment->execution_phase,
                    'required_phase' => DriverExecutionPhase::POD_SUBMITTED,
                ],
            )];
        }

        return [];
    }
}
