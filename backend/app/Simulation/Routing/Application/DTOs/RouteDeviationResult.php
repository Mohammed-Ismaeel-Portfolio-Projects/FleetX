<?php

namespace App\Simulation\Routing\Application\DTOs;

use App\Simulation\Routing\Enums\RouteDeviationStatus;
use App\Simulation\Routing\ValueObjects\Coordinate;

/**
 * Result of comparing a vehicle GPS position against planned route geometry.
 */
readonly class RouteDeviationResult
{
    public function __construct(
        public bool $isDeviated,
        public float $deviationDistanceMeters,
        public ?Coordinate $nearestRoutePoint,
        public RouteDeviationStatus $routeStatus,
        public ?string $shipmentId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'is_deviated' => $this->isDeviated,
            'deviation_distance' => round($this->deviationDistanceMeters, 2),
            'nearest_route_point' => $this->nearestRoutePoint?->toArray(),
            'route_status' => $this->routeStatus->value,
            'shipment_id' => $this->shipmentId,
        ];
    }
}
