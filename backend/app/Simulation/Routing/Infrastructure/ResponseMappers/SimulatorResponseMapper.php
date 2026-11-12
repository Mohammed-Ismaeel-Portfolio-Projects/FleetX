<?php

namespace App\Simulation\Routing\Infrastructure\ResponseMappers;

use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\RouteGeometry;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * SimulatorResponseMapper
 * 
 * Maps SimulatorProvider responses to NormalizedRoute.
 * Temporary mapper for development and testing.
 */
class SimulatorResponseMapper implements ProviderResponseMapperInterface
{
    /**
     * Map provider response to NormalizedRoute.
     *
     * @param array $providerResponse Raw response from SimulatorProvider
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param array $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return NormalizedRoute The normalized route
     */
    public function mapToNormalizedRoute(
        array $providerResponse,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): NormalizedRoute {
        if (!$this->validateProviderResponse($providerResponse)) {
            return NormalizedRoute::empty();
        }

        $route = $providerResponse['routes'][0] ?? [];

        // Parse geometry
        $geometry = $this->parseGeometry($route['geometry'] ?? '');
        
        // Parse distance and duration
        $distanceMeters = $route['distance'] ?? 0;
        $durationSeconds = $route['duration'] ?? 0;

        // Parse legs
        $legs = $this->parseLegs($route['legs'] ?? [], $origin, $destination);

        // Parse steps
        $steps = $this->parseSteps($route['legs'] ?? []);

        return new NormalizedRoute(
            origin: $origin,
            destination: $destination,
            geometry: $geometry,
            distance: Distance::fromMeters($distanceMeters),
            duration: Duration::fromSeconds($durationSeconds),
            legs: $legs,
            steps: $steps,
            waypoints: $waypoints,
            metadata: [
                'provider' => 'simulator',
                'travel_mode' => $travelMode->value,
            ],
            warnings: [],
            diagnostics: [],
        );
    }

    /**
     * Validate provider response format.
     *
     * @param array $providerResponse Raw response from SimulatorProvider
     * @return bool True if valid, false otherwise
     */
    public function validateProviderResponse(array $providerResponse): bool
    {
        return isset($providerResponse['code']) 
            && $providerResponse['code'] === 'Ok'
            && isset($providerResponse['routes'])
            && is_array($providerResponse['routes'])
            && !empty($providerResponse['routes']);
    }

    /**
     * Get supported provider name.
     *
     * @return string The provider name
     */
    public function getSupportedProvider(): string
    {
        return 'simulator';
    }

    /**
     * Parse geometry from provider response.
     *
     * @param string $geometryString Geometry string (JSON encoded coordinates)
     * @return RouteGeometry The route geometry
     */
    private function parseGeometry(string $geometryString): RouteGeometry
    {
        if (empty($geometryString)) {
            $zero = new Coordinate(0, 0);
            return RouteGeometry::fromCoordinates([$zero, $zero]);
        }

        $coordinates = json_decode($geometryString, true);

        if (!is_array($coordinates) || count($coordinates) < 1) {
            $zero = new Coordinate(0, 0);
            return RouteGeometry::fromCoordinates([$zero, $zero]);
        }

        $coordinateObjects = array_map(
            fn($coord) => new Coordinate((float) $coord[0], (float) $coord[1]),
            $coordinates
        );

        if (count($coordinateObjects) === 1) {
            $coordinateObjects[] = $coordinateObjects[0];
        }

        return RouteGeometry::fromCoordinates($coordinateObjects);
    }

    /**
     * Parse legs from provider response.
     *
     * @param array $legs Legs array
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @return array Normalized legs
     */
    private function parseLegs(array $legs, Coordinate $origin, Coordinate $destination): array
    {
        $normalizedLegs = [];

        foreach ($legs as $leg) {
            $normalizedLegs[] = [
                'from' => $origin->toArray(),
                'to' => $destination->toArray(),
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
                'steps_count' => count($leg['steps'] ?? []),
            ];
        }

        return $normalizedLegs;
    }

    /**
     * Parse steps from provider response.
     *
     * @param array $legs Legs array
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
                ];
            }
        }

        return $normalizedSteps;
    }
}
