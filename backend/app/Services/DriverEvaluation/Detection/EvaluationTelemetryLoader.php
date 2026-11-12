<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\AssignmentEvaluationMode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\TrackingLog;
use App\Services\DriverEvaluation\AssignmentTelemetryAttributionService;
use App\Support\DistanceHelper;
use App\Support\DriverMotionSafetyPolicy;
use App\Support\DriverSpeedSafetyPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Loads and quality-filters assignment-window telemetry for evaluation (DRIVER-EVAL-03).
 */
class EvaluationTelemetryLoader
{
    public function __construct(
        private readonly AssignmentTelemetryAttributionService $attribution,
    ) {}

    /**
     * Chronological live/sim-filtered samples owned by this assignment's time window.
     *
     * @return Collection<int, TrackingLog>
     */
    public function loadForAssignment(Assignment $assignment, AssignmentEvaluationMode $mode): Collection
    {
        if ($assignment->shipment_id === null || $assignment->assigned_at === null) {
            return collect();
        }

        $from = Carbon::parse($assignment->assigned_at);
        $to = $assignment->ended_at !== null
            ? Carbon::parse($assignment->ended_at)
            : now();

        $logs = TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $to)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        return $logs
            ->filter(function (TrackingLog $log) use ($assignment, $mode) {
                $source = $this->attribution->classifyEvidenceSource($log);
                if (! $this->attribution->sourceAllowedForMode($source, $mode->value)) {
                    return false;
                }

                $attr = $this->attribution->attributeTrackingLog($log);
                if (! $attr->isAttributed()) {
                    return false;
                }

                return (int) $attr->assignment->id === (int) $assignment->id;
            })
            ->values();
    }

    public function isMotionTrustworthy(TrackingLog $log): bool
    {
        $isLive = DriverMotionSafetyPolicy::isLiveDriverEvidence($log->received_at);

        if (! DriverMotionSafetyPolicy::hasUsableSpeed($log->speed)) {
            return false;
        }

        if ($log->recorded_at === null) {
            return false;
        }

        $speed = (float) $log->speed;
        if ($speed < 0 || $speed > 300) {
            return false;
        }

        if ($log->accuracy !== null) {
            $accuracy = (float) $log->accuracy;
            if ($accuracy < 0 || $accuracy > DriverMotionSafetyPolicy::maxAccuracyMeters()) {
                return false;
            }
        }

        if ($isLive) {
            $recorded = Carbon::parse($log->recorded_at);
            $received = Carbon::parse($log->received_at);
            if (abs($recorded->diffInSeconds($received)) > DriverMotionSafetyPolicy::maxSampleAgeSeconds()) {
                return false;
            }
        }

        return true;
    }

    public function isSpeedTrustworthy(TrackingLog $log): bool
    {
        if ($log->speed === null || $log->recorded_at === null) {
            return false;
        }

        $speed = (float) $log->speed;
        if (! is_finite($speed) || $speed < 0 || $speed > DriverSpeedSafetyPolicy::maxPlausibleKmh()) {
            return false;
        }

        if ($log->accuracy !== null) {
            $accuracy = (float) $log->accuracy;
            if ($accuracy < 0 || $accuracy > DriverSpeedSafetyPolicy::maxAccuracyMeters()) {
                return false;
            }
        }

        if ($log->received_at !== null) {
            $recorded = Carbon::parse($log->recorded_at);
            $received = Carbon::parse($log->received_at);
            if (abs($recorded->diffInSeconds($received)) > DriverSpeedSafetyPolicy::maxSampleAgeSeconds()) {
                return false;
            }
        }

        return true;
    }

    public function isImpossibleJump(TrackingLog $newer, TrackingLog $older): bool
    {
        if ($newer->recorded_at === null || $older->recorded_at === null) {
            return true;
        }

        $dt = abs(Carbon::parse($newer->recorded_at)->diffInSeconds(Carbon::parse($older->recorded_at)));
        if ($dt < 1) {
            return false;
        }

        $km = DistanceHelper::calculateDistance(
            (float) $older->latitude,
            (float) $older->longitude,
            (float) $newer->latitude,
            (float) $newer->longitude
        );
        $derivedKmh = $km * 3600 / $dt;

        return $derivedKmh > DriverSpeedSafetyPolicy::maxPlausibleKmh();
    }

    public function evidenceSource(TrackingLog $log): DriverEvaluationEvidenceSource
    {
        return $this->attribution->classifyEvidenceSource($log);
    }
}
