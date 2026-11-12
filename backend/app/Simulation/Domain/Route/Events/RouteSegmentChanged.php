<?php

namespace App\Simulation\Domain\Route\Events;

/**
 * RouteSegmentChanged Event.
 * Published when a vehicle moves to a new route segment.
 */
readonly class RouteSegmentChanged
{
    public function __construct(
        public string $vehicleId,
        public string $routeId,
        public string $previousSegmentId,
        public string $newSegmentId,
        public float $positionOnRoute,
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
            'previous_segment_id' => $this->previousSegmentId,
            'new_segment_id' => $this->newSegmentId,
            'position_on_route' => $this->positionOnRoute,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
