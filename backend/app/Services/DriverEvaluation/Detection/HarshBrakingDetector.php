<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use Illuminate\Support\Collection;

class HarshBrakingDetector implements EvaluationEventDetector
{
    public function __construct(private readonly MotionSpeedEvidenceAnalyzer $analyzer) {}

    public function detect(Assignment $assignment, AssignmentEvaluation $evaluation, Collection $logs): array
    {
        return $this->analyzer->detectHarshBraking($logs, $assignment);
    }
}
