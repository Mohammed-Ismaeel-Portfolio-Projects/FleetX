<?php

namespace App\Simulation\Domain\Route\Models;

/**
 * RouteProgress Model.
 * Tracks vehicle progress along a route.
 */
class RouteProgress
{
    public function __construct(
        public string $routeId,
        public string $currentSegmentId,
        public float $distanceTravelled, // kilometers
        public float $remainingDistance, // kilometers
        public float $progressPercentage, // 0-100
        public array $completedWaypoints, // string[] waypoint IDs
        public array $remainingWaypoints, // string[] waypoint IDs
        public bool $isCompleted = false,
    ) {}

    /**
     * Create initial progress.
     */
    public static function createInitial(Route $route): self
    {
        $firstSegment = $route->getSegmentByIndex(0);
        $waypointIds = array_map(fn($w) => $w->id, $route->waypoints);

        return new self(
            routeId: $route->id,
            currentSegmentId: $firstSegment ? $firstSegment->id : '',
            distanceTravelled: 0.0,
            remainingDistance: $route->totalDistance,
            progressPercentage: 0.0,
            completedWaypoints: [],
            remainingWaypoints: $waypointIds,
            isCompleted: false,
        );
    }

    /**
     * Advance progress by distance.
     */
    public function advance(float $distance): void
    {
        $this->distanceTravelled += $distance;
        $this->remainingDistance -= $distance;
        $this->updateProgressPercentage();
    }

    /**
     * Update current segment.
     */
    public function setCurrentSegment(string $segmentId): void
    {
        $this->currentSegmentId = $segmentId;
    }

    /**
     * Mark waypoint as completed.
     */
    public function completeWaypoint(string $waypointId): void
    {
        $this->completedWaypoints[] = $waypointId;
        $this->remainingWaypoints = array_filter(
            $this->remainingWaypoints,
            fn($id) => $id !== $waypointId
        );
    }

    /**
     * Mark route as completed.
     */
    public function markCompleted(): void
    {
        $this->isCompleted = true;
        $this->progressPercentage = 100.0;
        $this->remainingDistance = 0.0;
    }

    /**
     * Update progress percentage.
     */
    private function updateProgressPercentage(): void
    {
        $totalDistance = $this->distanceTravelled + $this->remainingDistance;
        if ($totalDistance > 0) {
            $this->progressPercentage = ($this->distanceTravelled / $totalDistance) * 100;
        }
    }

    /**
     * Check if waypoint is completed.
     */
    public function isWaypointCompleted(string $waypointId): bool
    {
        return in_array($waypointId, $this->completedWaypoints);
    }
}
