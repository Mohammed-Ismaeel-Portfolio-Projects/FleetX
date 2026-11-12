<?php

namespace App\Simulation\Routing\Infrastructure\ResponseMappers;

use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\Polyline;
use App\Simulation\Routing\ValueObjects\RouteGeometry;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * OSRMResponseMapper
 * 
 * Maps OSRM provider responses to NormalizedRoute.
 * Transforms raw OSRM JSON into FleetX internal DTOs.
 * 
 * Responsibilities:
 * - Validate OSRM response format
 * - Decode OSRM polyline to Polyline ValueObject
 * - Map OSRM fields to NormalizedRoute
 * - Use existing ValueObjects (Coordinate, Distance, Duration, RouteGeometry)
 * 
 * Does NOT:
 * - Perform HTTP requests
 * - Create Route domain objects
 * - Calculate ETA (uses Duration from OSRM)
 */
class OSRMResponseMapper implements ProviderResponseMapperInterface
{
    /**
     * Map OSRM provider response to NormalizedRoute.
     *
     * @param array $providerResponse Raw OSRM JSON response
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param array $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return NormalizedRoute The normalized route
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
            throw new InvalidRouteDataException('Invalid OSRM response format');
        }

        $route = $providerResponse['routes'][0] ?? [];

        // Decode geometry using existing Polyline ValueObject
        $geometry = $this->parseGeometry($route['geometry'] ?? '');

        // Parse distance and duration
        $distanceMeters = $route['distance'] ?? 0;
        $durationSeconds = $route['duration'] ?? 0;

        // Parse legs
        $legs = $this->parseLegs($route['legs'] ?? [], $origin, $destination);

        // Parse steps
        $steps = $this->parseSteps($route['legs'] ?? []);

        // Parse waypoints
        $parsedWaypoints = $this->parseWaypoints($providerResponse['waypoints'] ?? []);

        return new NormalizedRoute(
            origin: $origin,
            destination: $destination,
            geometry: $geometry,
            distance: Distance::fromMeters($distanceMeters),
            duration: Duration::fromSeconds($durationSeconds),
            legs: $legs,
            steps: $steps,
            waypoints: $parsedWaypoints,
            metadata: [
                'provider' => 'osrm',
                'travel_mode' => $travelMode->value,
                'weight' => $route['weight'] ?? null,
                'weight_name' => $route['weight_name'] ?? null,
            ],
            warnings: $this->extractWarnings($providerResponse),
            diagnostics: [],
        );
    }

    /**
     * Validate OSRM provider response format.
     *
     * @param array $providerResponse Raw OSRM JSON response
     * @return bool True if valid, false otherwise
     */
    public function validateProviderResponse(array $providerResponse): bool
    {
        return isset($providerResponse['code'])
            && $providerResponse['code'] === 'Ok'
            && isset($providerResponse['routes'])
            && is_array($providerResponse['routes'])
            && !empty($providerResponse['routes'])
            && isset($providerResponse['waypoints'])
            && is_array($providerResponse['waypoints']);
    }

    /**
     * Get supported provider name.
     *
     * @return string The provider name
     */
    public function getSupportedProvider(): string
    {
        return 'osrm';
    }

    /**
     * Parse geometry from OSRM polyline.
     * Uses existing Polyline ValueObject for decoding.
     *
     * @param string $encodedPolyline Encoded polyline string from OSRM
     * @return RouteGeometry The route geometry
     */
    private function parseGeometry(string $encodedPolyline): RouteGeometry
    {
        if (empty($encodedPolyline)) {
            $zero = new Coordinate(0, 0);
            return RouteGeometry::fromCoordinates([$zero, $zero]);
        }

        // Use existing Polyline ValueObject to decode
        $polyline = Polyline::fromEncoded($encodedPolyline);
        return RouteGeometry::fromPolyline($polyline);
    }

    /**
     * Parse legs from OSRM response.
     *
     * @param array $legs OSRM legs array
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @return array Normalized legs
     */
    private function parseLegs(array $legs, Coordinate $origin, Coordinate $destination): array
    {
        $normalizedLegs = [];

        foreach ($legs as $index => $leg) {
            $normalizedLegs[] = [
                'from' => $index === 0 ? $origin->toArray() : null,
                'to' => $index === count($legs) - 1 ? $destination->toArray() : null,
                'distance' => [
                    'meters' => $leg['distance'] ?? 0,
                    'kilometers' => ($leg['distance'] ?? 0) / 1000,
                    'miles' => ($leg['distance'] ?? 0) / 1609.34,
                ],
                'duration' => [
                    'seconds' => $leg['duration'] ?? 0,
                    'minutes' => ($leg['duration'] ?? 0) / 60,
                    'hours' => ($leg['duration'] ?? 0) / 3600,
                ],
                'summary' => $leg['summary'] ?? '',
                'steps_count' => count($leg['steps'] ?? []),
            ];
        }

        return $normalizedLegs;
    }

    /**
     * Parse steps from OSRM response.
     *
     * @param array $legs OSRM legs array
     * @return array Normalized steps
     */
    private function parseSteps(array $legs): array
    {
        $normalizedSteps = [];

        foreach ($legs as $leg) {
            foreach ($leg['steps'] ?? [] as $step) {
                $normalizedSteps[] = [
                    'instruction' => $step['maneuver']['instruction'] ?? '',
                    'distance' => [
                        'meters' => $step['distance'] ?? 0,
                        'kilometers' => ($step['distance'] ?? 0) / 1000,
                    ],
                    'duration' => [
                        'seconds' => $step['duration'] ?? 0,
                        'minutes' => ($step['duration'] ?? 0) / 60,
                    ],
                    'maneuver_type' => $step['maneuver']['type'] ?? '',
                    'maneuver_modifier' => $step['maneuver']['modifier'] ?? '',
                    'road_name' => $step['name'] ?? '',
                    'road_ref' => $step['ref'] ?? '',
                ];
            }
        }

        return $normalizedSteps;
    }

    /**
     * Parse waypoints from OSRM response.
     *
     * @param array $waypoints OSRM waypoints array
     * @return array Normalized waypoints
     */
    private function parseWaypoints(array $waypoints): array
    {
        $normalizedWaypoints = [];

        foreach ($waypoints as $index => $waypoint) {
            $location = $waypoint['location'] ?? [0, 0];
            $normalizedWaypoints[] = [
                'id' => "wp-{$index}",
                'coordinate' => [
                    'latitude' => $location[1] ?? 0,
                    'longitude' => $location[0] ?? 0,
                ],
                'name' => $waypoint['name'] ?? '',
                'distance' => $waypoint['distance'] ?? 0,
                'sequence' => $index,
            ];
        }

        return $normalizedWaypoints;
    }

    /**
     * Extract warnings from OSRM response.
     *
     * @param array $providerResponse OSRM response
     * @return array Warnings array
     */
    private function extractWarnings(array $providerResponse): array
    {
        $warnings = [];

        // OSRM may include warnings in different formats
        if (isset($providerResponse['warnings']) && is_array($providerResponse['warnings'])) {
            $warnings = array_merge($warnings, $providerResponse['warnings']);
        }

        return $warnings;
    }
}
