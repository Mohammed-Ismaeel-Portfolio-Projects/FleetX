<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use Illuminate\Support\Carbon;

/**
 * A single detected evidence candidate prior to persistence (DRIVER-EVAL-03).
 */
final class DetectedEvaluationCandidate
{
    /**
     * @param  list<int>  $trackingLogIds
     * @param  array<string, mixed>  $evidenceMetadata
     */
    public function __construct(
        public readonly DriverEvaluationEventCode $eventCode,
        public readonly DriverEvaluationEvidenceSource $evidenceSource,
        public readonly string $detectorCode,
        public readonly string $detectorVersion,
        public readonly Carbon $occurredAt,
        public readonly array $trackingLogIds = [],
        public readonly array $evidenceMetadata = [],
        public readonly ?Carbon $windowStart = null,
        public readonly ?Carbon $windowEnd = null,
    ) {}
}
