<?php

namespace App\Simulation\Domain\Environment\Services;

use App\Simulation\Domain\Environment\Models\Visibility;
use App\Simulation\Domain\Environment\Models\Weather;

/**
 * VisibilityCalculator Service.
 * Calculates visibility from weather.
 */
class VisibilityCalculator
{
    /**
     * Calculate visibility from weather.
     */
    public function calculate(Weather $weather): Visibility
    {
        // TODO: Implement realistic visibility calculation
        // This is architecture preparation only
        
        return match($weather->weatherType) {
            'clear' => Visibility::excellent(),
            'cloudy' => Visibility::good(),
            'rain' => $this->calculateRainVisibility($weather->intensity),
            'storm' => $this->calculateStormVisibility($weather->intensity),
            'fog' => $this->calculateFogVisibility($weather->intensity),
            'snow' => $this->calculateSnowVisibility($weather->intensity),
            default => Visibility::excellent(),
        };
    }

    /**
     * Calculate rain visibility.
     */
    private function calculateRainVisibility(float $intensity): Visibility
    {
        if ($intensity > 0.7) {
            return Visibility::poor();
        }
        return Visibility::good();
    }

    /**
     * Calculate storm visibility.
     */
    private function calculateStormVisibility(float $intensity): Visibility
    {
        if ($intensity > 0.8) {
            return Visibility::veryPoor();
        }
        return Visibility::poor();
    }

    /**
     * Calculate fog visibility.
     */
    private function calculateFogVisibility(float $intensity): Visibility
    {
        if ($intensity > 0.8) {
            return Visibility::veryPoor();
        }
        if ($intensity > 0.5) {
            return Visibility::poor();
        }
        return Visibility::good();
    }

    /**
     * Calculate snow visibility.
     */
    private function calculateSnowVisibility(float $intensity): Visibility
    {
        if ($intensity > 0.7) {
            return Visibility::poor();
        }
        return Visibility::good();
    }
}
