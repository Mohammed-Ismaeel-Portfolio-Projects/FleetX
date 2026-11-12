<?php

namespace App\Simulation\Domain\Fuel\DTOs;

/**
 * FuelConsumptionOutput DTO.
 * Contains calculated fuel consumption metrics.
 * Strategies return this, FuelModule applies it.
 * Extended with energy metrics for future electric vehicle support.
 */
readonly class FuelConsumptionOutput
{
    public function __construct(
        public float $fuelFlow, // liters per second
        public float $litersPerHour, // liters per hour
        public float $consumptionRate, // percentage per hour
        public float $estimatedRemainingRange, // kilometers
        public string $strategy, // strategy that calculated this
        public float $energyConsumed, // energy consumed (kWh or equivalent)
        public string $energyUnit, // kWh, MJ, or equivalent
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'fuel_flow' => $this->fuelFlow,
            'liters_per_hour' => $this->litersPerHour,
            'consumption_rate' => $this->consumptionRate,
            'estimated_remaining_range' => $this->estimatedRemainingRange,
            'strategy' => $this->strategy,
            'energy_consumed' => $this->energyConsumed,
            'energy_unit' => $this->energyUnit,
        ];
    }
}
