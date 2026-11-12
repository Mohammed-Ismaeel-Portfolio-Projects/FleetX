<?php

namespace App\Simulation\Domain\Environment\Services;

use App\Simulation\Domain\Environment\Models\RoadCondition;
use App\Simulation\Domain\Environment\Models\Weather;

/**
 * RoadConditionCalculator Service.
 * Calculates road condition from weather.
 */
class RoadConditionCalculator
{
    /**
     * Calculate road condition from weather.
     */
    public function calculate(Weather $weather): RoadCondition
    {
        // TODO: Implement realistic road condition calculation
        // This is architecture preparation only
        
        return match($weather->weatherType) {
            'clear', 'cloudy' => RoadCondition::dry(),
            'rain' => $this->calculateRainCondition($weather->intensity),
            'storm' => $this->calculateStormCondition($weather->intensity),
            'fog' => RoadCondition::dry(), // Fog doesn't affect road condition
            'snow' => $this->calculateSnowCondition($weather->intensity),
            default => RoadCondition::dry(),
        };
    }

    /**
     * Calculate rain condition.
     */
    private function calculateRainCondition(float $intensity): RoadCondition
    {
        if ($intensity > 0.7) {
            return RoadCondition::flooded();
        }
        return RoadCondition::wet();
    }

    /**
     * Calculate storm condition.
     */
    private function calculateStormCondition(float $intensity): RoadCondition
    {
        if ($intensity > 0.8) {
            return RoadCondition::flooded();
        }
        return RoadCondition::wet();
    }

    /**
     * Calculate snow condition.
     */
    private function calculateSnowCondition(float $intensity): RoadCondition
    {
        if ($intensity > 0.6) {
            return RoadCondition::icy();
        }
        return RoadCondition::wet();
    }
}
