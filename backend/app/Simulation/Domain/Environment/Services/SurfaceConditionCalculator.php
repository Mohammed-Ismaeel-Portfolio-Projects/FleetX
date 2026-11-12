<?php

namespace App\Simulation\Domain\Environment\Services;

use App\Simulation\Domain\Environment\Models\SurfaceCondition;
use App\Simulation\Domain\Environment\Models\Weather;

/**
 * SurfaceConditionCalculator Service.
 * Calculates road friction from weather and surface type.
 */
class SurfaceConditionCalculator
{
    private string $defaultSurfaceType = 'asphalt';

    /**
     * Calculate surface condition.
     */
    public function calculate(Weather $weather, ?string $surfaceType = null): SurfaceCondition
    {
        $surfaceType = $surfaceType ?? $this->defaultSurfaceType;
        $baseFriction = $this->getBaseFriction($surfaceType);
        $adjustedFriction = $this->adjustFrictionForWeather($baseFriction, $weather);

        return new SurfaceCondition(
            surfaceType: $surfaceType,
            frictionCoefficient: $adjustedFriction,
        );
    }

    /**
     * Get base friction for surface type.
     */
    private function getBaseFriction(string $surfaceType): float
    {
        return match($surfaceType) {
            'asphalt' => 0.8,
            'concrete' => 0.7,
            'gravel' => 0.6,
            'sand' => 0.4,
            'mud' => 0.3,
            default => 0.8,
        };
    }

    /**
     * Adjust friction for weather.
     */
    private function adjustFrictionForWeather(float $baseFriction, Weather $weather): float
    {
        $reduction = 0.0;

        // Reduce friction based on weather
        switch ($weather->weatherType) {
            case 'rain':
                $reduction = 0.2 * $weather->intensity;
                break;
            case 'storm':
                $reduction = 0.3 * $weather->intensity;
                break;
            case 'snow':
                $reduction = 0.4 * $weather->intensity;
                break;
            case 'fog':
                // Fog doesn't affect friction
                $reduction = 0.0;
                break;
            case 'clear':
            case 'cloudy':
            default:
                $reduction = 0.0;
                break;
        }

        return max(0.1, $baseFriction - $reduction);
    }

    /**
     * Set default surface type.
     */
    public function setDefaultSurfaceType(string $surfaceType): void
    {
        $this->defaultSurfaceType = $surfaceType;
    }

    /**
     * Get default surface type.
     */
    public function getDefaultSurfaceType(): string
    {
        return $this->defaultSurfaceType;
    }
}
