<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use Illuminate\Support\Collection;

interface EvaluationEventDetector
{
    /**
     * @param  Collection<int, \App\Models\TrackingLog>  $logs  chronological (oldest → newest), mode-filtered
     * @return list<DetectedEvaluationCandidate>
     */
    public function detect(
        Assignment $assignment,
        AssignmentEvaluation $evaluation,
        Collection $logs,
    ): array;
}
