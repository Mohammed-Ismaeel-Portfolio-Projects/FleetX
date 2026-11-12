<?php

namespace App\Simulation\Domain\Route\Services;

use App\Simulation\Domain\Route\Models\Route;
use App\Simulation\Domain\Route\Models\RoutePosition;

/**
 * RouteInterpolator Service.
 * Calculates logical position along current segment.
 */
class RouteInterpolator
{
    /**
     * Interpolate position on segment.
     */
    public function interpolate(
        Route $route,
        RoutePosition $position,
    ): array {
        $currentSegment = $route->getSegment($position->segmentId);
        
        if (!$currentSegment) {
            return [
                'position_on_segment' => 0.0,
                'position_on_route' => 0.0,
                'distance_on_segment' => 0.0,
            ];
        }
        
        // Calculate distance on current segment
        $distanceOnSegment = $position->positionOnSegment * $currentSegment->length;
        
        // Calculate overall route position
        $positionOnRoute = $this->calculateRoutePosition($route, $position);
        
        return [
            'position_on_segment' => $position->positionOnSegment,
            'position_on_route' => $positionOnRoute,
            'distance_on_segment' => $distanceOnSegment,
        ];
    }

    /**
     * Calculate overall route position (0-1).
     */
    private function calculateRoutePosition(Route $route, RoutePosition $position): float
    {
        if ($route->totalDistance <= 0) {
            return 0.0;
        }
        
        return $position->distanceFromStart / $route->totalDistance;
    }

    /**
     * Check if position is at segment end.
     */
    public function isAtSegmentEnd(RoutePosition $position): bool
    {
        return $position->positionOnSegment >= 1.0;
    }

    /**
     * Check if position is at route end.
     */
    public function isAtRouteEnd(Route $route, RoutePosition $position): bool
    {
        return $position->distanceFromStart >= $route->totalDistance;
    }
}
