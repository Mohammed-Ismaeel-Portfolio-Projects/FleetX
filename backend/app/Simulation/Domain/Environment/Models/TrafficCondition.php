<?php

namespace App\Simulation\Domain\Environment\Models;

/**
 * TrafficCondition Model.
 * Represents traffic density conditions.
 */
readonly class TrafficCondition
{
    public function __construct(
        public string $density, // low, medium, heavy, blocked
        public float $averageSpeedModifier, // 0-1 multiplier
    ) {}

    /**
     * Create low traffic.
     */
    public static function low(): self
    {
        return new self('low', 1.0);
    }

    /**
     * Create medium traffic.
     */
    public static function medium(): self
    {
        return new self('medium', 0.8);
    }

    /**
     * Create heavy traffic.
     */
    public static function heavy(): self
    {
        return new self('heavy', 0.5);
    }

    /**
     * Create blocked traffic.
     */
    public static function blocked(): self
    {
        return new self('blocked', 0.0);
    }

    /**
     * Check if blocked.
     */
    public function isBlocked(): bool
    {
        return $this->density === 'blocked';
    }

    /**
     * Check if congested.
     */
    public function isCongested(): bool
    {
        return in_array($this->density, ['heavy', 'blocked']);
    }
}
