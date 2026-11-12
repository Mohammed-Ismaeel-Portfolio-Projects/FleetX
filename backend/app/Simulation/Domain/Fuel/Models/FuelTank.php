<?php

namespace App\Simulation\Domain\Fuel\Models;

/**
 * FuelTank Model.
 * Represents vehicle fuel tank state and capacity.
 * Implements ConsumableReservoir interface for multi-energy support.
 */
class FuelTank implements ConsumableReservoir
{
    public function __construct(
        public string $vehicleId,
        public float $capacity, // liters
        public float $currentLevel, // liters
        public string $fuelType, // diesel, petrol, hybrid, electric
        public \DateTime $lastRefuelTime,
        public float $currentFuelPercentage, // 0-100%
    ) {}

    /**
     * Create initial fuel tank.
     */
    public static function createInitial(string $vehicleId, float $capacity, string $fuelType): self
    {
        return new self(
            vehicleId: $vehicleId,
            capacity: $capacity,
            currentLevel: $capacity,
            fuelType: $fuelType,
            lastRefuelTime: new \DateTime(),
            currentFuelPercentage: 100.0,
        );
    }

    /**
     * Get remaining fuel in liters.
     */
    public function getRemainingFuel(): float
    {
        return $this->currentLevel;
    }

    /**
     * Get fuel percentage.
     */
    public function getFuelPercentage(): float
    {
        return $this->currentFuelPercentage;
    }

    /**
     * Consume fuel.
     */
    public function consumeFuel(float $liters): void
    {
        $this->currentLevel = max(0, $this->currentLevel - $liters);
        $this->currentFuelPercentage = ($this->currentLevel / $this->capacity) * 100;
    }

    /**
     * Refuel fuel tank.
     */
    public function refuel(float $liters): void
    {
        $this->currentLevel = min($this->capacity, $this->currentLevel + $liters);
        $this->currentFuelPercentage = ($this->currentLevel / $this->capacity) * 100;
        $this->lastRefuelTime = new \DateTime();
    }

    /**
     * Check if empty.
     */
    public function isEmpty(): bool
    {
        return $this->currentLevel <= 0;
    }

    /**
     * Check if low fuel (below 10%).
     */
    public function isLowFuel(): bool
    {
        return $this->currentFuelPercentage < 10;
    }

    // ConsumableReservoir Interface Implementation

    public function getCapacity(): float
    {
        return $this->capacity;
    }

    public function getCurrentLevel(): float
    {
        return $this->currentLevel;
    }

    public function getPercentage(): float
    {
        return $this->currentFuelPercentage;
    }

    public function consume(float $amount): void
    {
        $this->consumeFuel($amount);
    }

    public function refill(float $amount): void
    {
        $this->refuel($amount);
    }

    public function isLow(): bool
    {
        return $this->isLowFuel();
    }

    public function getType(): string
    {
        return 'fuel_tank';
    }

    public function getUnit(): string
    {
        return 'liters';
    }
}
