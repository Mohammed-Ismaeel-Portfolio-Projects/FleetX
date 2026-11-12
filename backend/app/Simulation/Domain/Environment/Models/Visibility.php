<?php

namespace App\Simulation\Domain\Environment\Models;

/**
 * Visibility Model.
 * Represents visibility conditions.
 */
readonly class Visibility
{
    public function __construct(
        public float $visibilityDistance, // kilometers
        public float $visibilityFactor, // 0-1 multiplier
    ) {}

    /**
     * Create excellent visibility.
     */
    public static function excellent(): self
    {
        return new self(10.0, 1.0);
    }

    /**
     * Create good visibility.
     */
    public static function good(): self
    {
        return new self(5.0, 0.9);
    }

    /**
     * Create poor visibility.
     */
    public static function poor(): self
    {
        return new self(1.0, 0.5);
    }

    /**
     * Create very poor visibility.
     */
    public static function veryPoor(): self
    {
        return new self(0.5, 0.3);
    }

    /**
     * Check if limited.
     */
    public function isLimited(): bool
    {
        return $this->visibilityDistance < 2.0;
    }

    /**
     * Check if hazardous.
     */
    public function isHazardous(): bool
    {
        return $this->visibilityDistance < 1.0;
    }
}
