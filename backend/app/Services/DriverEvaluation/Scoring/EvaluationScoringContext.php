<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Scoring;

/**
 * Context for category evaluability (DRIVER-EVAL-04).
 * Missing evidence ⇒ NOT_EVALUABLE, never an automatic penalty.
 */
final class EvaluationScoringContext
{
    public function __construct(
        public readonly int $telemetrySampleCount = 0,
        public readonly bool $routeGeometryAvailable = false,
        public readonly bool $timingDataAvailable = false,
        public readonly bool $workflowDataAvailable = true,
    ) {}
}
