<?php

namespace App\Simulation\Domain\Vehicle\Models;

/**
 * VehicleState Model - Dynamic Realtime State.
 * Represents current vehicle operational state that changes during simulation.
 */
class VehicleState
{
    public function __construct(
        public string $vehicleId,
        public string $engineStatus, // on, off, starting, stopping
        public string $ignitionStatus, // on, off
        public string $vehicleStatus, // idle, moving, parked, stopped, maintenance
        public float $currentSpeed, // in km/h
        public int $currentGear,
        public float $rpm,
        public float $throttlePosition, // 0-100 percentage
        public float $fuelLevel, // in liters
        public float $fuelConsumptionRate, // in liters/hour
        public float $coolantTemperature, // in Celsius
        public float $oilPressure, // in PSI
        public float $batteryVoltage, // in volts
        public float $engineLoad, // 0-100 percentage
        public float $odometer, // in km
        public float $engineHours, // in hours
        public \DateTime $lastUpdateTime,
    ) {}

    /**
     * Create initial state for a vehicle.
     */
    public static function createInitial(string $vehicleId): self
    {
        return new self(
            vehicleId: $vehicleId,
            engineStatus: 'off',
            ignitionStatus: 'off',
            vehicleStatus: 'parked',
            currentSpeed: 0.0,
            currentGear: 0,
            rpm: 0.0,
            throttlePosition: 0.0,
            fuelLevel: 0.0,
            fuelConsumptionRate: 0.0,
            coolantTemperature: 20.0,
            oilPressure: 0.0,
            batteryVoltage: 12.6,
            engineLoad: 0.0,
            odometer: 0.0,
            engineHours: 0.0,
            lastUpdateTime: new \DateTime(),
        );
    }
}
