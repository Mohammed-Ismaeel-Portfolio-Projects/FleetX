<?php

namespace App\Simulation\Domain\GPS\Services;

/**
 * SpeedProjector Service.
 * Copies current vehicle speed into GPS output.
 */
class SpeedProjector
{
    /**
     * Project vehicle speed to GPS output.
     */
    public function project(float $vehicleSpeed): float
    {
        // Direct projection - GPS speed equals vehicle speed
        // In production, this could include speed smoothing or filtering
        return $vehicleSpeed;
    }

    /**
     * Project speed with smoothing.
     */
    public function projectWithSmoothing(float $vehicleSpeed, float $previousSpeed, float $smoothingFactor = 0.5): float
    {
        // Exponential moving average for speed smoothing
        return ($vehicleSpeed * (1 - $smoothingFactor)) + ($previousSpeed * $smoothingFactor);
    }

    /**
     * Project speed with noise (optional).
     */
    public function projectWithNoise(float $vehicleSpeed, float $noiseMagnitude = 0.0): float
    {
        if ($noiseMagnitude <= 0.0) {
            return $vehicleSpeed;
        }
        
        $noise = $noiseMagnitude * (rand(-100, 100) / 100);
        return max(0, $vehicleSpeed + $noise);
    }
}
