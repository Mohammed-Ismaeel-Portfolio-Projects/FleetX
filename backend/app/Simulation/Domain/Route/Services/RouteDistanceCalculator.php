<?php

namespace App\Simulation\Domain\Route\Services;

use App\Simulation\Domain\Route\Models\Route;
use App\Simulation\Domain\Route\Models\RoutePosition;

/**
 * RouteDistanceCalculator Service.
 * Calculates distances along route.
 */
class RouteDistanceCalculator
{
    /**
     * Calculate distance to next waypoint.
     */
    public function distanceToNextWaypoint(Route $route, RoutePosition $position): float
    {
        $progress = $position->distanceFromStart;
        
        foreach ($route->waypoints as $waypoint) {
            $waypointDistance = $waypoint->positionOnRoute * $route->totalDistance;
            if ($waypointDistance > $progress) {
                return $waypointDistance - $progress;
            }
        }
        
        return 0.0; // No more waypoints
    }

    /**
     * Calculate distance to destination.
     */
    public function distanceToDestination(Route $route, RoutePosition $position): float
    {
        return $route->totalDistance - $position->distanceFromStart;
    }

    /**
     * Calculate distance to segment end.
     */
    public function distanceToSegmentEnd(Route $route, RoutePosition $position): float
    {
        $segment = $route->getSegment($position->segmentId);
        if (!$segment) {
            return 0.0;
        }
        
        $distanceOnSegment = $position->positionOnSegment * $segment->length;
        return $segment->length - $distanceOnSegment;
    }
}
