<?php

declare(strict_types=1);

namespace App\Http\Resources\DriverEvaluation;

use App\Models\DriverScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact overall driver performance for multi-driver surfaces (DRIVER-EVAL-08A).
 * Reads driver_scores only — never recalculates.
 *
 * @mixin DriverScore
 */
class DriverPerformanceCompactResource extends JsonResource
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
                'score' => null,
                'score_label' => null,
                'confidence' => null,
                'maturity' => 'unavailable',
                'trend' => 'insufficient_data',
                'trend_delta' => null,
                'score_available' => false,
            ];
        }

        $overall = $score->score !== null ? (float) $score->score : null;

        return [
            'driver_id' => (int) $score->driver_id,
            'overall_score' => $overall,
            'score' => $overall,
            'score_label' => $score->score_label,
            'confidence' => $score->confidence?->value,
            'maturity' => $score->maturity?->value ?? 'unavailable',
            'trend' => $score->trend?->value ?? 'insufficient_data',
            'trend_delta' => $score->trend_delta !== null ? (float) $score->trend_delta : null,
            'score_available' => $score->isScoreAvailable(),
        ];
    }
}
