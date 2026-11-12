<?php

namespace App\Simulation\Journey;

/**
 * Deterministic rest-stop schedule from route distance + configurable policy.
 *
 * Not legal HOS — graduation-demo route intelligence only.
 */
final class RestStopPlanner
{
    public function __construct(
        private readonly float $maxDrivingDistanceM,
        private readonly int $restDurationSeconds,
        private readonly float $minRemainingAfterRestM,
        private readonly float $assumedCruiseSpeedKmh,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxDrivingDistanceM: (float) config('simulation.rest.max_driving_distance_m', 80_000),
            restDurationSeconds: (int) config('simulation.rest.rest_duration_seconds', 900),
            minRemainingAfterRestM: (float) config('simulation.rest.min_remaining_after_rest_m', 20_000),
            assumedCruiseSpeedKmh: (float) config('simulation.rest.assumed_cruise_speed_kmh', 80),
        );
    }

    /**
     * @param array<string, mixed>|null $geometry RouteProgressEngine geometry (optional lat/lng)
     */
    public function plan(string $shipmentId, float $totalDistanceM, ?array $geometry = null): JourneyPlan
    {
        $total = max(0.0, $totalDistanceM);
        $cruise = max(1.0, $this->assumedCruiseSpeedKmh);
        $estimatedSeconds = $total <= 0 ? 0.0 : ($total / 1000.0) / $cruise * 3600.0;

        $stops = [];
        $threshold = max(1.0, $this->maxDrivingDistanceM);
        $minTail = max(0.0, $this->minRemainingAfterRestM);

        if ($total > $threshold + $minTail) {
            $offset = $threshold;
            $seq = 1;
            while ($offset + $minTail < $total - 0.5) {
                $coords = $this->coordsAtOffset($geometry, $offset);
                $stops[] = new RestStopWaypoint(
                    id: sprintf('%s-rest-%d', $shipmentId, $seq),
                    sequence: $seq,
                    routeOffsetM: $offset,
                    durationSeconds: $this->restDurationSeconds,
                    reason: 'max_driving_distance',
                    status: RestStopStatus::Planned,
                    latitude: $coords['latitude'] ?? null,
                    longitude: $coords['longitude'] ?? null,
                );
                $seq++;
                $offset += $threshold;
            }
        }

        return new JourneyPlan(
            shipmentId: $shipmentId,
            totalDistanceM: $total,
            maxDrivingDistanceM: $threshold,
            restDurationSeconds: $this->restDurationSeconds,
            estimatedTravelSeconds: $estimatedSeconds,
            restStops: $stops,
            plannedAt: now()->toIso8601String(),
        );
    }

    /**
     * @param array<string, mixed>|null $geometry
     * @return array{latitude: float, longitude: float}|null
     */
    private function coordsAtOffset(?array $geometry, float $offsetM): ?array
    {
        if ($geometry === null) {
            return null;
        }

        $coords = $geometry['coordinates'] ?? null;
        $cumulative = $geometry['cumulative_m'] ?? null;
        $segmentLengths = $geometry['segment_lengths_m'] ?? null;
        if (! is_array($coords) || count($coords) < 2 || ! is_array($cumulative) || ! is_array($segmentLengths)) {
            return null;
        }

        $total = (float) ($geometry['total_distance_m'] ?? 0);
        $travelled = min($total, max(0.0, $offsetM));
        $seg = 0;
        $lastSeg = count($segmentLengths) - 1;
        while ($seg < $lastSeg && $travelled >= (float) ($cumulative[$seg + 1] ?? $total)) {
            $seg++;
        }

        $segStartCum = (float) ($cumulative[$seg] ?? 0.0);
        $segLen = (float) ($segmentLengths[$seg] ?? 0.0);
        $into = $travelled - $segStartCum;
        $ratio = $segLen <= 0.0001 ? 1.0 : min(1.0, max(0.0, $into / $segLen));
        $a = $coords[$seg];
        $b = $coords[$seg + 1] ?? $a;

        return [
            'latitude' => (float) $a['latitude'] + ((float) $b['latitude'] - (float) $a['latitude']) * $ratio,
            'longitude' => (float) $a['longitude'] + ((float) $b['longitude'] - (float) $a['longitude']) * $ratio,
        ];
    }
}
