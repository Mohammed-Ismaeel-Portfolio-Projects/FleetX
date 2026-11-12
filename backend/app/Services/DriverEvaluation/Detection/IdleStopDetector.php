<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\DriverEvaluationEventCode;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\TrackingLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sustained low-speed idle / prolonged stop from tracking_logs speed series.
 */
class IdleStopDetector implements EvaluationEventDetector
{
    public function __construct(private readonly EvaluationTelemetryLoader $loader) {}

    public function detect(Assignment $assignment, AssignmentEvaluation $evaluation, Collection $logs): array
    {
        return array_merge(
            $this->detectBand(
                $logs,
                eventCode: DriverEvaluationEventCode::EXCESSIVE_IDLE,
                minSpeed: null,
                maxSpeed: (float) config('driver_evaluation.idle.max_speed_kmh', 0.5),
                minDuration: (int) config('driver_evaluation.idle.min_duration_seconds', 600),
                minSamples: (int) config('driver_evaluation.idle.min_samples', 4),
                maxGap: (int) config('driver_evaluation.idle.max_gap_seconds', 120),
                detectorKey: 'idle',
            ),
            $this->detectBand(
                $logs,
                eventCode: DriverEvaluationEventCode::PROLONGED_STOP,
                minSpeed: (float) config('driver_evaluation.prolonged_stop.min_speed_kmh', 0.5),
                maxSpeed: (float) config('driver_evaluation.prolonged_stop.max_speed_kmh', 5.0),
                minDuration: (int) config('driver_evaluation.prolonged_stop.min_duration_seconds', 900),
                minSamples: (int) config('driver_evaluation.prolonged_stop.min_samples', 4),
                maxGap: (int) config('driver_evaluation.prolonged_stop.max_gap_seconds', 120),
                detectorKey: 'stop',
            ),
        );
    }

    /**
     * @return list<DetectedEvaluationCandidate>
     */
    private function detectBand(
        Collection $logs,
        DriverEvaluationEventCode $eventCode,
        ?float $minSpeed,
        float $maxSpeed,
        int $minDuration,
        int $minSamples,
        int $maxGap,
        string $detectorKey,
    ): array {
        $meta = config('driver_evaluation.detectors.'.$detectorKey);
        $candidates = [];
        $window = [];
        $previous = null;

        $flush = function () use (
            &$window,
            &$candidates,
            $eventCode,
            $minDuration,
            $minSamples,
            $meta,
            $minSpeed,
            $maxSpeed,
        ) {
            if (count($window) < $minSamples) {
                $window = [];

                return;
            }

            /** @var TrackingLog $first */
            $first = $window[0];
            /** @var TrackingLog $last */
            $last = $window[count($window) - 1];
            $start = Carbon::parse($first->recorded_at);
            $end = Carbon::parse($last->recorded_at);
            $duration = abs($end->diffInSeconds($start));
            if ($duration < $minDuration) {
                $window = [];

                return;
            }

            $ids = array_map(static fn (TrackingLog $l) => (int) $l->id, $window);
            $candidates[] = new DetectedEvaluationCandidate(
                eventCode: $eventCode,
                evidenceSource: $this->loader->evidenceSource($last),
                detectorCode: $meta['code'],
                detectorVersion: $meta['version'],
                occurredAt: $end,
                trackingLogIds: $ids,
                evidenceMetadata: [
                    'duration_seconds' => $duration,
                    'min_duration_seconds' => $minDuration,
                    'sample_count' => count($window),
                    'speed_band_min_kmh' => $minSpeed,
                    'speed_band_max_kmh' => $maxSpeed,
                ],
                windowStart: $start,
                windowEnd: $end,
            );
            $window = [];
        };

        foreach ($logs as $log) {
            /** @var TrackingLog $log */
            if (! $this->loader->isSpeedTrustworthy($log)) {
                $flush();
                $previous = null;
                continue;
            }

            $speed = (float) $log->speed;
            $inBand = $speed <= $maxSpeed && ($minSpeed === null || $speed > $minSpeed);

            if (! $inBand) {
                $flush();
                $previous = null;
                continue;
            }

            if ($previous instanceof TrackingLog) {
                $gap = abs(Carbon::parse($log->recorded_at)->diffInSeconds(Carbon::parse($previous->recorded_at)));
                if ($gap > $maxGap) {
                    $flush();
                }
            }

            $window[] = $log;
            $previous = $log;
        }

        $flush();

        return $candidates;
    }
}
