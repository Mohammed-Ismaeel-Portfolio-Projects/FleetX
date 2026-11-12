<?php

namespace App\Simulation\Domain\Fuel\Models;

/**
 * ConsumableReservoir Interface.
 * Abstract concept for consumable resources in vehicles.
 * Implemented by FuelTank, BatteryPack, CoolantReservoir, OilReservoir, AdBlueTank, etc.
 */
interface ConsumableReservoir
{
    /**
     * Get reservoir capacity.
     */
    public function getCapacity(): float;

    /**
     * Get current level.
     */
    public function getCurrentLevel(): float;

    /**
     * Get current level as percentage (0-100).
     */
    public function getPercentage(): float;

    /**
     * Consume from reservoir.
     */
    public function consume(float $amount): void;

    /**
     * Refill reservoir.
     */
    public function refill(float $amount): void;

    /**
     * Check if reservoir is empty.
     */
    public function isEmpty(): bool;

    /**
     * Check if reservoir is low (below threshold).
     */
    public function isLow(): bool;

    /**
     * Get reservoir type identifier.
     */
    public function getType(): string;

    /**
     * Get unit of measurement.
     */
    public function getUnit(): string;
}
