<?php

declare(strict_types=1);

namespace App\Http\Resources\DriverEvaluation;

use App\Models\AssignmentEvaluationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explainable evaluation event (DRIVER-EVAL-06). No raw telemetry streams.
 *
 * @mixin AssignmentEvaluationEvent
 */
class AssignmentEvaluationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssignmentEvaluationEvent $event */
        $event = $this->resource;
        $logIds = is_array($event->evidence_tracking_log_ids) ? $event->evidence_tracking_log_ids : [];

        return [
            'id' => (int) $event->id,
            'event_code' => $event->event_code?->value,
            'category' => $event->category?->value,
            'severity' => $event->severity?->value,
            'occurred_at' => $event->occurred_at?->format('Y-m-d H:i:s'),
            'window_start_at' => $event->window_start_at?->format('Y-m-d H:i:s'),
            'window_end_at' => $event->window_end_at?->format('Y-m-d H:i:s'),
            'evidence_source' => $event->evidence_source?->value,
            'detector_code' => $event->detector_code,
            'detector_version' => $event->detector_version,
            'evidence_tracking_log_id_count' => count($logIds),
            'evidence_tracking_log_ids' => array_values(array_map('intval', $logIds)),
            'evidence_metadata' => $this->safeMetadata($event->evidence_metadata),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function safeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        // Drop any nested raw coordinate streams if present.
        unset($metadata['raw_points'], $metadata['telemetry_samples'], $metadata['gps_stream']);

        return $metadata;
    }
}
