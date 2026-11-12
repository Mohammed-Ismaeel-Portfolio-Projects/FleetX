<?php

namespace App\Simulation\Routing\ValueObjects;

/**
 * SpeedLimit Value Object.
 * Represents a speed limit.
 * Immutable value object following DDD principles.
 */
readonly class SpeedLimit
{
    private float $kilometersPerHour;

    private function __construct(float $kilometersPerHour)
    {
        if ($kilometersPerHour < 0) {
            throw new \InvalidArgumentException('Speed limit cannot be negative.');
        }
        $this->kilometersPerHour = $kilometersPerHour;
    }

    /**
     * Create from km/h.
     */
    public static function fromKilometersPerHour(float $kph): self
    {
        return new self($kph);
    }

    /**
     * Create from mph.
     */
    public static function fromMilesPerHour(float $mph): self
    {
        return new self($mph * 1.609344);
    }

    /**
     * Create from m/s.
     */
    public static function fromMetersPerSecond(float $mps): self
    {
        return new self($mps * 3.6);
    }

    /**
     * Get speed in km/h.
     */
    public function toKilometersPerHour(): float
    {
        return $this->kilometersPerHour;
    }

    /**
     * Get speed in mph.
     */
    public function toMilesPerHour(): float
    {
        return $this->kilometersPerHour / 1.609344;
    }

    /**
     * Get speed in m/s.
     */
    public function toMetersPerSecond(): float
    {
        return $this->kilometersPerHour / 3.6;
    }

    /**
     * Check if unlimited (no speed limit).
     */
    public function isUnlimited(): bool
    {
        return $this->kilometersPerHour >= 999; // Convention for unlimited
    }

    /**
     * Check equality with another speed limit.
     */
    public function equals(SpeedLimit $other): bool
    {
        return abs($this->kilometersPerHour - $other->kilometersPerHour) < 0.1;
    }

    /**
     * Check if greater than another speed limit.
     */
    public function greaterThan(SpeedLimit $other): bool
    {
        return $this->kilometersPerHour > $other->kilometersPerHour;
    }

    /**
     * Check if less than another speed limit.
     */
    public function lessThan(SpeedLimit $other): bool
    {
        return $this->kilometersPerHour < $other->kilometersPerHour;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'kilometers_per_hour' => $this->kilometersPerHour,
            'miles_per_hour' => $this->toMilesPerHour(),
            'meters_per_second' => $this->toMetersPerSecond(),
        ];
    }
}
