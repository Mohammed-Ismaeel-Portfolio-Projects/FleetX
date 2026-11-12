<?php

namespace App\Simulation\Domain\Route\Services;

use App\Simulation\Domain\Route\Models\Route;
use App\Simulation\Domain\Route\Models\RouteProgress;
use App\Simulation\Domain\Route\Models\RoutePosition;

/**
 * RouteCompletionDetector Service.
 * Detects segment changes, waypoint reached, and route completion.
 */
class RouteCompletionDetector
{
    /**
     * Detect route events.
     */
    public function detect(
        Route $route,
        RouteProgress $progress,
        RoutePosition $position,
    ): array {
        $events = [];
        
        // Detect segment change
        $segmentChange = $this->detectSegmentChange($route, $position);
        if ($segmentChange) {
            $events['segment_changed'] = $segmentChange;
        }
        
        // Detect waypoint reached
        $waypointReached = $this->detectWaypointReached($route, $progress, $position);
        if ($waypointReached) {
            $events['waypoint_reached'] = $waypointReached;
        }
        
        // Detect route completion
        $routeCompleted = $this->detectRouteCompletion($route, $progress, $position);
        if ($routeCompleted) {
            $events['route_completed'] = $routeCompleted;
        }
        
        return $events;
    }

    /**
     * Detect segment change.
     */
    private function detectSegmentChange(Route $route, RoutePosition $position): ?array
    {
        if ($position->positionOnSegment >= 1.0) {
            // Move to next segment
            $currentSegmentIndex = $this->findSegmentIndex($route, $position->segmentId);
            $nextSegment = $route->getSegmentByIndex($currentSegmentIndex + 1);
            
            if ($nextSegment) {
                return [
                    'previous_segment_id' => $position->segmentId,
                    'new_segment_id' => $nextSegment->id,
                    'position_on_route' => $nextSegment->startPoint,
                ];
            }
        }
        
        return null;
    }

    /**
     * Detect waypoint reached.
     */
    private function detectWaypointReached(Route $route, RouteProgress $progress, RoutePosition $position): ?array
    {
        foreach ($route->waypoints as $waypoint) {
            if (!$progress->isWaypointCompleted($waypoint->id)) {
                $waypointDistance = $waypoint->positionOnRoute * $route->totalDistance;
                if ($position->distanceFromStart >= $waypointDistance) {
                    return [
                        'waypoint_id' => $waypoint->id,
                        'waypoint_name' => $waypoint->name,
                        'waypoint_type' => $waypoint->type,
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Detect route completion.
     */
    private function detectRouteCompletion(Route $route, RouteProgress $progress, RoutePosition $position): ?array
    {
        if (!$progress->isCompleted && $position->distanceFromStart >= $route->totalDistance) {
            return [
                'route_id' => $route->id,
                'total_distance' => $route->totalDistance,
                'duration' => 0, // TODO: Calculate actual duration
            ];
        }
        
        return null;
    }

    /**
     * Find segment index by ID.
     */
    private function findSegmentIndex(Route $route, string $segmentId): int
    {
        foreach ($route->segments as $index => $segment) {
            if ($segment->id === $segmentId) {
                return $index;
            }
        }
        return 0;
    }
}
