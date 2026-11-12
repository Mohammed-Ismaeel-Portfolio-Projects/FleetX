<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Aggregation;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\DriverEvaluationScoreLabel;
use App\Enums\DriverPerformanceMaturity;
use App\Enums\DriverPerformanceTrend;

/**
 * Pure aggregation result DTO (DRIVER-EVAL-05). Not persisted until upsert.
 *
 * @phpstan-type CategoryAgg array{
 *   score: float|null,
 *   sample_count: int,
 *   evaluable_sample_count: int
 * }
 */
final class DriverPerformanceResult
{
    /**
     * @param  array<string, CategoryAgg>  $categoryScores
     * @param  array<string, mixed>  $metadata
     * @param  list<int>  $evaluationIds
     */
    public function __construct(
        public readonly ?float $overallScore,
        public readonly ?DriverEvaluationScoreLabel $scoreLabel,
        public readonly AssignmentEvaluationConfidence $confidence,
        public readonly DriverPerformanceMaturity $maturity,
        public readonly DriverPerformanceTrend $trend,
        public readonly ?float $recentScore,
        public readonly ?float $previousScore,
        public readonly ?float $trendDelta,
        public readonly int $evaluatedAssignmentsCount,
        public readonly int $incompleteAssignmentsCount,
        public readonly int $totalDeliveries,
        public readonly int $lateDeliveries,
        public readonly array $categoryScores,
        public readonly string $aggregationVersion,
        public readonly array $metadata,
        public readonly array $evaluationIds,
        public readonly ?string $lastEvaluatedAt,
    ) {}

    public function isScoreAvailable(): bool
    {
        return $this->overallScore !== null;
    }
}
