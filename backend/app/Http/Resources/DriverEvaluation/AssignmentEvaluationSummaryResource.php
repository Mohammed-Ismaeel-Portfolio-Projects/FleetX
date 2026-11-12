<?php

declare(strict_types=1);

namespace App\Http\Resources\DriverEvaluation;

use App\Models\AssignmentEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Evaluation history item (DRIVER-EVAL-06). Persisted scores only.
 *
 * @mixin AssignmentEvaluation
 */
class AssignmentEvaluationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssignmentEvaluation $evaluation */
        $evaluation = $this->resource;

        return [
            'evaluation_id' => (int) $evaluation->id,
            'assignment_id' => (int) $evaluation->assignment_id,
            'shipment_id' => $evaluation->shipment_id,
            'driver_id' => (int) $evaluation->driver_id,
            'revision' => (int) $evaluation->revision,
            'status' => $evaluation->status?->value,
            'evaluation_mode' => $evaluation->evaluation_mode?->value,
            'overall_score' => $evaluation->overall_score !== null ? (float) $evaluation->overall_score : null,
            'score_label' => $evaluation->score_label,
            'confidence' => $evaluation->confidence?->value,
            'evidence_quality' => $evaluation->evidence_quality?->value,
            'scoring_version' => $evaluation->scoring_version,
            'category_scores' => $evaluation->category_scores,
            'finalized_at' => $evaluation->finalized_at?->format('Y-m-d H:i:s'),
            'completed_at' => $evaluation->completed_at?->format('Y-m-d H:i:s'),
            'assignment_code' => $this->when(
                $evaluation->relationLoaded('assignment'),
                fn () => $evaluation->assignment?->assignment_code
            ),
            'shipment_number' => $this->when(
                $evaluation->relationLoaded('shipment'),
                fn () => $evaluation->shipment?->shipment_number
            ),
        ];
    }
}
