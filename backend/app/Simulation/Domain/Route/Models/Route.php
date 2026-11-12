<?php

namespace App\Simulation\Domain\Route\Models;

/**
 * Route Model.
 * Represents a predefined route with segments and waypoints.
 * Source of truth for vehicle movement.
 */
class Route
{
    public function __construct(
        public string $id,
        public string $origin,
        public string $destination,
        public float $totalDistance, // kilometers
        public float $estimatedDuration, // seconds
        public array $segments, // RouteSegment[]
        public array $waypoints, // Waypoint[]
    ) {}

    /**
     * Create route from segments and waypoints.
     */
    public static function create(
        string $id,
        string $origin,
        string $destination,
        array $segments,
        array $waypoints,
    ): self {
        $totalDistance = self::calculateTotalDistance($segments);
        $estimatedDuration = self::calculateEstimatedDuration($segments);

        return new self(
            id: $id,
            origin: $origin,
            destination: $destination,
            totalDistance: $totalDistance,
            estimatedDuration: $estimatedDuration,
            segments: $segments,
            waypoints: $waypoints,
        );
    }

    /**
     * Get segment by ID.
     */
    public function getSegment(string $segmentId): ?RouteSegment
    {
        foreach ($this->segments as $segment) {
            if ($segment->id === $segmentId) {
                return $segment;
            }
        }
        return null;
    }

    /**
     * Get waypoint by ID.
     */
    public function getWaypoint(string $waypointId): ?Waypoint
    {
        foreach ($this->waypoints as $waypoint) {
            if ($waypoint->id === $waypointId) {
                return $waypoint;
            }
        }
        return null;
    }

    /**
     * Get segment by index.
     */
    public function getSegmentByIndex(int $index): ?RouteSegment
    {
        return $this->segments[$index] ?? null;
    }

    /**
     * Get segment count.
     */
    public function getSegmentCount(): int
    {
        return count($this->segments);
    }

    /**
     * Calculate total distance from segments.
     */
    private static function calculateTotalDistance(array $segments): float
    {
        return array_reduce($segments, fn($total, $segment) => $total + $segment->length, 0);
    }

    /**
     * Calculate estimated duration from segments.
     */
    private static function calculateEstimatedDuration(array $segments): float
    {
        return array_reduce($segments, fn($total, $segment) => $total + ($segment->length / $segment->speedLimit) * 3600, 0);
    }
}
