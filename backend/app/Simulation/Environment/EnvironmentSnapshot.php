<?php

namespace App\Simulation\Environment;

/**
 * EnvironmentSnapshot
 *
 * Immutable external road conditions for one vehicle / tick.
 * Influences movement only via recommendedTargetSpeedKmh.
 */
readonly class EnvironmentSnapshot
{
    public function __construct(
        public string $routeId,
        public string $vehicleId,
        public string $timestamp,
        public float $roadSpeedLimitKmh,
        public float $trafficDensity,
        public float $trafficFactor,
        public string $weatherCondition,
        public string $roadCondition,
        public ?string $roadEvent,
        public bool $constructionZone,
        public bool $accidentNearby,
        public bool $roadClosed,
        public bool $schoolZone,
        public bool $tollGateAhead,
        public bool $borderCrossingAhead,
        public float $recommendedTargetSpeedKmh,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'route_id' => $this->routeId,
            'vehicle_id' => $this->vehicleId,
            'timestamp' => $this->timestamp,
            'road_speed_limit_kmh' => $this->roadSpeedLimitKmh,
            'traffic_density' => $this->trafficDensity,
            'traffic_factor' => $this->trafficFactor,
            'weather_condition' => $this->weatherCondition,
            'road_condition' => $this->roadCondition,
            'road_event' => $this->roadEvent,
            'construction_zone' => $this->constructionZone,
            'accident_nearby' => $this->accidentNearby,
            'road_closed' => $this->roadClosed,
            'school_zone' => $this->schoolZone,
            'toll_gate_ahead' => $this->tollGateAhead,
            'border_crossing_ahead' => $this->borderCrossingAhead,
            'recommended_target_speed_kmh' => $this->recommendedTargetSpeedKmh,
        ];
    }

    /**
     * Compact reference embedded into telemetry registry payloads.
     *
     * @return array<string, mixed>
     */
    public function toTelemetryReference(): array
    {
        return [
            'road_speed_limit_kmh' => $this->roadSpeedLimitKmh,
            'traffic_density' => $this->trafficDensity,
            'traffic_factor' => $this->trafficFactor,
            'recommended_target_speed_kmh' => $this->recommendedTargetSpeedKmh,
            'road_event' => $this->roadEvent,
            'weather_condition' => $this->weatherCondition,
            'road_condition' => $this->roadCondition,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            routeId: (string) ($data['route_id'] ?? ''),
            vehicleId: (string) ($data['vehicle_id'] ?? ''),
            timestamp: (string) ($data['timestamp'] ?? now()->toIso8601String()),
            roadSpeedLimitKmh: (float) ($data['road_speed_limit_kmh'] ?? 90),
            trafficDensity: (float) ($data['traffic_density'] ?? 0.3),
            trafficFactor: (float) ($data['traffic_factor'] ?? 1.0),
            weatherCondition: (string) ($data['weather_condition'] ?? 'clear'),
            roadCondition: (string) ($data['road_condition'] ?? 'dry'),
            roadEvent: isset($data['road_event']) ? (is_string($data['road_event']) ? $data['road_event'] : null) : null,
            constructionZone: (bool) ($data['construction_zone'] ?? false),
            accidentNearby: (bool) ($data['accident_nearby'] ?? false),
            roadClosed: (bool) ($data['road_closed'] ?? false),
            schoolZone: (bool) ($data['school_zone'] ?? false),
            tollGateAhead: (bool) ($data['toll_gate_ahead'] ?? false),
            borderCrossingAhead: (bool) ($data['border_crossing_ahead'] ?? false),
            recommendedTargetSpeedKmh: (float) ($data['recommended_target_speed_kmh'] ?? 80),
        );
    }
}
