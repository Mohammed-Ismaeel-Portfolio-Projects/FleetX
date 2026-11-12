<?php

namespace App\Simulation\Domain\Environment\Services;

use App\Simulation\Domain\Environment\Models\EnvironmentState;
use App\Simulation\Domain\Environment\Models\Weather;
use App\Simulation\Domain\Environment\Models\RoadCondition;
use App\Simulation\Domain\Environment\Models\TrafficCondition;
use App\Simulation\Domain\Environment\Models\Visibility;
use App\Simulation\Domain\Environment\Models\SurfaceCondition;

/**
 * EnvironmentMerger Service.
 * Combines all environment components into a single immutable EnvironmentState.
 */
class EnvironmentMerger
{
    /**
     * Merge environment components into EnvironmentState.
     */
    public function merge(
        Weather $weather,
        float $temperature,
        float $humidity,
        float $windSpeed,
        Visibility $visibility,
        RoadCondition $roadCondition,
        TrafficCondition $trafficCondition,
        SurfaceCondition $surfaceCondition,
    ): EnvironmentState {
        // Calculate road friction from surface condition
        $roadFriction = $surfaceCondition->frictionCoefficient;

        return new EnvironmentState(
            weather: $weather,
            temperature: $temperature,
            humidity: $humidity,
            windSpeed: $windSpeed,
            visibility: $visibility,
            roadCondition: $roadCondition,
            roadFriction: $roadFriction,
            trafficCondition: $trafficCondition,
            surfaceCondition: $surfaceCondition,
            timestamp: new \DateTime(),
        );
    }
}
