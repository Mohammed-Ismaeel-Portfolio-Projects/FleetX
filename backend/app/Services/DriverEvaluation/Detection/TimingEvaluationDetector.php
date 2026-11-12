<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\Shipment;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Support\DriverAssignmentTransitionMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Timing evidence from Assignment + Shipment planned/actual dates and planned-route ETA.
 * Date-only planned fields use calendar-day comparisons (documented precision limit).
 * Does not query the legacy routes table (UUID/bigint mismatch).
 */
class TimingEvaluationDetector implements EvaluationEventDetector
{
    public function __construct(
        private readonly PlannedRouteCache $plannedRouteCache,
    ) {}

    public function detect(Assignment $assignment, AssignmentEvaluation $evaluation, Collection $logs): array
    {
        unset($logs);

        $assignment->loadMissing(['shipment', 'status']);
        $shipment = $assignment->shipment;
        if (! $shipment instanceof Shipment) {
            return [];
        }

        $meta = config('driver_evaluation.detectors.timing');
        $candidates = [];

        $lateDeparture = $this->lateDeparture($assignment, $shipment, $meta);
        if ($lateDeparture !== null) {
            $candidates[] = $lateDeparture;
        }

        $lateArrival = $this->lateArrival($assignment, $shipment, $meta);
        if ($lateArrival !== null) {
            $candidates[] = $lateArrival;
        }

        $abnormal = $this->abnormalDuration($assignment, $shipment, $meta);
        if ($abnormal !== null) {
            $candidates[] = $abnormal;
        }

        return $candidates;
    }

    /**
     * @param  array{code: string, version: string}  $meta
     */
    private function lateDeparture(Assignment $assignment, Shipment $shipment, array $meta): ?DetectedEvaluationCandidate
    {
        if ($shipment->planned_pickup_date === null || $assignment->assigned_at === null) {
            return null;
        }

        $planned = Carbon::parse($shipment->planned_pickup_date)->startOfDay();
        $actual = Carbon::parse($assignment->assigned_at)->startOfDay();
        if ($actual->lte($planned)) {
            return null;
        }

        return new DetectedEvaluationCandidate(
            eventCode: DriverEvaluationEventCode::LATE_DEPARTURE,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: $meta['code'],
            detectorVersion: $meta['version'],
            occurredAt: Carbon::parse($assignment->assigned_at),
            trackingLogIds: [],
            evidenceMetadata: [
                'planned_pickup_date' => $planned->toDateString(),
                'actual_start_at' => Carbon::parse($assignment->assigned_at)->toIso8601String(),
                'precision' => 'date_only_planned',
                'days_late' => $planned->diffInDays($actual),
            ],
            windowStart: $planned,
            windowEnd: Carbon::parse($assignment->assigned_at),
        );
    }

    /**
     * @param  array{code: string, version: string}  $meta
     */
    private function lateArrival(Assignment $assignment, Shipment $shipment, array $meta): ?DetectedEvaluationCandidate
    {
        if ($shipment->planned_delivery_date === null) {
            return null;
        }

        $actualDate = $shipment->actual_delivery_date;
        $occurredAt = null;
        if ($actualDate !== null) {
            $occurredAt = Carbon::parse($actualDate)->endOfDay();
        } elseif (
            $assignment->ended_at !== null
            && $assignment->status?->name === DriverAssignmentTransitionMap::COMPLETED
        ) {
            $occurredAt = Carbon::parse($assignment->ended_at);
            $actualDate = Carbon::parse($assignment->ended_at)->toDateString();
        }

        if ($occurredAt === null || $actualDate === null) {
            return null;
        }

        $planned = Carbon::parse($shipment->planned_delivery_date)->startOfDay();
        $actual = Carbon::parse($actualDate)->startOfDay();
        if ($actual->lte($planned)) {
            return null;
        }

        return new DetectedEvaluationCandidate(
            eventCode: DriverEvaluationEventCode::LATE_ARRIVAL,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: $meta['code'],
            detectorVersion: $meta['version'],
            occurredAt: $occurredAt,
            trackingLogIds: [],
            evidenceMetadata: [
                'planned_delivery_date' => $planned->toDateString(),
                'actual_delivery_date' => Carbon::parse($actualDate)->toDateString(),
                'precision' => 'date_only',
                'days_late' => $planned->diffInDays($actual),
            ],
            windowStart: $planned,
            windowEnd: $occurredAt,
        );
    }

    /**
     * @param  array{code: string, version: string}  $meta
     */
    private function abnormalDuration(Assignment $assignment, Shipment $shipment, array $meta): ?DetectedEvaluationCandidate
    {
        if ($assignment->assigned_at === null || $assignment->ended_at === null) {
            return null;
        }

        if ($assignment->status?->name !== DriverAssignmentTransitionMap::COMPLETED) {
            return null;
        }

        $estimatedSeconds = $this->resolveEstimatedDurationSeconds((string) $shipment->id);
        if ($estimatedSeconds === null || $estimatedSeconds <= 0) {
            return null;
        }

        $actualSeconds = abs(Carbon::parse($assignment->ended_at)->diffInSeconds(Carbon::parse($assignment->assigned_at)));
        $ratio = (float) config('driver_evaluation.timing.abnormal_duration_ratio', 1.5);
        if ($actualSeconds <= (int) ceil($estimatedSeconds * $ratio)) {
            return null;
        }

        return new DetectedEvaluationCandidate(
            eventCode: DriverEvaluationEventCode::ABNORMAL_DURATION,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: $meta['code'],
            detectorVersion: $meta['version'],
            occurredAt: Carbon::parse($assignment->ended_at),
            trackingLogIds: [],
            evidenceMetadata: [
                'actual_duration_seconds' => $actualSeconds,
                'estimated_duration_seconds' => $estimatedSeconds,
                'estimated_source' => 'planned_route_cache',
                'abnormal_ratio_threshold' => $ratio,
                'observed_ratio' => round($actualSeconds / max(1, $estimatedSeconds), 4),
            ],
            windowStart: Carbon::parse($assignment->assigned_at),
            windowEnd: Carbon::parse($assignment->ended_at),
        );
    }

    private function resolveEstimatedDurationSeconds(string $shipmentId): ?int
    {
        $cached = $this->plannedRouteCache->get($shipmentId);
        if (! is_array($cached)) {
            return null;
        }

        $duration = $cached['api_payload']['route']['duration'] ?? null;
        if (is_array($duration) && isset($duration['seconds']) && is_numeric($duration['seconds'])) {
            return (int) $duration['seconds'];
        }
        if (is_numeric($duration)) {
            return (int) $duration;
        }

        return null;
    }
}
