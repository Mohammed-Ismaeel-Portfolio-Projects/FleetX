<?php

namespace App\Actions\Driver;

use App\Models\Assignment;
use App\Models\DriverNotification;
use App\Models\TrackingLog;
use App\Support\DistanceHelper;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverMotionKinematics;
use App\Support\DriverMotionSafetyPolicy;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\DriverSpeedSafetyPolicy;
use App\Support\ObservedLongitudinalMotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Confirm harsh-braking and sudden-stop events from validated D6 telemetry (D15-C).
 *
 * Observes live TrackingLog.speed only. Never generates motion. Never invokes the
 * simulation dynamics tick, tracking simulator, environment, driver-intelligence,
 * vehicle-health engines, or simulation telemetry caches.
 *
 * Live thresholds are freight-class operational gates (van 2.5 / light 2.0 / heavy 1.5 m/s²).
 * They are NOT simulation VehicleConstraints 60-second brake ramps and NOT legal standards.
 *
 * Never throws to ingest. Never mutates availability. Never trusts client event flags.
 */
class EvaluateDriverMotionSafetyAction
{
    public function execute(int $assignmentId, int $trackingLogId): void
    {
        try {
            if (! DriverMotionSafetyPolicy::enabled()) {
                return;
            }

            $assignment = Assignment::query()
                ->with(['status', 'vehicle', 'trailer'])
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

            // Simulation TrackingLog rows omit received_at and must never confirm a Driver event.
            if (! $this->isTrustworthy($current)) {
                return;
            }

            $lookback = DriverMotionSafetyPolicy::lookbackSeconds();
            $logs = TrackingLog::query()
                ->where('shipment_id', $assignment->shipment_id)
                ->whereNotNull('received_at')
                ->where('recorded_at', '>=', Carbon::parse($current->recorded_at)->subSeconds($lookback))
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->limit(16)
                ->get();

            $freightClass = DriverMotionSafetyPolicy::freightClassForAssignment($assignment);

            if ($this->isConfirmedHarshBrake($logs, $current, $freightClass)) {
                $this->emitIfAllowed(
                    $assignment,
                    $current,
                    DriverNotificationCode::HARSH_BRAKING,
                    'Sharp slowdown detected',
                    'A sharp slowdown was detected from your reported speed. Ease off when it is safe. This is an operational notice, not a legal finding.'
                );
            }

            if ($this->isConfirmedSuddenStop($logs, $current)) {
                $this->emitIfAllowed(
                    $assignment,
                    $current,
                    DriverNotificationCode::SUDDEN_STOP,
                    'Rapid stop detected',
                    'A rapid stop was detected from your reported speed. Continue only when it is safe. This is an operational notice, not a legal finding.'
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Mean |a_obs| over a confirmed decelerating window >= freight-class threshold.
     * One noisy drop / one pair / unusable Δt / |a| > 8 m/s² cannot confirm.
     *
     * @param  Collection<int, TrackingLog>  $logs  newest-first, live Driver rows only
     */
    private function isConfirmedHarshBrake(Collection $logs, TrackingLog $current, string $freightClass): bool
    {
        $window = $this->confirmedDecelerationWindow($logs, $current);
        if ($window === null) {
            return false;
        }

        /** @var list<ObservedLongitudinalMotion> $observations */
        $observations = $window['observations'];
        $magnitudes = array_map(
            static fn (ObservedLongitudinalMotion $obs): float => $obs->decelerationMagnitudeMs2(),
            $observations
        );
        $mean = array_sum($magnitudes) / count($magnitudes);
        $threshold = DriverMotionSafetyPolicy::harshBrakeThresholdMs2($freightClass);

        // Operational detection (>=). Not a legal brake standard. Not sim 0.15 m/s².
        return $mean >= $threshold;
    }

    /**
     * Moving-band → stopped-band with required drop, confirmation, and jump reject.
     * A single speed==0 sample must not confirm. GPS teleports must not confirm.
     *
     * @param  Collection<int, TrackingLog>  $logs  newest-first, live Driver rows only
     */
    private function isConfirmedSuddenStop(Collection $logs, TrackingLog $current): bool
    {
        $stoppedMax = DriverMotionSafetyPolicy::suddenStopStoppedMaxKmh();
        $movingMin = DriverMotionSafetyPolicy::suddenStopMovingMinKmh();
        $minDrop = DriverMotionSafetyPolicy::suddenStopMinSpeedDropKmh();

        if ((float) $current->speed > $stoppedMax) {
            return false;
        }

        $chain = $this->confirmedLiveChain($logs, $current, requireDeceleration: false);
        if ($chain === null) {
            return false;
        }

        /** @var list<TrackingLog> $samples */
        $samples = $chain['samples'];
        $movingSpeed = null;
        foreach ($samples as $sample) {
            $speed = (float) $sample->speed;
            if ($speed >= $movingMin) {
                $movingSpeed = $movingSpeed === null ? $speed : max($movingSpeed, $speed);
            }
        }

        if ($movingSpeed === null) {
            return false;
        }

        $drop = $movingSpeed - (float) $current->speed;

        return $drop >= $minDrop;
    }

    /**
     * Consecutive live samples that are all decelerating, with usable Δt and plausible |a|.
     *
     * @param  Collection<int, TrackingLog>  $logs
     * @return array{samples: list<TrackingLog>, observations: list<ObservedLongitudinalMotion>}|null
     */
    private function confirmedDecelerationWindow(Collection $logs, TrackingLog $current): ?array
    {
        return $this->confirmedLiveChain($logs, $current, requireDeceleration: true);
    }

    /**
     * Walk newest-first until trust, jump, Δt, or (optional) deceleration fails.
     *
     * @param  Collection<int, TrackingLog>  $logs
     * @return array{samples: list<TrackingLog>, observations: list<ObservedLongitudinalMotion>}|null
     */
    private function confirmedLiveChain(Collection $logs, TrackingLog $current, bool $requireDeceleration): ?array
    {
        $minSamples = DriverMotionSafetyPolicy::minSamples();
        $minDuration = DriverMotionSafetyPolicy::minDurationSeconds();

        $samples = [];
        $observations = [];
        $previous = null;

        foreach ($logs as $log) {
            if (! $this->isTrustworthy($log)) {
                break;
            }

            if ($previous instanceof TrackingLog && $this->isImpossibleJump($previous, $log)) {
                break;
            }

            if ($previous instanceof TrackingLog) {
                $dt = DriverMotionKinematics::elapsedSeconds($log->recorded_at, $previous->recorded_at);
                if (! DriverMotionSafetyPolicy::isUsableInterval($dt)) {
                    break;
                }

                $observed = DriverMotionKinematics::observe(
                    (float) $log->speed,
                    (float) $previous->speed,
                    $dt
                );
                if ($observed === null) {
                    break;
                }

                // |a| above 8 m/s² is treated as GPS teleport, not vehicle motion.
                if (abs($observed->accelerationMs2) > DriverMotionSafetyPolicy::maxPlausibleDecelerationMs2()) {
                    break;
                }

                if ($requireDeceleration && ! $observed->isDeceleration()) {
                    break;
                }

                $observations[] = $observed;
            }

            $samples[] = $log;
            $previous = $log;
        }

        if ($samples === [] || (int) $samples[0]->id !== (int) $current->id) {
            return null;
        }

        if (count($samples) < $minSamples) {
            return null;
        }

        $newest = Carbon::parse($samples[0]->recorded_at);
        $oldest = Carbon::parse($samples[count($samples) - 1]->recorded_at);

        if (abs($newest->diffInSeconds($oldest)) < $minDuration) {
            return null;
        }

        if ($requireDeceleration && $observations === []) {
            return null;
        }

        return [
            'samples' => $samples,
            'observations' => $observations,
        ];
    }

    /**
     * Live Driver evidence only: this log's received_at, usable speed, accuracy, age.
     * Do not fall back to another sample's received_at (that would let simulation rows ride along).
     */
    private function isTrustworthy(TrackingLog $log): bool
    {
        if (! DriverMotionSafetyPolicy::isLiveDriverEvidence($log->received_at)) {
            return false;
        }

        if (! DriverMotionSafetyPolicy::hasUsableSpeed($log->speed)) {
            return false;
        }

        if ($log->accuracy === null || $log->recorded_at === null) {
            return false;
        }

        $speed = (float) $log->speed;
        $accuracy = (float) $log->accuracy;

        if ($speed < 0 || $speed > 300) {
            return false;
        }

        if ($accuracy < 0 || $accuracy > DriverMotionSafetyPolicy::maxAccuracyMeters()) {
            return false;
        }

        $recorded = Carbon::parse($log->recorded_at);
        $received = Carbon::parse($log->received_at);

        if (abs($recorded->diffInSeconds($received)) > DriverMotionSafetyPolicy::maxSampleAgeSeconds()) {
            return false;
        }

        return true;
    }

    /**
     * D14-style haversine jump reject. A GPS teleport must not confirm braking or a stop.
     */
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

    private function emitIfAllowed(
        Assignment $assignment,
        TrackingLog $current,
        string $code,
        string $title,
        string $body
    ): void {
        DB::transaction(function () use ($assignment, $current, $code, $title, $body) {
            Assignment::query()->whereKey($assignment->id)->lockForUpdate()->first();

            $cooldown = DriverMotionSafetyPolicy::cooldownSeconds();
            $last = DriverNotification::query()
                ->where('driver_id', $assignment->driver_id)
                ->where('assignment_id', $assignment->id)
                ->where('code', $code)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($last !== null && $last->created_at?->gt(now()->subSeconds($cooldown))) {
                return;
            }

            if ($last !== null && ! $this->hasRecoveredSince($assignment, $last->created_at, $code)) {
                return;
            }

            DriverNotificationWriter::forAssignment(
                $assignment,
                $code,
                $title,
                $body,
                'warning',
                DriverNotificationAction::KIND_ACTIVE_TRIP,
                (string) $current->id
            );
        });
    }

    private function hasRecoveredSince(Assignment $assignment, ?Carbon $since, string $code): bool
    {
        if ($since === null) {
            return true;
        }

        $later = TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->whereNotNull('received_at')
            ->where('recorded_at', '>', $since)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        if ($later->isEmpty()) {
            return true;
        }

        if ($code === DriverNotificationCode::SUDDEN_STOP) {
            $movingMin = DriverMotionSafetyPolicy::suddenStopMovingMinKmh();
            foreach ($later as $log) {
                if (! $this->isTrustworthy($log)) {
                    continue;
                }
                if ((float) $log->speed >= $movingMin) {
                    return true;
                }
            }

            return false;
        }

        $anchor = TrackingLog::query()
            ->where('shipment_id', $assignment->shipment_id)
            ->whereNotNull('received_at')
            ->where('recorded_at', '<=', $since)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        $previous = $anchor;
        $recovery = DriverMotionSafetyPolicy::recoveryDecelerationMs2();

        foreach ($later as $log) {
            if (! $this->isTrustworthy($log)) {
                continue;
            }

            if ($previous instanceof TrackingLog && $this->isTrustworthy($previous)) {
                $dt = DriverMotionKinematics::elapsedSeconds($previous->recorded_at, $log->recorded_at);
                if (DriverMotionSafetyPolicy::isUsableInterval($dt)) {
                    $observed = DriverMotionKinematics::observe(
                        (float) $previous->speed,
                        (float) $log->speed,
                        $dt
                    );
                    if ($observed !== null && $observed->decelerationMagnitudeMs2() <= $recovery) {
                        return true;
                    }
                }
            }

            $previous = $log;
        }

        return false;
    }
}
