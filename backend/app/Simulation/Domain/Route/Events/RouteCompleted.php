<?php

namespace App\Simulation\Domain\Route\Events;

/**
 * RouteCompleted Event.
 * Published when a vehicle completes a route.
 */
readonly class RouteCompleted
{
    public function __construct(
        public string $vehicleId,
        public string $routeId,
        public string $origin,
        public string $destination,
        public float $totalDistance,
        public float $actualDuration, // seconds
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
            'actual_duration' => $this->actualDuration,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
