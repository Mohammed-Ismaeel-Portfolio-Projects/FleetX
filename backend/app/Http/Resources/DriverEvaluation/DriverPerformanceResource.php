<?php

declare(strict_types=1);

namespace App\Http\Resources\DriverEvaluation;

use App\Models\DriverScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared Overall Driver Performance read contract (DRIVER-EVAL-06 / 08A).
 *
 * Authoritative source: driver_scores projection.
 * Distinct from Current Assignment Evaluation.
 * Reads driver_scores only — never recalculates, scores, or aggregates on GET.
 *
 * @mixin DriverScore
 */
class DriverPerformanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DriverScore|null $score */
        $score = $this->resource;

        if ($score === null) {
            return [
                'driver_id' => null,
                'overall_score' => null,
                'score_label' => null,
                'confidence' => null,
                'maturity' => 'unavailable',
                'trend' => 'insufficient_data',
                'trend_delta' => null,
                'recent_score' => null,
                'previous_score' => null,
                'evaluated_assignments_count' => 0,
                'incomplete_assignments_count' => 0,
                'late_evaluations_count' => 0,
                // Legacy aliases (same semantics as EVAL-05 projection).
                'score' => null,
                'total_deliveries' => 0,
                'late_deliveries' => 0,
                'category_scores' => null,
                'aggregation_version' => null,
                'scoring_versions_seen' => [],
                'last_evaluated_at' => null,
                'aggregated_at' => null,
                'score_available' => false,
            ];
        }

        $meta = is_array($score->aggregation_metadata) ? $score->aggregation_metadata : [];

        return [
            'driver_id' => (int) $score->driver_id,
            'overall_score' => $score->score !== null ? (float) $score->score : null,
            'score_label' => $score->score_label,
            'confidence' => $score->confidence?->value,
            'maturity' => $score->maturity?->value ?? 'unavailable',
            'trend' => $score->trend?->value ?? 'insufficient_data',
            'trend_delta' => $score->trend_delta !== null ? (float) $score->trend_delta : null,
            'recent_score' => $score->recent_score !== null ? (float) $score->recent_score : null,
            'previous_score' => $score->previous_score !== null ? (float) $score->previous_score : null,
            'evaluated_assignments_count' => (int) $score->evaluated_assignments_count,
            'incomplete_assignments_count' => (int) $score->incomplete_assignments_count,
            'late_evaluations_count' => (int) $score->late_deliveries,
            // Legacy field names retained for existing consumers (accurate UI labels required).
            'score' => $score->score !== null ? (float) $score->score : null,
            'total_deliveries' => (int) $score->total_deliveries,
            'late_deliveries' => (int) $score->late_deliveries,
            'category_scores' => $score->category_scores,
            'aggregation_version' => $score->aggregation_version,
            'scoring_versions_seen' => array_values($meta['scoring_versions_seen'] ?? []),
            'last_evaluated_at' => $score->last_evaluated_at?->format('Y-m-d H:i:s'),
            'aggregated_at' => $score->aggregated_at?->format('Y-m-d H:i:s'),
            'score_available' => $score->isScoreAvailable(),
        ];
    }
}
