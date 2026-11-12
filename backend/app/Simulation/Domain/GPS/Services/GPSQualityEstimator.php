<?php

namespace App\Simulation\Domain\GPS\Services;

use App\Simulation\Domain\GPS\Models\GPSSignal;

/**
 * GPSQualityEstimator Service.
 * Calculates GPS signal quality metrics.
 */
class GPSQualityEstimator
{
    /**
     * Estimate GPS signal quality.
     */
    public function estimate(float $baseAccuracy = 5.0): GPSSignal
    {
        // TODO: Implement realistic quality estimation
        // This is architecture preparation only
        
        // Placeholder: Return default quality metrics
        return new GPSSignal(
            accuracy: $baseAccuracy, // meters
            hdop: 1.0, // Horizontal Dilution of Precision
            satelliteCount: 12, // Number of satellites
            signalQuality: $this->determineQuality($baseAccuracy),
            fixType: '3d', // 3D fix
        );
    }

    /**
     * Estimate quality with environmental factors.
     */
    public function estimateWithFactors(
        float $baseAccuracy,
        string $environment = 'open', // open, urban, canyon, indoor
        float $speed = 0.0, // km/h
    ): GPSSignal {
        // TODO: Implement factor-based quality estimation
        // This would adjust accuracy based on environment and speed
        
        $adjustedAccuracy = $baseAccuracy;
        
        // Adjust for environment
        $environmentFactor = match($environment) {
            'open' => 1.0,
            'urban' => 2.0,
            'canyon' => 3.0,
            'indoor' => 5.0,
            default => 1.0,
        };
        
        $adjustedAccuracy *= $environmentFactor;
        
        // Adjust for speed (higher speed = slightly worse accuracy)
        if ($speed > 100) {
            $adjustedAccuracy *= 1.2;
        }
        
        // Adjust satellite count based on environment
        $satelliteCount = match($environment) {
            'open' => 12,
            'urban' => 8,
            'canyon' => 6,
            'indoor' => 4,
            default => 12,
        };
        
        // Adjust HDOP based on satellite count
        $hdop = 24.0 / $satelliteCount; // Rough approximation
        
        return new GPSSignal(
            accuracy: $adjustedAccuracy,
            hdop: $hdop,
            satelliteCount: $satelliteCount,
            signalQuality: $this->determineQuality($adjustedAccuracy),
            fixType: $satelliteCount >= 4 ? '3d' : '2d',
        );
    }

    /**
     * Determine signal quality based on accuracy.
     */
    private function determineQuality(float $accuracy): string
    {
        if ($accuracy <= 3.0) {
            return 'excellent';
        }
        if ($accuracy <= 5.0) {
            return 'good';
        }
        if ($accuracy <= 10.0) {
            return 'fair';
        }
        return 'poor';
    }
}
