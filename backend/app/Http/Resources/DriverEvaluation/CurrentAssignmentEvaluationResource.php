<?php

declare(strict_types=1);

namespace App\Http\Resources\DriverEvaluation;

use App\Enums\AssignmentEvaluationStatus;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Current assignment evaluation read model (DRIVER-EVAL-08A).
 *
 * Distinct from overall driver_scores. Does not invent interim scores.
 * overall_score is null until a finalized persisted score exists.
 *
 * @property-read Assignment $assignment
 * @property-read AssignmentEvaluation|null $evaluation
 */
class CurrentAssignmentEvaluationResource extends JsonResource
{
    public const RECENT_EVENT_LIMIT = 20;

    /**
     * @param  array{assignment: Assignment, evaluation: AssignmentEvaluation|null}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{assignment: Assignment, evaluation: AssignmentEvaluation|null} $payload */
        $payload = $this->resource;
        $assignment = $payload['assignment'];
        $evaluation = $payload['evaluation'];

        if ($evaluation === null) {
            return [
                'assignment_id' => (int) $assignment->id,
                'assignment_code' => $assignment->assignment_code,
                'shipment_id' => $assignment->shipment_id !== null ? (int) $assignment->shipment_id : null,
                'driver_id' => $assignment->driver_id !== null ? (int) $assignment->driver_id : null,
                'vehicle_id' => $assignment->vehicle_id !== null ? (int) $assignment->vehicle_id : null,
                'evaluation_available' => false,
                'evaluation_id' => null,
                'revision' => null,
                'status' => null,
                'evaluation_mode' => null,
                'is_mutable' => false,
                'is_finalized' => false,
                'ui_state' => 'not_started',
                'overall_score' => null,
                'score_label' => null,
                'confidence' => null,
                'evidence_quality' => null,
                'scoring_version' => null,
                'category_scores' => null,
                'score_available' => false,
                'event_count' => 0,
                'recent_events' => [],
                'started_at' => null,
                'completed_at' => null,
                'finalized_at' => null,
            ];
        }

        $events = $evaluation->relationLoaded('events')
            ? $evaluation->events
            : collect();

        $recent = $events
            ->sortByDesc(fn ($e) => $e->occurred_at?->timestamp ?? 0)
            ->take(self::RECENT_EVENT_LIMIT)
            ->values();

        $score = $evaluation->overall_score !== null ? (float) $evaluation->overall_score : null;
        $finalized = $evaluation->isFinalized();
        $mutable = $evaluation->isMutable();

        return [
            'assignment_id' => (int) $evaluation->assignment_id,
            'assignment_code' => $assignment->assignment_code,
            'shipment_id' => $evaluation->shipment_id !== null ? (int) $evaluation->shipment_id : null,
            'driver_id' => (int) $evaluation->driver_id,
            'vehicle_id' => $evaluation->vehicle_id !== null ? (int) $evaluation->vehicle_id : null,
            'evaluation_available' => true,
            'evaluation_id' => (int) $evaluation->id,
            'revision' => (int) $evaluation->revision,
            'status' => $evaluation->status?->value,
            'evaluation_mode' => $evaluation->evaluation_mode?->value,
            'is_mutable' => $mutable,
            'is_finalized' => $finalized,
            'ui_state' => $this->uiState($evaluation),
            // Never coerce null → 0. Mid-trip mutable rows have null score by design.
            'overall_score' => $finalized ? $score : null,
            'score_label' => $finalized ? $evaluation->score_label : null,
            'confidence' => $finalized ? $evaluation->confidence?->value : null,
            'evidence_quality' => $evaluation->evidence_quality?->value,
            'scoring_version' => $finalized ? $evaluation->scoring_version : null,
            'category_scores' => $finalized ? $evaluation->category_scores : null,
            'score_available' => $finalized && $score !== null,
            'event_count' => $events->count(),
            'recent_events' => AssignmentEvaluationEventResource::collection($recent)->resolve(),
            'started_at' => $evaluation->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $evaluation->completed_at?->format('Y-m-d H:i:s'),
            'finalized_at' => $evaluation->finalized_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function uiState(AssignmentEvaluation $evaluation): string
    {
        return match ($evaluation->status) {
            AssignmentEvaluationStatus::PENDING,
            AssignmentEvaluationStatus::PROCESSING => 'evaluating',
            AssignmentEvaluationStatus::COMPLETED => 'completed',
            AssignmentEvaluationStatus::INCOMPLETE => 'incomplete',
            AssignmentEvaluationStatus::INVALIDATED => 'unavailable',
            default => 'not_started',
        };
    }
}
