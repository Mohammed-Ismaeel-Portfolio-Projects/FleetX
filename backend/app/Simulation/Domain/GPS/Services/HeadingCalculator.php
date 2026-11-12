<?php

namespace App\Simulation\Domain\GPS\Services;

use App\Simulation\Domain\GPS\Models\Coordinate;

/**
 * HeadingCalculator Service.
 * Calculates heading using previous and current positions.
 */
class HeadingCalculator
{
    /**
     * Calculate heading between two coordinates.
     */
    public function calculate(Coordinate $previous, Coordinate $current): float
    {
        // TODO: Implement accurate heading calculation using great circle bearing
        // This is architecture preparation only
        
        // Placeholder: Simple calculation based on coordinate differences
        $latDiff = $current->latitude - $previous->latitude;
        $lonDiff = $current->longitude - $previous->longitude;
        
        // Simple bearing calculation
        $heading = atan2($lonDiff, $latDiff) * 180 / M_PI;
        
        // Normalize to 0-360 degrees
        if ($heading < 0) {
            $heading += 360;
        }
        
        // Adjust so 0 degrees = North
        $heading = ($heading + 90) % 360;
        
        return $heading;
    }

    /**
     * Calculate heading using great circle bearing formula.
     */
    public function calculateGreatCircleBearing(Coordinate $previous, Coordinate $current): float
    {
        // TODO: Implement accurate great circle bearing calculation
        // This would use the formula:
        // θ = atan2(sin(Δlong) * cos(lat2), cos(lat1) * sin(lat2) - sin(lat1) * cos(lat2) * cos(Δlong))
        
        return $this->calculate($previous, $current);
    }

    /**
     * Calculate heading from route segment direction.
     */
    public function calculateFromSegment(float $segmentStartLat, float $segmentStartLon, float $segmentEndLat, float $segmentEndLon): float
    {
        $start = new Coordinate($segmentStartLat, $segmentStartLon);
        $end = new Coordinate($segmentEndLat, $segmentEndLon);
        
        return $this->calculate($start, $end);
    }
}
