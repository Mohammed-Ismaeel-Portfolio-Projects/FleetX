<?php

namespace App\Simulation\Telemetry;

/**
 * TelemetrySnapshot
 *
 * Immutable per-vehicle runtime metrics for one simulation tick.
 * Single source of truth for Tracking and downstream consumers.
 */
readonly class TelemetrySnapshot
{
    public function __construct(
        public string $vehicleId,
        public string $shipmentId,
        public string $timestamp,
        public float $latitude,
        public float $longitude,
        public float $heading,
        public float $currentSpeedKmh,
        public float $targetSpeedKmh,
        public float $acceleration,
        public string $vehicleState,
        public float $distanceTravelledMeters,
        public float $remainingDistanceMeters,
        public float $completionPercentage,
        public int $currentSegment,
        public int $polylineIndex,
        public float $drivingTimeSeconds,
        public float $idleTimeSeconds,
        public float $engineRuntimeSeconds,
        public float $averageSpeedKmh,
        public float $odometerMeters,
        public float $gpsAccuracy,
        public string $signalQuality,
        // Placeholders — future Fuel / Engine Health / Predictive Maintenance
        public mixed $fuelLevel = null,
        public mixed $fuelConsumptionRate = null,
        public mixed $engineTemperature = null,
        public mixed $engineHealth = null,
        public mixed $batteryLevel = null,
        public bool $fromFallbackCorridor = false,
        public bool $routeCompleted = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'shipment_id' => $this->shipmentId,
            'timestamp' => $this->timestamp,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'heading' => $this->heading,
            'current_speed_kmh' => $this->currentSpeedKmh,
            'target_speed_kmh' => $this->targetSpeedKmh,
            'acceleration' => $this->acceleration,
            'vehicle_state' => $this->vehicleState,
            'distance_travelled_meters' => $this->distanceTravelledMeters,
            'remaining_distance_meters' => $this->remainingDistanceMeters,
            'completion_percentage' => $this->completionPercentage,
            'current_segment' => $this->currentSegment,
            'polyline_index' => $this->polylineIndex,
            'driving_time_seconds' => $this->drivingTimeSeconds,
            'idle_time_seconds' => $this->idleTimeSeconds,
            'engine_runtime_seconds' => $this->engineRuntimeSeconds,
            'average_speed_kmh' => $this->averageSpeedKmh,
            'odometer_meters' => $this->odometerMeters,
            'gps_accuracy' => $this->gpsAccuracy,
            'signal_quality' => $this->signalQuality,
            'fuel_level' => $this->fuelLevel,
            'fuel_consumption_rate' => $this->fuelConsumptionRate,
            'engine_temperature' => $this->engineTemperature,
            'engine_health' => $this->engineHealth,
            'battery_level' => $this->batteryLevel,
            'from_fallback_corridor' => $this->fromFallbackCorridor,
            'route_completed' => $this->routeCompleted,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vehicleId: (string) ($data['vehicle_id'] ?? ''),
            shipmentId: (string) ($data['shipment_id'] ?? ''),
            timestamp: (string) ($data['timestamp'] ?? now()->toIso8601String()),
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
            heading: (float) ($data['heading'] ?? 0),
            currentSpeedKmh: (float) ($data['current_speed_kmh'] ?? 0),
            targetSpeedKmh: (float) ($data['target_speed_kmh'] ?? 0),
            acceleration: (float) ($data['acceleration'] ?? 0),
            vehicleState: (string) ($data['vehicle_state'] ?? 'offline'),
            distanceTravelledMeters: (float) ($data['distance_travelled_meters'] ?? 0),
            remainingDistanceMeters: (float) ($data['remaining_distance_meters'] ?? 0),
            completionPercentage: (float) ($data['completion_percentage'] ?? 0),
            currentSegment: (int) ($data['current_segment'] ?? 0),
            polylineIndex: (int) ($data['polyline_index'] ?? 0),
            drivingTimeSeconds: (float) ($data['driving_time_seconds'] ?? 0),
            idleTimeSeconds: (float) ($data['idle_time_seconds'] ?? 0),
            engineRuntimeSeconds: (float) ($data['engine_runtime_seconds'] ?? 0),
            averageSpeedKmh: (float) ($data['average_speed_kmh'] ?? 0),
            odometerMeters: (float) ($data['odometer_meters'] ?? 0),
            gpsAccuracy: (float) ($data['gps_accuracy'] ?? 5),
            signalQuality: (string) ($data['signal_quality'] ?? 'unknown'),
            fuelLevel: $data['fuel_level'] ?? null,
            fuelConsumptionRate: $data['fuel_consumption_rate'] ?? null,
            engineTemperature: $data['engine_temperature'] ?? null,
            engineHealth: $data['engine_health'] ?? null,
            batteryLevel: $data['battery_level'] ?? null,
            fromFallbackCorridor: (bool) ($data['from_fallback_corridor'] ?? false),
            routeCompleted: (bool) ($data['route_completed'] ?? false),
        );
    }
}
