<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Routing\Application\DTOs\RouteDeviationResult;
use App\Simulation\Routing\Enums\RouteDeviationStatus;
use App\Simulation\Routing\ValueObjects\Coordinate;

/**
 * RouteDeviationDetector
 *
 * Compares a live GPS position against planned route geometry and
 * reports ON_ROUTE / DEVIATED using a configurable meter threshold.
 */
class RouteDeviationDetector
{
    /**
     * @param array<int, Coordinate|array{latitude?: float, longitude?: float, lat?: float, lon?: float}> $routeCoordinates
     */
    public function detect(
        float $latitude,
        float $longitude,
        array $routeCoordinates,
        ?float $speedKmh = null,
        ?string $shipmentId = null,
        ?float $thresholdMeters = null,
    ): RouteDeviationResult {
        unset($speedKmh); // Reserved for future speed-aware hysteresis

        $threshold = $thresholdMeters ?? (float) config('routing.deviation.threshold_meters', 100);
        $vehicle = new Coordinate($latitude, $longitude);
        $points = $this->normalizeCoordinates($routeCoordinates);

        if (count($points) < 1) {
            return new RouteDeviationResult(
                isDeviated: false,
                deviationDistanceMeters: 0.0,
                nearestRoutePoint: null,
                routeStatus: RouteDeviationStatus::ON_ROUTE,
                shipmentId: $shipmentId,
            );
        }

        [$nearest, $distanceMeters] = $this->nearestPointOnRoute($vehicle, $points);
        $isDeviated = $distanceMeters > $threshold;

        return new RouteDeviationResult(
            isDeviated: $isDeviated,
            deviationDistanceMeters: $distanceMeters,
            nearestRoutePoint: $nearest,
            routeStatus: $isDeviated ? RouteDeviationStatus::DEVIATED : RouteDeviationStatus::ON_ROUTE,
            shipmentId: $shipmentId,
        );
    }

    /**
     * @param array<int, Coordinate|array> $routeCoordinates
     * @return Coordinate[]
     */
    private function normalizeCoordinates(array $routeCoordinates): array
    {
        $points = [];

        foreach ($routeCoordinates as $coordinate) {
            if ($coordinate instanceof Coordinate) {
                $points[] = $coordinate;
                continue;
            }

            if (!is_array($coordinate)) {
                continue;
            }

            $lat = $coordinate['latitude'] ?? $coordinate['lat'] ?? null;
            $lng = $coordinate['longitude'] ?? $coordinate['lon'] ?? $coordinate['lng'] ?? null;

            if ($lat === null || $lng === null) {
                continue;
            }

            $points[] = new Coordinate((float) $lat, (float) $lng);
        }

        return $points;
    }

    /**
     * @param Coordinate[] $points
     * @return array{0: Coordinate, 1: float} Nearest point and distance in meters
     */
    private function nearestPointOnRoute(Coordinate $vehicle, array $points): array
    {
        if (count($points) === 1) {
            $distanceKm = $vehicle->distanceTo($points[0])->toKilometers();

            return [$points[0], $distanceKm * 1000];
        }

        $bestPoint = $points[0];
        $bestDistance = PHP_FLOAT_MAX;

        for ($i = 0; $i < count($points) - 1; $i++) {
            [$candidate, $distanceMeters] = $this->nearestPointOnSegment(
                $vehicle,
                $points[$i],
                $points[$i + 1]
            );

            if ($distanceMeters < $bestDistance) {
                $bestDistance = $distanceMeters;
                $bestPoint = $candidate;
            }
        }

        return [$bestPoint, $bestDistance];
    }

    /**
     * Project vehicle onto segment A→B in a local tangent-plane approximation.
     *
     * @return array{0: Coordinate, 1: float}
     */
    private function nearestPointOnSegment(Coordinate $vehicle, Coordinate $a, Coordinate $b): array
    {
        $refLat = deg2rad(($a->latitude + $b->latitude) / 2);
        $metersPerDegLat = 110540.0;
        $metersPerDegLon = 111320.0 * cos($refLat);

        $ax = 0.0;
        $ay = 0.0;
        $bx = ($b->longitude - $a->longitude) * $metersPerDegLon;
        $by = ($b->latitude - $a->latitude) * $metersPerDegLat;
        $px = ($vehicle->longitude - $a->longitude) * $metersPerDegLon;
        $py = ($vehicle->latitude - $a->latitude) * $metersPerDegLat;

        $abx = $bx - $ax;
        $aby = $by - $ay;
        $apx = $px - $ax;
        $apy = $py - $ay;

        $abLenSq = ($abx * $abx) + ($aby * $aby);
        if ($abLenSq <= 0.0001) {
            $distanceKm = $vehicle->distanceTo($a)->toKilometers();

            return [$a, $distanceKm * 1000];
        }

        $t = (($apx * $abx) + ($apy * $aby)) / $abLenSq;
        $t = max(0.0, min(1.0, $t));

        $nearest = new Coordinate(
            $a->latitude + (($b->latitude - $a->latitude) * $t),
            $a->longitude + (($b->longitude - $a->longitude) * $t),
        );

        $distanceKm = $vehicle->distanceTo($nearest)->toKilometers();

        return [$nearest, $distanceKm * 1000];
    }
}
