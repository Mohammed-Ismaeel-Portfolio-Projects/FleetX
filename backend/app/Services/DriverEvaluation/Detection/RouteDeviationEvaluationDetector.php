<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\DriverEvaluationEventCode;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\TrackingLog;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Simulation\Routing\Application\Services\RouteDeviationDetector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Translates RouteDeviationDetector results into evaluation evidence.
 * Geometry source: PlannedRouteCache (durable routes table is UUID-incompatible).
 */
class RouteDeviationEvaluationDetector implements EvaluationEventDetector
{
    public function __construct(
        private readonly RouteDeviationDetector $routeDeviationDetector,
        private readonly PlannedRouteCache $plannedRouteCache,
        private readonly EvaluationTelemetryLoader $loader,
    ) {}

    public function detect(Assignment $assignment, AssignmentEvaluation $evaluation, Collection $logs): array
    {
        if (! (bool) config('routing.deviation.enabled', true)) {
            return [];
        }

        $coordinates = $this->resolveRouteCoordinates((string) $assignment->shipment_id);
        if (count($coordinates) < 2) {
            return [];
        }

        $threshold = (float) config('routing.deviation.threshold_meters', 100);
        $meta = config('driver_evaluation.detectors.route_deviation');
        $candidates = [];
        $inDeviation = false;
        $window = [];

        foreach ($logs as $log) {
            /** @var TrackingLog $log */
            if ($log->latitude === null || $log->longitude === null) {
                continue;
            }

            $result = $this->routeDeviationDetector->detect(
                latitude: (float) $log->latitude,
                longitude: (float) $log->longitude,
                routeCoordinates: $coordinates,
                speedKmh: $log->speed !== null ? (float) $log->speed : null,
                shipmentId: (string) $assignment->shipment_id,
                thresholdMeters: $threshold,
            );

            if ($result->isDeviated) {
                $window[] = [
                    'log' => $log,
                    'distance' => $result->deviationDistanceMeters,
                ];
                $inDeviation = true;
                continue;
            }

            if ($inDeviation && $window !== []) {
                $candidates[] = $this->buildCandidate($window, $meta, $threshold);
                $window = [];
                $inDeviation = false;
            }
        }

        if ($inDeviation && $window !== []) {
            $candidates[] = $this->buildCandidate($window, $meta, $threshold);
        }

        return $candidates;
    }

    /**
     * @param  list<array{log: TrackingLog, distance: float}>  $window
     * @param  array{code: string, version: string}  $meta
     */
    private function buildCandidate(array $window, array $meta, float $threshold): DetectedEvaluationCandidate
    {
        $logs = array_map(static fn (array $row) => $row['log'], $window);
        $distances = array_map(static fn (array $row) => $row['distance'], $window);
        /** @var TrackingLog $first */
        $first = $logs[0];
        /** @var TrackingLog $last */
        $last = $logs[count($logs) - 1];

        return new DetectedEvaluationCandidate(
            eventCode: DriverEvaluationEventCode::ROUTE_DEVIATION,
            evidenceSource: $this->loader->evidenceSource($last),
            detectorCode: $meta['code'],
            detectorVersion: $meta['version'],
            occurredAt: Carbon::parse($last->recorded_at),
            trackingLogIds: array_map(static fn (TrackingLog $l) => (int) $l->id, $logs),
            evidenceMetadata: [
                'threshold_meters' => $threshold,
                'peak_deviation_meters' => max($distances),
                'sample_count' => count($logs),
                'geometry_source' => 'route_deviation_detector',
            ],
            windowStart: Carbon::parse($first->recorded_at),
            windowEnd: Carbon::parse($last->recorded_at),
        );
    }

    /**
     * @return list<array{latitude: float, longitude: float}>
     */
    private function resolveRouteCoordinates(string $shipmentId): array
    {
        $cached = $this->plannedRouteCache->get($shipmentId);
        if (! $this->plannedRouteCache->hasUsableRoute($cached)) {
            return [];
        }

        $coords = $cached['coordinates'] ?? [];
        if (! is_array($coords) || count($coords) < 2) {
            $coords = $cached['api_payload']['route']['geometry']['coordinates'] ?? [];
        }

        $normalized = [];
        foreach ($coords as $coord) {
            if (! is_array($coord)) {
                continue;
            }
            if (isset($coord['latitude'], $coord['longitude'])) {
                $normalized[] = [
                    'latitude' => (float) $coord['latitude'],
                    'longitude' => (float) $coord['longitude'],
                ];
                continue;
            }
            if (isset($coord[0], $coord[1]) && is_numeric($coord[0]) && is_numeric($coord[1])) {
                $normalized[] = [
                    'latitude' => (float) $coord[1],
                    'longitude' => (float) $coord[0],
                ];
            }
        }

        return $normalized;
    }
}
