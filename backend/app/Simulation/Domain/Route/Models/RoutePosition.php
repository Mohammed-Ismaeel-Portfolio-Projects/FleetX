<?php

namespace App\Simulation\Domain\Route\Models;

/**
 * RoutePosition Model.
 * Represents logical position along a route.
 * No GPS coordinates - only logical position.
 */
class RoutePosition
{
    public function __construct(
        public string $routeId,
        public string $segmentId,
        public float $positionOnSegment, // 0-1 within segment
        public float $positionOnRoute, // 0-1 overall route
        public float $distanceFromStart, // kilometers
    ) {}

    /**
     * Create initial position.
     */
    public static function createInitial(Route $route): self
    {
        $firstSegment = $route->getSegmentByIndex(0);

        return new self(
            routeId: $route->id,
            segmentId: $firstSegment ? $firstSegment->id : '',
            positionOnSegment: 0.0,
            positionOnRoute: 0.0,
            distanceFromStart: 0.0,
        );
    }

    /**
     * Advance position by distance.
     */
    public function advance(float $distance, RouteSegment $segment): void
    {
        $this->distanceFromStart += $distance;
        
        // Calculate new position on segment
        $segmentProgress = $distance / $segment->length;
        $this->positionOnSegment = min(1.0, $this->positionOnSegment + $segmentProgress);
    }

    /**
     * Move to next segment.
     */
    public function moveToSegment(string $segmentId, float $positionOnSegment, float $positionOnRoute): void
    {
        $this->segmentId = $segmentId;
        $this->positionOnSegment = $positionOnSegment;
        $this->positionOnRoute = $positionOnRoute;
    }

    /**
     * Update overall route position.
     */
    public function updateRoutePosition(float $positionOnRoute): void
    {
        $this->positionOnRoute = $positionOnRoute;
    }
}
