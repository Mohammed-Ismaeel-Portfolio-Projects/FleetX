<?php

namespace App\Simulation\Domain\Route\Events;

/**
 * RouteStarted Event.
 * Published when a vehicle starts a route.
 */
readonly class RouteStarted
{
    public function __construct(
        public string $vehicleId,
        public string $routeId,
        public string $origin,
        public string $destination,
        public float $totalDistance,
        public \DateTime $timestamp,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'route_id' => $this->routeId,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'total_distance' => $this->totalDistance,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
