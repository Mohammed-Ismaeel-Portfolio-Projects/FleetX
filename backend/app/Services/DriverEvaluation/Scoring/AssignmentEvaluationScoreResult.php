<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Scoring;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\DriverEvaluationScoreLabel;

/**
 * Pure scoring result DTO (DRIVER-EVAL-04). Not persisted until finalize.
 *
 * @phpstan-type CategoryBreakdown array{
 *   key: string,
 *   evaluable: bool,
 *   score: float|null,
 *   configured_weight: float,
 *   normalized_weight: float|null,
 *   event_contributions: list<array<string, mixed>>,
 *   skipped_overlaps: list<array<string, mixed>>,
 *   reason?: string
 * }
 */
final class AssignmentEvaluationScoreResult
{
    /**
     * @param  list<CategoryBreakdown>  $categories
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ?float $overallScore,
        public readonly ?DriverEvaluationScoreLabel $scoreLabel,
        public readonly AssignmentEvaluationConfidence $confidence,
        public readonly string $scoringCode,
        public readonly string $scoringVersion,
        public readonly array $categories,
        public readonly array $metadata,
        public readonly bool $isScoreAvailable,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function categoryScoresPayload(): array
    {
        $out = [];
        foreach ($this->categories as $category) {
            $out[$category['key']] = [
                'evaluable' => $category['evaluable'],
                'score' => $category['score'],
                'configured_weight' => $category['configured_weight'],
                'normalized_weight' => $category['normalized_weight'],
                'event_contributions' => $category['event_contributions'],
                'skipped_overlaps' => $category['skipped_overlaps'],
                'reason' => $category['reason'] ?? null,
            ];
        }

        return $out;
    }
}
