<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\TrackingLog;
use App\Support\DriverMotionKinematics;
use App\Support\DriverMotionSafetyPolicy;
use App\Support\DriverSpeedSafetyPolicy;
use App\Support\ObservedLongitudinalMotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Motion/speed evidence analyzers reusing D14/D15 policies + DriverMotionKinematics.
 * Does not emit notifications — evaluation persistence only.
 */
class MotionSpeedEvidenceAnalyzer
{
    public function __construct(
        private readonly EvaluationTelemetryLoader $loader,
    ) {}

    /**
     * @param  Collection<int, TrackingLog>  $logs  chronological
     * @return list<DetectedEvaluationCandidate>
     */
    public function detectSpeeding(Collection $logs, AssignmentEvaluation $evaluation): array
    {
        if (! DriverSpeedSafetyPolicy::enabled()) {
            return [];
        }

        $threshold = DriverSpeedSafetyPolicy::thresholdKmh();
        $minSamples = DriverSpeedSafetyPolicy::minSamples();
        $minDuration = DriverSpeedSafetyPolicy::minDurationSeconds();
        $meta = config('driver_evaluation.detectors.speeding');

        $candidates = [];
        $i = 0;
        $count = $logs->count();

        while ($i < $count) {
            /** @var TrackingLog $start */
            $start = $logs[$i];
            if (! $this->loader->isSpeedTrustworthy($start) || (float) $start->speed < $threshold) {
                $i++;
                continue;
            }

            $window = [$start];
            $j = $i + 1;
            $previous = $start;

            while ($j < $count) {
                /** @var TrackingLog $next */
                $next = $logs[$j];
                if (! $this->loader->isSpeedTrustworthy($next)) {
                    break;
                }
                if ($this->loader->isImpossibleJump($next, $previous)) {
                    break;
                }
                if ((float) $next->speed < $threshold) {
                    break;
                }
                $window[] = $next;
                $previous = $next;
                $j++;
            }

            if (count($window) >= $minSamples) {
                $oldest = Carbon::parse($window[0]->recorded_at);
                $newest = Carbon::parse($window[count($window) - 1]->recorded_at);
                if (abs($newest->diffInSeconds($oldest)) >= $minDuration) {
                    $peak = max(array_map(static fn (TrackingLog $l) => (float) $l->speed, $window));
                    $ids = array_map(static fn (TrackingLog $l) => (int) $l->id, $window);
                    $candidates[] = new DetectedEvaluationCandidate(
                        eventCode: DriverEvaluationEventCode::SPEEDING,
                        evidenceSource: $this->loader->evidenceSource($window[count($window) - 1]),
                        detectorCode: $meta['code'],
                        detectorVersion: $meta['version'],
                        occurredAt: $newest,
                        trackingLogIds: $ids,
                        evidenceMetadata: [
                            'peak_speed_kmh' => $peak,
                            'threshold_kmh' => $threshold,
                            'sample_count' => count($window),
                            'duration_seconds' => abs($newest->diffInSeconds($oldest)),
                        ],
                        windowStart: $oldest,
                        windowEnd: $newest,
                    );
                }
            }

            $i = max($i + 1, $j);
        }

        return $this->dedupeByLastSample($candidates);
    }

    /**
     * @param  Collection<int, TrackingLog>  $logs  chronological
     * @return list<DetectedEvaluationCandidate>
     */
    public function detectHarshBraking(Collection $logs, Assignment $assignment): array
    {
        return $this->detectLongitudinalEvents(
            $logs,
            $assignment,
            requireDeceleration: true,
            eventCode: DriverEvaluationEventCode::HARSH_BRAKING,
            thresholdResolver: fn (string $class) => DriverMotionSafetyPolicy::harshBrakeThresholdMs2($class),
            magnitudePicker: static fn (ObservedLongitudinalMotion $o) => $o->decelerationMagnitudeMs2(),
            requireMeanAboveThreshold: true,
        );
    }

    /**
     * @param  Collection<int, TrackingLog>  $logs
     * @return list<DetectedEvaluationCandidate>
     */
    public function detectHarshAcceleration(Collection $logs, Assignment $assignment): array
    {
        return $this->detectLongitudinalEvents(
            $logs,
            $assignment,
            requireDeceleration: false,
            eventCode: DriverEvaluationEventCode::HARSH_ACCELERATION,
            thresholdResolver: function (string $class) {
                $configured = config('driver_evaluation.harsh_acceleration.ms2.'.$class);
                if ($configured !== null) {
                    return max(1.0, (float) $configured);
                }

                return DriverMotionSafetyPolicy::harshBrakeThresholdMs2($class);
            },
            magnitudePicker: static fn (ObservedLongitudinalMotion $o) => $o->isAcceleration() ? abs($o->accelerationMs2) : 0.0,
            requireMeanAboveThreshold: true,
            requireAcceleration: true,
        );
    }

    /**
     * @param  Collection<int, TrackingLog>  $logs
     * @return list<DetectedEvaluationCandidate>
     */
    public function detectSuddenStops(Collection $logs): array
    {
        if (! DriverMotionSafetyPolicy::enabled()) {
            return [];
        }

        $stoppedMax = DriverMotionSafetyPolicy::suddenStopStoppedMaxKmh();
        $movingMin = DriverMotionSafetyPolicy::suddenStopMovingMinKmh();
        $minDrop = DriverMotionSafetyPolicy::suddenStopMinSpeedDropKmh();
        $minSamples = DriverMotionSafetyPolicy::minSamples();
        $minDuration = DriverMotionSafetyPolicy::minDurationSeconds();
        $meta = config('driver_evaluation.detectors.motion');

        $candidates = [];
        $indexed = $logs->values();

        for ($i = $minSamples - 1; $i < $indexed->count(); $i++) {
            /** @var TrackingLog $current */
            $current = $indexed[$i];
            if (! $this->loader->isMotionTrustworthy($current)) {
                continue;
            }
            if ((float) $current->speed > $stoppedMax) {
                continue;
            }

            $windowLogs = $indexed->slice(max(0, $i - 15), $i + 1)->values()->reverse()->values();
            $chain = $this->confirmedChain($windowLogs, $current, requireDeceleration: false, requireAcceleration: false);
            if ($chain === null) {
                continue;
            }

            $movingSpeed = null;
            foreach ($chain['samples'] as $sample) {
                $speed = (float) $sample->speed;
                if ($speed >= $movingMin) {
                    $movingSpeed = $movingSpeed === null ? $speed : max($movingSpeed, $speed);
                }
            }

            if ($movingSpeed === null) {
                continue;
            }

            $drop = $movingSpeed - (float) $current->speed;
            if ($drop < $minDrop) {
                continue;
            }

            /** @var list<TrackingLog> $samples */
            $samples = $chain['samples'];
            $newest = Carbon::parse($samples[0]->recorded_at);
            $oldest = Carbon::parse($samples[count($samples) - 1]->recorded_at);
            if (abs($newest->diffInSeconds($oldest)) < $minDuration) {
                continue;
            }

            $ids = array_map(static fn (TrackingLog $l) => (int) $l->id, array_reverse($samples));
            $candidates[] = new DetectedEvaluationCandidate(
                eventCode: DriverEvaluationEventCode::SUDDEN_STOP,
                evidenceSource: $this->loader->evidenceSource($current),
                detectorCode: $meta['code'],
                detectorVersion: $meta['version'],
                occurredAt: $newest,
                trackingLogIds: $ids,
                evidenceMetadata: [
                    'moving_speed_kmh' => $movingSpeed,
                    'final_speed_kmh' => (float) $current->speed,
                    'speed_drop_kmh' => $drop,
                    'moving_min_kmh' => $movingMin,
                    'stopped_max_kmh' => $stoppedMax,
                    'min_drop_kmh' => $minDrop,
                ],
                windowStart: $oldest,
                windowEnd: $newest,
            );
        }

        return $this->dedupeByLastSample($candidates);
    }

    /**
     * @param  Collection<int, TrackingLog>  $logs
     * @param  callable(string): float  $thresholdResolver
     * @param  callable(ObservedLongitudinalMotion): float  $magnitudePicker
     * @return list<DetectedEvaluationCandidate>
     */
    private function detectLongitudinalEvents(
        Collection $logs,
        Assignment $assignment,
        bool $requireDeceleration,
        DriverEvaluationEventCode $eventCode,
        callable $thresholdResolver,
        callable $magnitudePicker,
        bool $requireMeanAboveThreshold = true,
        bool $requireAcceleration = false,
    ): array {
        if (! DriverMotionSafetyPolicy::enabled()) {
            return [];
        }

        $freightClass = DriverMotionSafetyPolicy::freightClassForAssignment($assignment);
        $threshold = $thresholdResolver($freightClass);
        $meta = config('driver_evaluation.detectors.motion');
        $indexed = $logs->values();
        $candidates = [];

        for ($i = DriverMotionSafetyPolicy::minSamples() - 1; $i < $indexed->count(); $i++) {
            /** @var TrackingLog $current */
            $current = $indexed[$i];
            if (! $this->loader->isMotionTrustworthy($current)) {
                continue;
            }

            $windowLogs = $indexed->slice(max(0, $i - 15), $i + 1)->values()->reverse()->values();
            $chain = $this->confirmedChain(
                $windowLogs,
                $current,
                requireDeceleration: $requireDeceleration,
                requireAcceleration: $requireAcceleration,
            );
            if ($chain === null || $chain['observations'] === []) {
                continue;
            }

            $magnitudes = array_map($magnitudePicker, $chain['observations']);
            $mean = array_sum($magnitudes) / count($magnitudes);
            if ($requireMeanAboveThreshold && $mean < $threshold) {
                continue;
            }

            /** @var list<TrackingLog> $samples */
            $samples = $chain['samples'];
            $newest = Carbon::parse($samples[0]->recorded_at);
            $oldest = Carbon::parse($samples[count($samples) - 1]->recorded_at);
            $ids = array_map(static fn (TrackingLog $l) => (int) $l->id, array_reverse($samples));

            $candidates[] = new DetectedEvaluationCandidate(
                eventCode: $eventCode,
                evidenceSource: $this->loader->evidenceSource($current),
                detectorCode: $meta['code'],
                detectorVersion: $meta['version'],
                occurredAt: $newest,
                trackingLogIds: $ids,
                evidenceMetadata: [
                    'mean_magnitude_ms2' => round($mean, 4),
                    'threshold_ms2' => $threshold,
                    'freight_class' => $freightClass,
                    'sample_count' => count($samples),
                    'peak_magnitude_ms2' => round(max($magnitudes), 4),
                ],
                windowStart: $oldest,
                windowEnd: $newest,
            );
        }

        return $this->dedupeByLastSample($candidates);
    }

    /**
     * @param  Collection<int, TrackingLog>  $logsNewestFirst
     * @return array{samples: list<TrackingLog>, observations: list<ObservedLongitudinalMotion>}|null
     */
    private function confirmedChain(
        Collection $logsNewestFirst,
        TrackingLog $current,
        bool $requireDeceleration,
        bool $requireAcceleration,
    ): ?array {
        $minSamples = DriverMotionSafetyPolicy::minSamples();
        $minDuration = DriverMotionSafetyPolicy::minDurationSeconds();

        $samples = [];
        $observations = [];
        $previous = null;

        foreach ($logsNewestFirst as $log) {
            if (! $this->loader->isMotionTrustworthy($log)) {
                break;
            }

            if ($previous instanceof TrackingLog && $this->loader->isImpossibleJump($previous, $log)) {
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

                if (abs($observed->accelerationMs2) > DriverMotionSafetyPolicy::maxPlausibleDecelerationMs2()) {
                    break;
                }

                if ($requireDeceleration && ! $observed->isDeceleration()) {
                    break;
                }

                if ($requireAcceleration && ! $observed->isAcceleration()) {
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

        return [
            'samples' => $samples,
            'observations' => $observations,
        ];
    }

    /**
     * @param  list<DetectedEvaluationCandidate>  $candidates
     * @return list<DetectedEvaluationCandidate>
     */
    private function dedupeByLastSample(array $candidates): array
    {
        $seen = [];
        $out = [];
        foreach ($candidates as $candidate) {
            $ids = $candidate->trackingLogIds;
            $tail = $ids === [] ? '0' : (string) $ids[count($ids) - 1];
            $key = $candidate->eventCode->value.':'.$tail;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $candidate;
        }

        return $out;
    }
}
