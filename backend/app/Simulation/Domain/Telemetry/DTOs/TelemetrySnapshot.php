<?php

namespace App\Simulation\Domain\Telemetry\DTOs;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;

/**
 * TelemetrySnapshot DTO.
 * Immutable snapshot of vehicle telemetry data at a point in time.
 * Contains only existing values from Vehicle, VehicleState, and VehicleRuntime.
 */
readonly class TelemetrySnapshot
{
    public function __construct(
        public string $vehicleId,
        public \DateTime $simulationTime,
        public float $currentSpeed, // km/h
        public float $rpm,
        public int $gear,
        public float $throttlePosition, // 0-100%
        public float $engineLoad, // 0-100%
        public float $fuelLevel, // 0-100%
        public float $batteryVoltage, // volts
        public string $vehicleStatus, // active, inactive, etc.
        public VehicleSimulationState $simulationState,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'simulation_time' => $this->simulationTime->format('Y-m-d H:i:s.u'),
            'current_speed' => $this->currentSpeed,
            'rpm' => $this->rpm,
            'gear' => $this->gear,
            'throttle_position' => $this->throttlePosition,
            'engine_load' => $this->engineLoad,
            'fuel_level' => $this->fuelLevel,
            'battery_voltage' => $this->batteryVoltage,
            'vehicle_status' => $this->vehicleStatus,
            'simulation_state' => $this->simulationState->value,
        ];
    }
}
