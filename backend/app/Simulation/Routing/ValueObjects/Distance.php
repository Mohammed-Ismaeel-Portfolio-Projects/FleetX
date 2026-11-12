<?php

namespace App\Simulation\Routing\ValueObjects;

/**
 * Distance Value Object.
 * Represents a distance measurement.
 * Immutable value object following DDD principles.
 */
readonly class Distance
{
    private float $meters;

    private function __construct(float $meters)
    {
        if ($meters < 0) {
            throw new \InvalidArgumentException('Distance cannot be negative.');
        }
        $this->meters = $meters;
    }

    /**
     * Create from meters.
     */
    public static function fromMeters(float $meters): self
    {
        return new self($meters);
    }

    /**
     * Create from kilometers.
     */
    public static function fromKilometers(float $kilometers): self
    {
        return new self($kilometers * 1000);
    }

    /**
     * Create from miles.
     */
    public static function fromMiles(float $miles): self
    {
        return new self($miles * 1609.344);
    }

    /**
     * Get distance in meters.
     */
    public function toMeters(): float
    {
        return $this->meters;
    }

    /**
     * Get distance in kilometers.
     */
    public function toKilometers(): float
    {
        return $this->meters / 1000;
    }

    /**
     * Get distance in miles.
     */
    public function toMiles(): float
    {
        return $this->meters / 1609.344;
    }

    /**
     * Add another distance.
     */
    public function add(Distance $other): self
    {
        return new self($this->meters + $other->meters);
    }

    /**
     * Subtract another distance.
     */
    public function subtract(Distance $other): self
    {
        return new self(max(0, $this->meters - $other->meters));
    }

    /**
     * Multiply by a scalar.
     */
    public function multiply(float $scalar): self
    {
        return new self($this->meters * $scalar);
    }

    /**
     * Check equality with another distance.
     */
    public function equals(Distance $other): bool
    {
        return abs($this->meters - $other->meters) < 0.001; // 1mm tolerance
    }

    /**
     * Check if greater than another distance.
     */
    public function greaterThan(Distance $other): bool
    {
        return $this->meters > $other->meters;
    }

    /**
     * Check if less than another distance.
     */
    public function lessThan(Distance $other): bool
    {
        return $this->meters < $other->meters;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'meters' => $this->meters,
            'kilometers' => $this->toKilometers(),
            'miles' => $this->toMiles(),
        ];
    }
}
