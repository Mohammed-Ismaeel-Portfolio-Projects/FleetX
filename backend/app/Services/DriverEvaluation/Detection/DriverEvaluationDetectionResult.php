<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;

/**
 * @phpstan-type SkipReason array{reason: string, detail?: string}
 */
final class DriverEvaluationDetectionResult
{
    /**
     * @param  list<AssignmentEvaluationEvent>  $eventsCreated
     * @param  list<AssignmentEvaluationEvent>  $eventsExisting
     * @param  list<array{reason: string, detail?: string}>  $skipped
     */
    public function __construct(
        public readonly AssignmentEvaluation $evaluation,
        public readonly array $eventsCreated,
        public readonly array $eventsExisting,
        public readonly int $telemetrySampleCount,
        public readonly array $skipped = [],
    ) {}

    public function eventCount(): int
    {
        return count($this->eventsCreated) + count($this->eventsExisting);
    }
}
