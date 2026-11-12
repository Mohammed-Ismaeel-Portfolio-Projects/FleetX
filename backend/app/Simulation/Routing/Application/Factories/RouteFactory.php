<?php

namespace App\Simulation\Routing\Application\Factories;

use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\Domain\Models\Route;
use App\Simulation\Routing\Domain\Models\RouteLeg;
use App\Simulation\Routing\Domain\Models\RouteStep;
use App\Simulation\Routing\Domain\Models\Waypoint;
use App\Simulation\Routing\ValueObjects\RouteGeometry;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\Polyline;
use App\Simulation\Routing\ValueObjects\SpeedLimit;
use App\Simulation\Routing\Enums\TravelMode;
use App\Simulation\Routing\Enums\RoadType;
use App\Simulation\Routing\Enums\InstructionType;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;

/**
 * RouteFactory.
 * Factory for creating Route aggregates from normalized routing data.
 * Provider-agnostic - never knows about OSRM, Google, etc.
 */
class RouteFactory
{
    /**
     * Create a Route aggregate from NormalizedRoute.
     *
     * @param NormalizedRoute $normalizedRoute Normalized routing data from any provider
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param Coordinate[] $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @param string $providerName Provider name (for metadata only)
     * @return Route The constructed route
     * @throws InvalidRouteDataException
     */
    public function createFromNormalizedRoute(
        NormalizedRoute $normalizedRoute,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
        string $providerName,
    ): Route {
        $this->validateNormalizedRoute($normalizedRoute);

        $routeId = $normalizedRoute->metadata['route_id'] ?? $this->generateRouteId();
        $geometry = $normalizedRoute->geometry;
        $legs = $this->createLegsFromNormalizedRoute($normalizedRoute, $origin, $destination);
        $waypointModels = $this->createWaypointsFromCoordinates($origin, $destination, $waypoints);

        return Route::create(
            id: $routeId,
            origin: $origin,
            destination: $destination,
            waypoints: $waypointModels,
            legs: $legs,
            geometry: $geometry,
            distance: $normalizedRoute->distance,
            duration: $normalizedRoute->duration,
            travelMode: $travelMode,
            provider: $providerName,
        );
    }

    /**
     * Create a Route aggregate from normalized routing data (legacy method).
     *
     * @param array $normalizedData Normalized routing data from any provider
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param Coordinate[] $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @param string $providerName Provider name (for metadata only)
     * @return Route The constructed route
     * @throws InvalidRouteDataException
     */
    public function createFromNormalizedData(
        array $normalizedData,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
        string $providerName,
    ): Route {
        // Validate normalized data structure
        $this->validateNormalizedData($normalizedData);

        // Extract route-level data
        $routeId = $normalizedData['route_id'] ?? $this->generateRouteId();
        $totalDistance = Distance::fromMeters($normalizedData['total_distance'] ?? 0);
        $totalDuration = Duration::fromSeconds($normalizedData['total_duration'] ?? 0);

        // Create geometry
        $geometry = $this->createGeometry($normalizedData);

        // Create legs
        $legs = $this->createLegs($normalizedData['legs'] ?? []);

        // Create waypoints
        $waypointModels = $this->createWaypoints($waypoints, $normalizedData);

        // Build route
        return Route::create(
            id: $routeId,
            origin: $origin,
            destination: $destination,
            waypoints: $waypointModels,
            legs: $legs,
            geometry: $geometry,
            distance: $totalDistance,
            duration: $totalDuration,
            travelMode: $travelMode,
            provider: $providerName,
        );
    }

    /**
     * Create route geometry from normalized data.
     *
     * @param array $normalizedData Normalized routing data
     * @return RouteGeometry The route geometry
     */
    private function createGeometry(array $normalizedData): RouteGeometry
    {
        $encodedPolyline = $normalizedData['geometry']['encoded'] ?? '';
        $coordinates = [];

        if (!empty($normalizedData['geometry']['coordinates'])) {
            $coordinates = array_map(
                fn($coord) => new Coordinate($coord['lat'], $coord['lon']),
                $normalizedData['geometry']['coordinates']
            );
        }

        if (!empty($encodedPolyline)) {
            return RouteGeometry::fromPolyline(Polyline::fromEncoded($encodedPolyline));
        }

        if (!empty($coordinates)) {
            return RouteGeometry::fromCoordinates($coordinates);
        }

        // Fallback: create geometry from origin/destination
        return RouteGeometry::fromCoordinates([$normalizedData['origin'], $normalizedData['destination']]);
    }

    /**
     * Create route legs from normalized data.
     *
     * @param array $legsData Normalized legs data
     * @return RouteLeg[] Array of route legs
     */
    private function createLegs(array $legsData): array
    {
        $legs = [];

        foreach ($legsData as $index => $legData) {
            $leg = $this->createLeg($legData, $index);
            $legs[] = $leg;
        }

        return $legs;
    }

    /**
     * Create a single route leg.
     *
     * @param array $legData Leg data
     * @param int $index Leg index
     * @return RouteLeg The route leg
     */
    private function createLeg(array $legData, int $index): RouteLeg
    {
        $start = new Coordinate(
            $legData['start']['lat'],
            $legData['start']['lon']
        );
        $end = new Coordinate(
            $legData['end']['lat'],
            $legData['end']['lon']
        );
        $distance = Distance::fromMeters($legData['distance'] ?? 0);
        $duration = Duration::fromSeconds($legData['duration'] ?? 0);

        // Create geometry
        $geometry = $this->createLegGeometry($legData);

        // Create steps
        $steps = $this->createSteps($legData['steps'] ?? []);

        return RouteLeg::create(
            id: $legData['id'] ?? "leg_{$index}",
            start: $start,
            end: $end,
            distance: $distance,
            duration: $duration,
            geometry: $geometry,
            steps: $steps,
        );
    }

    /**
     * Create leg geometry.
     *
     * @param array $legData Leg data
     * @return Polyline The leg geometry
     */
    private function createLegGeometry(array $legData): Polyline
    {
        if (!empty($legData['geometry']['encoded'])) {
            return Polyline::fromEncoded($legData['geometry']['encoded']);
        }

        if (!empty($legData['geometry']['coordinates'])) {
            $coordinates = array_map(
                fn($coord) => new Coordinate($coord['lat'], $coord['lon']),
                $legData['geometry']['coordinates']
            );
            return Polyline::fromCoordinates($coordinates);
        }

        // Fallback: create from start/end
        return Polyline::fromCoordinates([
            new Coordinate($legData['start']['lat'], $legData['start']['lon']),
            new Coordinate($legData['end']['lat'], $legData['end']['lon']),
        ]);
    }

    /**
     * Create route steps from normalized data.
     *
     * @param array $stepsData Normalized steps data
     * @return RouteStep[] Array of route steps
     */
    private function createSteps(array $stepsData): array
    {
        $steps = [];

        foreach ($stepsData as $index => $stepData) {
            $step = $this->createStep($stepData, $index);
            $steps[] = $step;
        }

        return $steps;
    }

    /**
     * Create a single route step.
     *
     * @param array $stepData Step data
     * @param int $index Step index
     * @return RouteStep The route step
     */
    private function createStep(array $stepData, int $index): RouteStep
    {
        $instruction = $stepData['instruction'] ?? '';
        $instructionType = $this->parseInstructionType($stepData['instruction_type'] ?? 'unknown');
        $distance = Distance::fromMeters($stepData['distance'] ?? 0);
        $duration = Duration::fromSeconds($stepData['duration'] ?? 0);
        $roadType = $this->parseRoadType($stepData['road_type'] ?? 'unknown');
        $geometry = $this->createStepGeometry($stepData);
        $speedLimit = !empty($stepData['speed_limit']) 
            ? SpeedLimit::fromKilometersPerHour($stepData['speed_limit']) 
            : null;

        return RouteStep::create(
            id: $stepData['id'] ?? "step_{$index}",
            instruction: $instruction,
            instructionType: $instructionType,
            distance: $distance,
            duration: $duration,
            roadType: $roadType,
            geometry: $geometry,
            speedLimit: $speedLimit,
        );
    }

    /**
     * Create step geometry.
     *
     * @param array $stepData Step data
     * @return Polyline The step geometry
     */
    private function createStepGeometry(array $stepData): Polyline
    {
        if (!empty($stepData['geometry']['encoded'])) {
            return Polyline::fromEncoded($stepData['geometry']['encoded']);
        }

        if (!empty($stepData['geometry']['coordinates'])) {
            $coordinates = array_map(
                fn($coord) => new Coordinate($coord['lat'], $coord['lon']),
                $stepData['geometry']['coordinates']
            );
            return Polyline::fromCoordinates($coordinates);
        }

        return $this->safePolylineFromPoints([new Coordinate(0, 0)]);
    }

    /**
     * Create waypoints from request and normalized data.
     *
     * @param Coordinate[] $requestWaypoints Waypoints from request
     * @param array $normalizedData Normalized routing data
     * @return Waypoint[] Array of waypoints
     */
    private function createWaypoints(array $requestWaypoints, array $normalizedData): array
    {
        $waypoints = [];

        // Add origin as first waypoint
        $waypoints[] = Waypoint::create(
            id: 'origin',
            coordinate: new Coordinate(
                $normalizedData['origin']['lat'],
                $normalizedData['origin']['lon']
            ),
            name: 'origin',
            sequenceNumber: 0,
        );

        // Add intermediate waypoints
        foreach ($requestWaypoints as $index => $waypoint) {
            $waypoints[] = Waypoint::create(
                id: "waypoint_{$index}",
                coordinate: $waypoint,
                name: "waypoint_{$index}",
                sequenceNumber: $index + 1,
            );
        }

        // Add destination as last waypoint
        $waypoints[] = Waypoint::create(
            id: 'destination',
            coordinate: new Coordinate(
                $normalizedData['destination']['lat'],
                $normalizedData['destination']['lon']
            ),
            name: 'destination',
            sequenceNumber: count($requestWaypoints) + 1,
        );

        return $waypoints;
    }

    /**
     * Parse instruction type from string.
     *
     * @param string $type Instruction type string
     * @return InstructionType The instruction type enum
     */
    private function parseInstructionType(string $type): InstructionType
    {
        return match(strtolower($type)) {
            'turn_left' => InstructionType::TURN_LEFT,
            'turn_right' => InstructionType::TURN_RIGHT,
            'turn_slight_left' => InstructionType::TURN_SLIGHT_LEFT,
            'turn_slight_right' => InstructionType::TURN_SLIGHT_RIGHT,
            'continue', 'straight' => InstructionType::CONTINUE_STRAIGHT,
            'u_turn' => InstructionType::U_TURN,
            'merge' => InstructionType::MERGE,
            'ramp' => InstructionType::RAMP,
            'roundabout_enter' => InstructionType::ROUNDABOUT_ENTER,
            'roundabout_exit' => InstructionType::ROUNDABOUT_EXIT,
            'roundabout_straight' => InstructionType::ROUNDABOUT_STRAIGHT,
            'ferry_enter' => InstructionType::FERRY_ENTER,
            'ferry_exit' => InstructionType::FERRY_EXIT,
            'arrive', 'destination' => InstructionType::ARRIVE,
            'depart', 'start' => InstructionType::DEPART,
            'head_on' => InstructionType::HEAD_ON,
            'end_of_road' => InstructionType::END_OF_ROAD,
            default => InstructionType::UNKNOWN,
        };
    }

    /**
     * Parse road type from string.
     *
     * @param string $type Road type string
     * @return RoadType The road type enum
     */
    private function parseRoadType(string $type): RoadType
    {
        return match(strtolower($type)) {
            'motorway', 'highway' => RoadType::MOTORWAY,
            'trunk' => RoadType::TRUNK,
            'primary' => RoadType::PRIMARY,
            'secondary' => RoadType::SECONDARY,
            'tertiary' => RoadType::TERTIARY,
            'residential', 'street' => RoadType::RESIDENTIAL,
            'service' => RoadType::SERVICE,
            'unpaved', 'dirt' => RoadType::UNPAVED,
            'ferry' => RoadType::FERRY,
            default => RoadType::UNKNOWN,
        };
    }

    /**
     * Validate normalized route.
     *
     * @param NormalizedRoute $normalizedRoute The normalized route
     * @throws InvalidRouteDataException
     */
    private function validateNormalizedRoute(NormalizedRoute $normalizedRoute): void
    {
        // Validation is handled by the NormalizedRoute structure
        // This is a placeholder for additional validation if needed
    }

    /**
     * Validate normalized data structure.
     *
     * @param array $normalizedData Normalized routing data
     * @throws InvalidRouteDataException
     */
    private function validateNormalizedData(array $normalizedData): void
    {
        if (empty($normalizedData)) {
            throw new InvalidRouteDataException('Normalized data is empty');
        }

        if (!isset($normalizedData['origin']) || !isset($normalizedData['destination'])) {
            throw new InvalidRouteDataException('Missing origin or destination in normalized data');
        }

        if (!isset($normalizedData['legs']) || !is_array($normalizedData['legs'])) {
            throw new InvalidRouteDataException('Missing or invalid legs in normalized data');
        }
    }

    /**
     * Build route legs from a NormalizedRoute DTO.
     * Mapper legs use from/to + nested distance/duration; fall back to a single O-D leg.
     *
     * @return RouteLeg[]
     */
    private function createLegsFromNormalizedRoute(
        NormalizedRoute $normalizedRoute,
        Coordinate $origin,
        Coordinate $destination,
    ): array {
        $legs = [];

        foreach ($normalizedRoute->legs as $index => $legData) {
            if (!is_array($legData)) {
                continue;
            }

            $start = $this->coordinateFromNormalizedPoint(
                $legData['from'] ?? $legData['start'] ?? null,
                $origin
            );
            $end = $this->coordinateFromNormalizedPoint(
                $legData['to'] ?? $legData['end'] ?? null,
                $destination
            );

            $distanceMeters = is_array($legData['distance'] ?? null)
                ? ($legData['distance']['meters'] ?? 0)
                : ($legData['distance'] ?? 0);
            $durationSeconds = is_array($legData['duration'] ?? null)
                ? ($legData['duration']['seconds'] ?? 0)
                : ($legData['duration'] ?? 0);

            $legs[] = RouteLeg::create(
                id: $legData['id'] ?? "leg_{$index}",
                start: $start,
                end: $end,
                distance: Distance::fromMeters((float) $distanceMeters),
                duration: Duration::fromSeconds((float) $durationSeconds),
                geometry: $this->safePolylineFromPoints([$start, $end]),
                steps: $this->createStepsFromNormalizedSteps(
                    isset($legData['steps']) && is_array($legData['steps'])
                        ? $legData['steps']
                        : ($index === 0 ? $normalizedRoute->steps : [])
                ),
            );
        }

        if (empty($legs)) {
            $legs[] = RouteLeg::create(
                id: 'leg_0',
                start: $origin,
                end: $destination,
                distance: $normalizedRoute->distance,
                duration: $normalizedRoute->duration,
                geometry: $normalizedRoute->geometry->getPolyline(),
                steps: $this->createStepsFromNormalizedSteps($normalizedRoute->steps),
            );
        }

        return $legs;
    }

    /**
     * @param array|null $point
     */
    private function coordinateFromNormalizedPoint($point, Coordinate $fallback): Coordinate
    {
        if (!is_array($point)) {
            return $fallback;
        }

        $lat = $point['latitude'] ?? $point['lat'] ?? null;
        $lng = $point['longitude'] ?? $point['lon'] ?? $point['lng'] ?? null;

        if ($lat === null || $lng === null) {
            return $fallback;
        }

        return new Coordinate((float) $lat, (float) $lng);
    }

    /**
     * @param Coordinate[] $points
     */
    private function safePolylineFromPoints(array $points): Polyline
    {
        $valid = array_values(array_filter($points, fn($p) => $p instanceof Coordinate));

        if (count($valid) < 1) {
            $valid = [new Coordinate(0, 0)];
        }

        if (count($valid) === 1) {
            $valid[] = $valid[0];
        }

        return Polyline::fromCoordinates($valid);
    }

    /**
     * @param Coordinate[] $waypoints
     * @return Waypoint[]
     */
    private function createWaypointsFromCoordinates(
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
    ): array {
        $models = [
            Waypoint::create(
                id: 'origin',
                coordinate: $origin,
                name: 'origin',
                sequenceNumber: 0,
            ),
        ];

        foreach ($waypoints as $index => $waypoint) {
            if (!$waypoint instanceof Coordinate) {
                continue;
            }

            $models[] = Waypoint::create(
                id: "waypoint_{$index}",
                coordinate: $waypoint,
                name: "waypoint_{$index}",
                sequenceNumber: $index + 1,
            );
        }

        $models[] = Waypoint::create(
            id: 'destination',
            coordinate: $destination,
            name: 'destination',
            sequenceNumber: count($waypoints) + 1,
        );

        return $models;
    }

    /**
     * @param array $stepsData
     * @return RouteStep[]
     */
    private function createStepsFromNormalizedSteps(array $stepsData): array
    {
        $steps = [];

        foreach ($stepsData as $index => $stepData) {
            if (!is_array($stepData)) {
                continue;
            }

            $distanceMeters = is_array($stepData['distance'] ?? null)
                ? ($stepData['distance']['meters'] ?? 0)
                : ($stepData['distance'] ?? 0);
            $durationSeconds = is_array($stepData['duration'] ?? null)
                ? ($stepData['duration']['seconds'] ?? 0)
                : ($stepData['duration'] ?? 0);

            $steps[] = RouteStep::create(
                id: $stepData['id'] ?? "step_{$index}",
                instruction: $stepData['instruction'] ?? '',
                instructionType: $this->parseInstructionType(
                    $stepData['instruction_type'] ?? $stepData['maneuver_type'] ?? 'unknown'
                ),
                distance: Distance::fromMeters((float) $distanceMeters),
                duration: Duration::fromSeconds((float) $durationSeconds),
                roadType: $this->parseRoadType($stepData['road_type'] ?? 'unknown'),
                geometry: $this->safePolylineFromPoints([
                    new Coordinate(0, 0),
                    new Coordinate(0, 0),
                ]),
                speedLimit: !empty($stepData['speed_limit'])
                    ? SpeedLimit::fromKilometersPerHour($stepData['speed_limit'])
                    : null,
            );
        }

        return $steps;
    }

    /**
     * Generate a unique route ID.
     *
     * @return string The route ID
     */
    private function generateRouteId(): string
    {
        return 'route_' . uniqid();
    }
}
