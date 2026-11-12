<?php

declare(strict_types=1);

namespace App\Http\Resources\DriverEvaluation;

use App\Models\AssignmentEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explainable assignment evaluation detail (DRIVER-EVAL-06).
 *
 * @mixin AssignmentEvaluation
 */
class AssignmentEvaluationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssignmentEvaluation $evaluation */
        $evaluation = $this->resource;
        $meta = is_array($evaluation->scoring_metadata) ? $evaluation->scoring_metadata : [];
        $categories = is_array($evaluation->category_scores) ? $evaluation->category_scores : [];

        return [
            'evaluation_id' => (int) $evaluation->id,
            'assignment_id' => (int) $evaluation->assignment_id,
            'shipment_id' => $evaluation->shipment_id,
            'driver_id' => (int) $evaluation->driver_id,
            'vehicle_id' => $evaluation->vehicle_id,
            'revision' => (int) $evaluation->revision,
            'supersedes_evaluation_id' => $evaluation->supersedes_evaluation_id,
            'status' => $evaluation->status?->value,
            'evaluation_mode' => $evaluation->evaluation_mode?->value,
            'overall_score' => $evaluation->overall_score !== null ? (float) $evaluation->overall_score : null,
            'score_label' => $evaluation->score_label,
            'confidence' => $evaluation->confidence?->value,
            'evidence_quality' => $evaluation->evidence_quality?->value,
            'scoring_version' => $evaluation->scoring_version,
            'scoring_code' => $meta['scoring_code'] ?? null,
            'category_scores' => $categories,
            'configured_weights' => $meta['configured_weights'] ?? null,
            'event_impacts' => $meta['event_impacts'] ?? null,
            'overlap_skipped' => $meta['overlap_skipped'] ?? [],
            'event_count' => $meta['event_count'] ?? ($evaluation->relationLoaded('events') ? $evaluation->events->count() : null),
            'scored_event_count' => $meta['scored_event_count'] ?? null,
            'finalized_at' => $evaluation->finalized_at?->format('Y-m-d H:i:s'),
            'completed_at' => $evaluation->completed_at?->format('Y-m-d H:i:s'),
            'assignment' => $this->when(
                $evaluation->relationLoaded('assignment') && $evaluation->assignment !== null,
                fn () => [
                    'id' => $evaluation->assignment->id,
                    'assignment_code' => $evaluation->assignment->assignment_code,
                    'is_active' => (bool) $evaluation->assignment->is_active,
                ]
            ),
            'shipment' => $this->when(
                $evaluation->relationLoaded('shipment') && $evaluation->shipment !== null,
                fn () => [
                    'id' => $evaluation->shipment->id,
                    'shipment_number' => $evaluation->shipment->shipment_number,
                    'pickup_city' => $evaluation->shipment->pickup_city,
                    'delivery_city' => $evaluation->shipment->delivery_city,
                ]
            ),
            'events' => $this->when(
                $evaluation->relationLoaded('events'),
                fn () => AssignmentEvaluationEventResource::collection($evaluation->events)
            ),
        ];
    }
}
