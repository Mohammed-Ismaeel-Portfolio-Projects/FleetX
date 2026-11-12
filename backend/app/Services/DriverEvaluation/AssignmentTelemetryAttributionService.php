<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation;

use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\TrackingLog;
use App\Services\DriverEvaluation\DTO\TelemetryAttributionResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Deterministic historical driver attribution for evaluation (DRIVER-EVAL-02).
 *
 * NEVER uses TrackingLog::activeAssignment() / shipment.activeAssignment().
 *
 * Contract:
 *   TrackingLog.shipment_id + TrackingLog.recorded_at
 *     → Assignments for same shipment where
 *         assigned_at <= recorded_at
 *         AND (ended_at IS NULL OR recorded_at <= ended_at)
 *     → exactly one match required
 */
class AssignmentTelemetryAttributionService
{
    public function classifyEvidenceSource(TrackingLog $log): DriverEvaluationEvidenceSource
    {
        // Align with EvaluateDriverMotionSafetyAction::isTrustworthy (received_at required for live).
        if ($log->received_at !== null) {
            return DriverEvaluationEvidenceSource::LIVE;
        }

        return DriverEvaluationEvidenceSource::SIMULATION;
    }

    public function attributeTrackingLog(TrackingLog $log): TelemetryAttributionResult
    {
        if ($log->shipment_id === null || $log->recorded_at === null) {
            return TelemetryAttributionResult::insufficient(
                'Tracking log is missing shipment_id or recorded_at.'
            );
        }

        return $this->attributeShipmentAt(
            (string) $log->shipment_id,
            Carbon::parse($log->recorded_at),
        );
    }

    public function attributeShipmentAt(string $shipmentId, CarbonInterface $at): TelemetryAttributionResult
    {
        // Include soft-deleted assignments so historical evaluation survives soft deletes.
        $matches = Assignment::withTrashed()
            ->where('shipment_id', $shipmentId)
            ->whereNotNull('driver_id')
            ->whereNotNull('vehicle_id')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', $at)
            ->where(function ($query) use ($at) {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $at);
            })
            ->orderBy('id')
            ->get();

        $count = $matches->count();

        if ($count === 0) {
            return TelemetryAttributionResult::unmatched(
                'No assignment ownership window covers this telemetry timestamp.'
            );
        }

        if ($count > 1) {
            return TelemetryAttributionResult::ambiguous(
                'Multiple overlapping assignment ownership windows match this telemetry timestamp.',
                $count,
            );
        }

        /** @var Assignment $assignment */
        $assignment = $matches->first();

        return TelemetryAttributionResult::attributed($assignment);
    }

    /**
     * Whether evidence source is allowed for the given evaluation mode.
     */
    public function sourceAllowedForMode(
        DriverEvaluationEvidenceSource $source,
        string $evaluationMode,
    ): bool {
        return match ($evaluationMode) {
            'live' => $source === DriverEvaluationEvidenceSource::LIVE,
            'simulation' => $source === DriverEvaluationEvidenceSource::SIMULATION,
            'mixed' => in_array($source, [
                DriverEvaluationEvidenceSource::LIVE,
                DriverEvaluationEvidenceSource::SIMULATION,
            ], true),
            default => false,
        };
    }
}
