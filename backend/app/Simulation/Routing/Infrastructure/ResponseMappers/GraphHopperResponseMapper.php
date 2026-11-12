<?php

namespace App\Simulation\Routing\Infrastructure\ResponseMappers;

use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Enums\TravelMode;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\Polyline;
use App\Simulation\Routing\ValueObjects\RouteGeometry;

/**
 * GraphHopperResponseMapper
 *
 * Maps GraphHopper Directions API responses to NormalizedRoute.
 * Does NOT create Route domain aggregates.
 */
class GraphHopperResponseMapper implements ProviderResponseMapperInterface
{
    /**
     * @param Coordinate[] $waypoints
     * @throws InvalidRouteDataException
     */
    public function mapToNormalizedRoute(
        array $providerResponse,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): NormalizedRoute {
        if (!$this->validateProviderResponse($providerResponse)) {
            throw new InvalidRouteDataException('Invalid GraphHopper response format');
        }

        $path = $providerResponse['paths'][0];

        $geometry = $this->parseGeometry($path['points'] ?? null);
        $distanceMeters = (float) ($path['distance'] ?? 0);
        // GraphHopper time is milliseconds
        $durationSeconds = ((float) ($path['time'] ?? 0)) / 1000;

        $steps = $this->parseInstructions($path['instructions'] ?? []);
        $legs = [[
            'from' => $origin->toArray(),
            'to' => $destination->toArray(),
            'distance' => [
                'meters' => $distanceMeters,
                'kilometers' => $distanceMeters / 1000,
                'miles' => $distanceMeters / 1609.34,
            ],
            'duration' => [
                'seconds' => $durationSeconds,
                'minutes' => $durationSeconds / 60,
                'hours' => $durationSeconds / 3600,
            ],
            'summary' => $path['description'] ?? '',
            'steps_count' => count($steps),
        ]];

        return new NormalizedRoute(
            origin: $origin,
            destination: $destination,
            geometry: $geometry,
            distance: Distance::fromMeters($distanceMeters),
            duration: Duration::fromSeconds($durationSeconds),
            legs: $legs,
            steps: $steps,
            waypoints: $this->normalizeWaypoints($waypoints),
            metadata: [
                'provider' => 'graphhopper',
                'travel_mode' => $travelMode->value,
                'weight' => $path['weight'] ?? null,
                'bbox' => $path['bbox'] ?? null,
                'transfers' => $path['transfers'] ?? null,
            ],
            warnings: $this->extractWarnings($providerResponse),
            diagnostics: [],
        );
    }

    public function validateProviderResponse(array $providerResponse): bool
    {
        return isset($providerResponse['paths'])
            && is_array($providerResponse['paths'])
            && !empty($providerResponse['paths'])
            && is_array($providerResponse['paths'][0]);
    }

    public function getSupportedProvider(): string
    {
        return 'graphhopper';
    }

    /**
     * Parse GraphHopper geometry (encoded polyline string or GeoJSON coordinates).
     */
    private function parseGeometry(mixed $points): RouteGeometry
    {
        if ($points === null || $points === '') {
            $zero = new Coordinate(0, 0);
            return RouteGeometry::fromCoordinates([$zero, $zero]);
        }

        // Encoded polyline (points_encoded=true)
        if (is_string($points)) {
            try {
                $polyline = Polyline::fromEncoded($points);
                if ($polyline->getCoordinateCount() < 1) {
                    $zero = new Coordinate(0, 0);
                    return RouteGeometry::fromCoordinates([$zero, $zero]);
                }
                return RouteGeometry::fromPolyline($polyline);
            } catch (\Throwable) {
                $zero = new Coordinate(0, 0);
                return RouteGeometry::fromCoordinates([$zero, $zero]);
            }
        }

        // GeoJSON-like object: { type: "LineString", coordinates: [[lng,lat], ...] }
        if (is_array($points)) {
            $coordinates = $points['coordinates'] ?? $points;
            if (!is_array($coordinates) || empty($coordinates)) {
                $zero = new Coordinate(0, 0);
                return RouteGeometry::fromCoordinates([$zero, $zero]);
            }

            $coordinateObjects = [];
            foreach ($coordinates as $coord) {
                if (!is_array($coord) || count($coord) < 2) {
                    continue;
                }
                // GraphHopper GeoJSON uses [longitude, latitude]
                $coordinateObjects[] = new Coordinate((float) $coord[1], (float) $coord[0]);
            }

            if (count($coordinateObjects) < 1) {
                $zero = new Coordinate(0, 0);
                return RouteGeometry::fromCoordinates([$zero, $zero]);
            }

            if (count($coordinateObjects) === 1) {
                $coordinateObjects[] = $coordinateObjects[0];
            }

            return RouteGeometry::fromCoordinates($coordinateObjects);
        }

        $zero = new Coordinate(0, 0);
        return RouteGeometry::fromCoordinates([$zero, $zero]);
    }

    /**
     * Map GraphHopper instructions to normalized step arrays.
     */
    private function parseInstructions(array $instructions): array
    {
        $steps = [];

        foreach ($instructions as $instruction) {
            if (!is_array($instruction)) {
                continue;
            }

            $distanceMeters = (float) ($instruction['distance'] ?? 0);
            $durationSeconds = ((float) ($instruction['time'] ?? 0)) / 1000;

            $steps[] = [
                'instruction' => $instruction['text'] ?? '',
                'distance' => [
                    'meters' => $distanceMeters,
                    'kilometers' => $distanceMeters / 1000,
                ],
                'duration' => [
                    'seconds' => $durationSeconds,
                    'minutes' => $durationSeconds / 60,
                ],
                'maneuver_type' => $this->mapSignToManeuver((int) ($instruction['sign'] ?? 0)),
                'maneuver_modifier' => null,
                'road_name' => $instruction['street_name'] ?? '',
                'road_ref' => $instruction['street_ref'] ?? null,
            ];
        }

        return $steps;
    }

    private function mapSignToManeuver(int $sign): string
    {
        return match ($sign) {
            -98 => 'u_turn',
            -8 => 'left',
            -7 => 'keep_left',
            -3 => 'sharp_left',
            -2 => 'left',
            -1 => 'slight_left',
            0 => 'continue',
            1 => 'slight_right',
            2 => 'right',
            3 => 'sharp_right',
            4 => 'arrive',
            5 => 'via',
            6 => 'roundabout',
            7 => 'keep_right',
            8 => 'right',
            default => 'unknown',
        };
    }

    /**
     * @param Coordinate[] $waypoints
     */
    private function normalizeWaypoints(array $waypoints): array
    {
        $normalized = [];

        foreach ($waypoints as $index => $waypoint) {
            if (!$waypoint instanceof Coordinate) {
                continue;
            }

            $normalized[] = [
                'id' => "wp-{$index}",
                'coordinate' => $waypoint->toArray(),
                'name' => "waypoint_{$index}",
                'distance' => 0,
                'sequence' => $index,
            ];
        }

        return $normalized;
    }

    private function extractWarnings(array $providerResponse): array
    {
        $warnings = [];

        if (isset($providerResponse['hints']) && is_array($providerResponse['hints'])) {
            foreach ($providerResponse['hints'] as $hint) {
                if (is_array($hint) && isset($hint['message'])) {
                    $warnings[] = $hint['message'];
                } elseif (is_string($hint)) {
                    $warnings[] = $hint;
                }
            }
        }

        return $warnings;
    }
}
