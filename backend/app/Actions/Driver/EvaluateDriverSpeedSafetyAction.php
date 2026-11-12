<?php

namespace App\Actions\Driver;

use App\Models\Assignment;
use App\Models\DriverNotification;
use App\Models\TrackingLog;
use App\Support\DistanceHelper;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\DriverSpeedSafetyPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Confirm excessive-speed safety events from validated D6 telemetry (D14).
 * Never throws to ingest. Never mutates availability. Never trusts client alert flags.
 */
class EvaluateDriverSpeedSafetyAction
{
    public function execute(int $assignmentId, int $trackingLogId): void
    {
        try {
            if (! DriverSpeedSafetyPolicy::enabled()) {
                return;
            }

            $assignment = Assignment::query()
                ->with(['status'])
                ->find($assignmentId);

            if ($assignment === null || $assignment->driver_id === null || $assignment->shipment_id === null) {
                return;
            }

            $statusName = $assignment->status?->name;
            if (! $assignment->is_active || $statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                return;
            }

            $current = TrackingLog::query()->find($trackingLogId);
            if ($current === null || (string) $current->shipment_id !== (string) $assignment->shipment_id) {
                return;
            }

            if (! $this->isTrustworthy($current, $current)) {
                return;
            }

            $lookback = DriverSpeedSafetyPolicy::lookbackSeconds();
            $logs = TrackingLog::query()
                ->where('shipment_id', $assignment->shipment_id)
                ->where('recorded_at', '>=', Carbon::parse($current->recorded_at)->subSeconds($lookback))
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->limit(16)
                ->get();

            if (! $this->isConfirmedExcess($logs, $current)) {
                return;
            }

            $this->emitIfAllowed($assignment, $current);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  Collection<int, TrackingLog>  $logs  newest-first
     */
    private function isConfirmedExcess(Collection $logs, TrackingLog $current): bool
    {
        $threshold = DriverSpeedSafetyPolicy::thresholdKmh();
        $minSamples = DriverSpeedSafetyPolicy::minSamples();
        $minDuration = DriverSpeedSafetyPolicy::minDurationSeconds();

        $consecutive = [];
        $previous = null;

        foreach ($logs as $log) {
            if (! $this->isTrustworthy($log, $current)) {
                break;
            }

            if ($previous instanceof TrackingLog && $this->isImpossibleJump($previous, $log)) {
                break;
            }

            $speed = (float) $log->speed;
            if ($speed < $threshold) {
                break;
            }

            $consecutive[] = $log;
            $previous = $log;
        }

        if ($consecutive === [] || (int) $consecutive[0]->id !== (int) $current->id) {
            return false;
        }

        if (count($consecutive) < $minSamples) {
            return false;
        }

        $newest = Carbon::parse($consecutive[0]->recorded_at);
        $oldest = Carbon::parse($consecutive[count($consecutive) - 1]->recorded_at);

        // Carbon 3 returns a signed float; duration must be absolute.
        return abs($newest->diffInSeconds($oldest)) >= $minDuration;
    }

    private function isTrustworthy(TrackingLog $log, TrackingLog $anchor): bool
    {
        if ($log->speed === null || $log->accuracy === null || $log->recorded_at === null) {
            return false;
        }

        $speed = (float) $log->speed;
        $accuracy = (float) $log->accuracy;

        if ($speed < 0 || $speed > 300) {
            return false;
        }

        if ($accuracy < 0 || $accuracy > DriverSpeedSafetyPolicy::maxAccuracyMeters()) {
            return false;
        }

        $recorded = Carbon::parse($log->recorded_at);
        $received = $log->received_at
            ? Carbon::parse($log->received_at)
            : Carbon::parse($anchor->received_at ?? $anchor->recorded_at);

        if (abs($recorded->diffInSeconds($received)) > DriverSpeedSafetyPolicy::maxSampleAgeSeconds()) {
            return false;
        }

        return true;
    }

    private function isImpossibleJump(TrackingLog $newer, TrackingLog $older): bool
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

    private function emitIfAllowed(Assignment $assignment, TrackingLog $current): void
    {
        DB::transaction(function () use ($assignment, $current) {
            Assignment::query()->whereKey($assignment->id)->lockForUpdate()->first();

            $cooldown = DriverSpeedSafetyPolicy::cooldownSeconds();
            $last = DriverNotification::query()
                ->where('driver_id', $assignment->driver_id)
                ->where('assignment_id', $assignment->id)
                ->where('code', DriverNotificationCode::SPEED_EXCESS)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($last !== null && $last->created_at?->gt(now()->subSeconds($cooldown))) {
                return;
            }

            if ($last !== null && ! $this->hasRecoveredSince($assignment, $last->created_at)) {
                return;
            }

            // Unique suffix so D8 assignment-level dedupe cannot block a later episode.
            DriverNotificationWriter::forAssignment(
                $assignment,
                DriverNotificationCode::SPEED_EXCESS,
                'Speed safety alert',
                'Your reported speed has exceeded the configured safety threshold. This is not a road speed-limit check.',
                'warning',
                DriverNotificationAction::KIND_ACTIVE_TRIP,
                (string) $current->id
            );
        });
    }

    private function hasRecoveredSince(Assignment $assignment, ?Carbon $since): bool
    {
        if ($since === null) {
            return true;
        }

        $recovery = DriverSpeedSafetyPolicy::recoveryKmh();
        $logs = TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->where('recorded_at', '>', $since)
            ->orderBy('recorded_at')
            ->get();

        if ($logs->isEmpty()) {
            return true;
        }

        foreach ($logs as $log) {
            if ($log->speed === null) {
                continue;
            }
            if ((float) $log->speed <= $recovery) {
                return true;
            }
        }

        return false;
    }
}
