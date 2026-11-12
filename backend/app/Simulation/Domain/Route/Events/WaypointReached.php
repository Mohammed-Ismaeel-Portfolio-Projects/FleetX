<?php

namespace App\Simulation\Domain\Route\Events;

/**
 * WaypointReached Event.
 * Published when a vehicle reaches a waypoint.
 */
readonly class WaypointReached
{
    public function __construct(
        public string $vehicleId,
        public string $routeId,
        public string $waypointId,
        public string $waypointName,
        public string $waypointType,
        public float $distanceTravelled,
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
            'waypoint_id' => $this->waypointId,
            'waypoint_name' => $this->waypointName,
            'waypoint_type' => $this->waypointType,
            'distance_travelled' => $this->distanceTravelled,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
