<?php

namespace App\Simulation\Domain\Route\Services;

use App\Simulation\Domain\Route\Models\Route;
use App\Simulation\Domain\Route\Models\RouteProgress;
use App\Simulation\Domain\Route\Models\RoutePosition;
use App\Simulation\Core\SimulationClock;

/**
 * RouteAdvancer Service.
 * Advances vehicle progress along route based on speed and time.
 */
class RouteAdvancer
{
    /**
     * Advance route progress.
     */
    public function advance(
        Route $route,
        RouteProgress $progress,
        RoutePosition $position,
        float $speed, // km/h
        float $elapsedMs, // milliseconds
    ): array {
        // Calculate distance advanced
        $distanceAdvanced = $this->calculateDistanceAdvanced($speed, $elapsedMs);
        
        // Update progress
        $progress->advance($distanceAdvanced);
        
        // Update position
        $currentSegment = $route->getSegment($progress->currentSegmentId);
        if ($currentSegment) {
            $position->advance($distanceAdvanced, $currentSegment);
        }
        
        return [
            'distance_advanced' => $distanceAdvanced,
            'progress' => $progress,
            'position' => $position,
        ];
    }

    /**
     * Calculate distance advanced based on speed and time.
     */
    private function calculateDistanceAdvanced(float $speed, float $elapsedMs): float
    {
        $elapsedHours = $elapsedMs / 1000 / 3600;
        return $speed * $elapsedHours; // kilometers
    }
}
