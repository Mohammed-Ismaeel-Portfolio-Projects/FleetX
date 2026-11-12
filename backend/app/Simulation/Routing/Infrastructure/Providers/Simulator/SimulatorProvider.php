<?php

namespace App\Simulation\Routing\Infrastructure\Providers\Simulator;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Enums\ProviderStatus;

/**
 * SimulatorProvider
 * 
 * Temporary routing provider for development and testing.
 * Generates mock route data without external API calls.
 * Will be replaced by real providers (OSRM, Google Maps, etc.) in production.
 */
class SimulatorProvider implements RoutingProviderInterface
{
    private ProviderStatus $status = ProviderStatus::AVAILABLE;

    /**
     * Calculate route.
     *
     * @param RoutePlanningRequest $request The route planning request
     * @return array The provider response
     */
    public function calculateRoute(RoutePlanningRequest $request): array
    {
        $origin = $request->origin;
        $destination = $request->destination;
        $waypoints = $request->waypoints;

        return [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => $this->generatePolyline($origin, $destination, $waypoints),
                    'legs' => [
                        [
                            'steps' => $this->generateSteps($origin, $destination),
                            'distance' => $this->calculatePathDistance($origin, $destination, $waypoints),
                            'duration' => $this->calculatePathDuration($origin, $destination, $waypoints),
                        ],
                    ],
                    'distance' => $this->calculatePathDistance($origin, $destination, $waypoints),
                    'duration' => $this->calculatePathDuration($origin, $destination, $waypoints),
                ],
            ],
        ];
    }

    /**
     * Get provider name.
     *
     * @return string The provider name
     */
    public function getProviderName(): string
    {
        return 'simulator';
    }

    /**
     * Check if provider is available.
     *
     * @return bool True if available
     */
    public function isAvailable(): bool
    {
        return $this->status === ProviderStatus::AVAILABLE;
    }

    /**
     * Get provider status.
     *
     * @return ProviderStatus The provider status
     */
    public function getStatus(): ProviderStatus
    {
        return $this->status;
    }

    /**
     * Set provider status.
     *
     * @param ProviderStatus $status The status
     */
    public function setStatus(ProviderStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * Get provider version.
     */
    public function getProviderVersion(): string
    {
        return 'simulator-1.0';
    }

    /**
     * Generate mock polyline through optional land-corridor waypoints.
     *
     * @param \App\Simulation\Routing\ValueObjects\Coordinate $origin
     * @param \App\Simulation\Routing\ValueObjects\Coordinate $destination
     * @param array $waypoints
     */
    private function generatePolyline($origin, $destination, array $waypoints = []): string
    {
        $anchors = array_merge([$origin], $waypoints, [$destination]);
        $points = $this->interpolateAlongAnchors($anchors, 50);

        return json_encode(array_map(fn($p) => [$p->latitude, $p->longitude], $points));
    }

    /**
     * @param array $anchors Coordinate[]
     * @return \App\Simulation\Routing\ValueObjects\Coordinate[]
     */
    private function interpolateAlongAnchors(array $anchors, int $totalPoints): array
    {
        if (count($anchors) < 2) {
            return $anchors;
        }

        $segmentDistances = [];
        $totalDistance = 0.0;
        for ($i = 0; $i < count($anchors) - 1; $i++) {
            $distance = $this->calculateDistance($anchors[$i], $anchors[$i + 1]);
            $segmentDistances[] = $distance;
            $totalDistance += $distance;
        }

        if ($totalDistance <= 0) {
            return [$anchors[0], $anchors[count($anchors) - 1]];
        }

        $points = [];
        for ($i = 0; $i <= $totalPoints; $i++) {
            $target = ($i / $totalPoints) * $totalDistance;
            $remaining = $target;
            $segmentIndex = 0;

            while ($segmentIndex < count($segmentDistances) - 1 && $remaining > $segmentDistances[$segmentIndex]) {
                $remaining -= $segmentDistances[$segmentIndex];
                $segmentIndex++;
            }

            $segmentLength = max($segmentDistances[$segmentIndex], 0.0001);
            $t = min(1.0, max(0.0, $remaining / $segmentLength));
            $from = $anchors[$segmentIndex];
            $to = $anchors[$segmentIndex + 1];

            $points[] = new \App\Simulation\Routing\ValueObjects\Coordinate(
                $from->latitude + ($to->latitude - $from->latitude) * $t,
                $from->longitude + ($to->longitude - $from->longitude) * $t
            );
        }

        return $points;
    }

    private function calculatePathDistance($origin, $destination, array $waypoints = []): float
    {
        $anchors = array_merge([$origin], $waypoints, [$destination]);
        $total = 0.0;
        for ($i = 0; $i < count($anchors) - 1; $i++) {
            $total += $this->calculateDistance($anchors[$i], $anchors[$i + 1]);
        }
        return $total;
    }

    private function calculatePathDuration($origin, $destination, array $waypoints = []): float
    {
        $distance = $this->calculatePathDistance($origin, $destination, $waypoints);
        return ($distance / 1000) / 80 * 3600;
    }

    /**
     * Generate mock steps.
     */
    private function generateSteps($origin, $destination): array
    {
        return [
            [
                'maneuver' => ['type' => 'depart', 'instruction' => 'Head toward destination'],
                'distance' => $this->calculateDistance($origin, $destination) * 0.1,
                'duration' => $this->calculateDuration($origin, $destination) * 0.1,
            ],
            [
                'maneuver' => ['type' => 'arrive', 'instruction' => 'Arrive at destination'],
                'distance' => $this->calculateDistance($origin, $destination) * 0.9,
                'duration' => $this->calculateDuration($origin, $destination) * 0.9,
            ],
        ];
    }

    /**
     * Calculate distance in meters.
     */
    private function calculateDistance($from, $to): float
    {
        $earthRadius = 6371000;
        $lat1Rad = deg2rad($from->latitude);
        $lat2Rad = deg2rad($to->latitude);
        $deltaLat = deg2rad($to->latitude - $from->latitude);
        $deltaLon = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Calculate duration in seconds.
     */
    private function calculateDuration($from, $to): float
    {
        $distance = $this->calculateDistance($from, $to);
        return ($distance / 1000) / 80 * 3600;
    }
}
