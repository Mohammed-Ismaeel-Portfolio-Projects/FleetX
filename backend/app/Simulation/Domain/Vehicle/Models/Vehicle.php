<?php

namespace App\Simulation\Domain\Vehicle\Models;

/**
 * Vehicle Model - Static Information.
 * Represents immutable vehicle characteristics and specifications.
 */
readonly class Vehicle
{
    public function __construct(
        public string $id,
        public string $vin,
        public string $plateNumber,
        public string $model,
        public string $manufacturer,
        public int $year,
        public string $fuelType,
        public float $fuelTankCapacity, // in liters
        public float $maximumSpeed, // in km/h
        public string $engineType,
        public string $vehicleType,
        public int $axleCount,
        public float $payloadCapacity, // in kg
    ) {}
}
