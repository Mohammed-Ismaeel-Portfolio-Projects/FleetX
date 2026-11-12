<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;

/**
 * RequestValidator Service.
 * Validates route planning requests.
 */
class RequestValidator
{
    /**
     * Validate route planning request.
     *
     * @param RoutePlanningRequest $request The request to validate
     * @throws InvalidRouteDataException
     */
    public function validate(RoutePlanningRequest $request): void
    {
        $this->validateCoordinates($request);
        $this->validateVehicleProfile($request);
        $this->validateWaypoints($request);
    }

    /**
     * Validate coordinates.
     */
    private function validateCoordinates(RoutePlanningRequest $request): void
    {
        // Validate origin and destination are different
        if ($request->origin->equals($request->destination)) {
            throw new InvalidRouteDataException('Origin and destination must be different');
        }

        // Validate coordinates are valid
        $this->validateCoordinate($request->origin, 'origin');
        $this->validateCoordinate($request->destination, 'destination');
    }

    /**
     * Validate single coordinate.
     */
    private function validateCoordinate($coordinate, string $fieldName): void
    {
        // Coordinate validation is handled in the ValueObject constructor
        // This is a placeholder for additional validation if needed
    }

    /**
     * Validate vehicle profile.
     */
    private function validateVehicleProfile(RoutePlanningRequest $request): void
    {
        $profile = $request->context->vehicle;

        // Validate large vehicle constraints
        if ($profile->isLargeVehicle()) {
            if ($profile->height > 5.0) {
                throw new InvalidRouteDataException('Vehicle height exceeds maximum allowed (5.0m)');
            }
            if ($profile->width > 2.6) {
                throw new InvalidRouteDataException('Vehicle width exceeds maximum allowed (2.6m)');
            }
            if ($profile->weight > 44000) {
                throw new InvalidRouteDataException('Vehicle weight exceeds maximum allowed (44,000kg)');
            }
        }

        // Validate trailer constraints
        if ($profile->hasTrailer && $profile->vehicleType->value === 'car') {
            throw new InvalidRouteDataException('Cars cannot have trailers in this configuration');
        }
    }

    /**
     * Validate waypoints.
     */
    private function validateWaypoints(RoutePlanningRequest $request): void
    {
        foreach ($request->waypoints as $index => $waypoint) {
            // Validate waypoint doesn't duplicate origin
            if ($waypoint->equals($request->origin)) {
                throw new InvalidRouteDataException("Waypoint at index {$index} cannot be the same as origin");
            }

            // Validate waypoint doesn't duplicate destination
            if ($waypoint->equals($request->destination)) {
                throw new InvalidRouteDataException("Waypoint at index {$index} cannot be the same as destination");
            }

            // Validate waypoints don't duplicate each other
            foreach ($request->waypoints as $otherIndex => $otherWaypoint) {
                if ($index !== $otherIndex && $waypoint->equals($otherWaypoint)) {
                    throw new InvalidRouteDataException("Duplicate waypoints at indices {$index} and {$otherIndex}");
                }
            }
        }
    }
}
