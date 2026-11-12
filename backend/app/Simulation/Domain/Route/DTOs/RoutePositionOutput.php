<?php

namespace App\Simulation\Domain\Route\DTOs;

/**
 * RoutePositionOutput DTO.
 * Output from route processing containing position and progress.
 */
readonly class RoutePositionOutput
{
    public function __construct(
        public string $routeId,
        public string $segmentId,
        public float $positionOnSegment,
        public float $positionOnRoute,
        public float $distanceFromStart,
        public float $distanceTravelled,
        public float $remainingDistance,
        public float $progressPercentage,
        public array $completedWaypoints,
        public array $remainingWaypoints,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'route_id' => $this->routeId,
            'segment_id' => $this->segmentId,
            'position_on_segment' => $this->positionOnSegment,
            'position_on_route' => $this->positionOnRoute,
            'distance_from_start' => $this->distanceFromStart,
            'distance_travelled' => $this->distanceTravelled,
            'remaining_distance' => $this->remainingDistance,
            'progress_percentage' => $this->progressPercentage,
            'completed_waypoints' => $this->completedWaypoints,
            'remaining_waypoints' => $this->remainingWaypoints,
        ];
    }
}
