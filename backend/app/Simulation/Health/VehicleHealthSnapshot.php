<?php

namespace App\Simulation\Health;

/**
 * VehicleHealthSnapshot
 *
 * Immutable vehicle health / fuel state for one simulation tick.
 * Influences dynamics only via recommendedMaxSpeed (+ accel derate).
 */
readonly class VehicleHealthSnapshot
{
    public function __construct(
        public string $vehicleId,
        public float $fuelLevel,
        public float $fuelConsumptionRate,
        public float $engineTemperature,
        public float $engineHealth,
        public float $batteryLevel,
        public float $brakeWear,
        public float $tyreWear,
        public float $maintenanceScore,
        public string $operationalStatus,
        public float $recommendedMaxSpeed,
        public float $accelerationScale = 1.0,
        public float $lastAccelerationMs2 = 0.0,
        public string $timestamp = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'fuel_level' => $this->fuelLevel,
            'fuel_consumption_rate' => $this->fuelConsumptionRate,
            'engine_temperature' => $this->engineTemperature,
            'engine_health' => $this->engineHealth,
            'battery_level' => $this->batteryLevel,
            'brake_wear' => $this->brakeWear,
            'tyre_wear' => $this->tyreWear,
            'maintenance_score' => $this->maintenanceScore,
            'operational_status' => $this->operationalStatus,
            'recommended_max_speed' => $this->recommendedMaxSpeed,
            'acceleration_scale' => $this->accelerationScale,
            'last_acceleration_ms2' => $this->lastAccelerationMs2,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Values mapped onto TelemetrySnapshot placeholders.
     *
     * @return array{
     *     fuel_level: float,
     *     fuel_consumption_rate: float,
     *     engine_temperature: float,
     *     engine_health: float,
     *     battery_level: float
     * }
     */
    public function toTelemetryPlaceholders(): array
    {
        return [
            'fuel_level' => $this->fuelLevel,
            'fuel_consumption_rate' => $this->fuelConsumptionRate,
            'engine_temperature' => $this->engineTemperature,
            'engine_health' => $this->engineHealth,
            'battery_level' => $this->batteryLevel,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vehicleId: (string) ($data['vehicle_id'] ?? ''),
            fuelLevel: (float) ($data['fuel_level'] ?? 80),
            fuelConsumptionRate: (float) ($data['fuel_consumption_rate'] ?? 0),
            engineTemperature: (float) ($data['engine_temperature'] ?? 85),
            engineHealth: (float) ($data['engine_health'] ?? 100),
            batteryLevel: (float) ($data['battery_level'] ?? 90),
            brakeWear: (float) ($data['brake_wear'] ?? 5),
            tyreWear: (float) ($data['tyre_wear'] ?? 5),
            maintenanceScore: (float) ($data['maintenance_score'] ?? 90),
            operationalStatus: (string) ($data['operational_status'] ?? 'available'),
            recommendedMaxSpeed: (float) ($data['recommended_max_speed'] ?? 120),
            accelerationScale: (float) ($data['acceleration_scale'] ?? 1.0),
            lastAccelerationMs2: (float) ($data['last_acceleration_ms2'] ?? 0),
            timestamp: (string) ($data['timestamp'] ?? ''),
        );
    }
}
