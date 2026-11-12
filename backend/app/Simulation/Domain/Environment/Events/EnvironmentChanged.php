<?php

namespace App\Simulation\Domain\Environment\Events;

/**
 * EnvironmentChanged Event.
 * Published when environment state changes.
 */
readonly class EnvironmentChanged
{
    public function __construct(
        public string $weatherType,
        public float $temperature,
        public float $humidity,
        public float $windSpeed,
        public float $visibilityDistance,
        public string $roadCondition,
        public float $roadFriction,
        public string $trafficDensity,
        public float $trafficSpeedModifier,
        public string $surfaceType,
        public float $surfaceFriction,
        public \DateTime $timestamp,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'weather_type' => $this->weatherType,
            'temperature' => $this->temperature,
            'humidity' => $this->humidity,
            'wind_speed' => $this->windSpeed,
            'visibility_distance' => $this->visibilityDistance,
            'road_condition' => $this->roadCondition,
            'road_friction' => $this->roadFriction,
            'traffic_density' => $this->trafficDensity,
            'traffic_speed_modifier' => $this->trafficSpeedModifier,
            'surface_type' => $this->surfaceType,
            'surface_friction' => $this->surfaceFriction,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
