<?php

namespace App\Simulation\Domain\Environment\Models;

/**
 * EnvironmentState Model.
 * Immutable shared environment context.
 * All simulation modules read this, no module modifies it.
 */
readonly class EnvironmentState
{
    public function __construct(
        public Weather $weather,
        public float $temperature, // Celsius
        public float $humidity, // percentage 0-100
        public float $windSpeed, // km/h
        public Visibility $visibility,
        public RoadCondition $roadCondition,
        public float $roadFriction, // 0-1
        public TrafficCondition $trafficCondition,
        public SurfaceCondition $surfaceCondition,
        public \DateTime $timestamp,
    ) {}

    /**
     * Create initial environment state.
     */
    public static function createInitial(
        Weather $weather,
        float $temperature,
        float $humidity,
        float $windSpeed,
    ): self {
        return new self(
            weather: $weather,
            temperature: $temperature,
            humidity: $humidity,
            windSpeed: $windSpeed,
            visibility: Visibility::excellent(),
            roadCondition: RoadCondition::dry(),
            roadFriction: 0.8,
            trafficCondition: TrafficCondition::low(),
            surfaceCondition: SurfaceCondition::asphalt(),
            timestamp: new \DateTime(),
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'weather' => [
                'type' => $this->weather->weatherType,
                'intensity' => $this->weather->intensity,
            ],
            'temperature' => $this->temperature,
            'humidity' => $this->humidity,
            'wind_speed' => $this->windSpeed,
            'visibility' => [
                'distance' => $this->visibility->visibilityDistance,
                'factor' => $this->visibility->visibilityFactor,
            ],
            'road_condition' => $this->roadCondition->condition,
            'road_friction' => $this->roadFriction,
            'traffic_condition' => [
                'density' => $this->trafficCondition->density,
                'speed_modifier' => $this->trafficCondition->averageSpeedModifier,
            ],
            'surface_condition' => [
                'type' => $this->surfaceCondition->surfaceType,
                'friction' => $this->surfaceCondition->frictionCoefficient,
            ],
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
