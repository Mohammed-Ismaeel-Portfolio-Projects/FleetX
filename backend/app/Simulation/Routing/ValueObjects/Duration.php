<?php

namespace App\Simulation\Routing\ValueObjects;

/**
 * Duration Value Object.
 * Represents a time duration.
 * Immutable value object following DDD principles.
 */
readonly class Duration
{
    private float $seconds;

    private function __construct(float $seconds)
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Duration cannot be negative.');
        }
        $this->seconds = $seconds;
    }

    /**
     * Create from seconds.
     */
    public static function fromSeconds(float $seconds): self
    {
        return new self($seconds);
    }

    /**
     * Create from minutes.
     */
    public static function fromMinutes(float $minutes): self
    {
        return new self($minutes * 60);
    }

    /**
     * Create from hours.
     */
    public static function fromHours(float $hours): self
    {
        return new self($hours * 3600);
    }

    /**
     * Get duration in seconds.
     */
    public function toSeconds(): float
    {
        return $this->seconds;
    }

    /**
     * Get duration in minutes.
     */
    public function toMinutes(): float
    {
        return $this->seconds / 60;
    }

    /**
     * Get duration in hours.
     */
    public function toHours(): float
    {
        return $this->seconds / 3600;
    }

    /**
     * Add another duration.
     */
    public function add(Duration $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    /**
     * Subtract another duration.
     */
    public function subtract(Duration $other): self
    {
        return new self(max(0, $this->seconds - $other->seconds));
    }

    /**
     * Multiply by a scalar.
     */
    public function multiply(float $scalar): self
    {
        return new self($this->seconds * $scalar);
    }

    /**
     * Check equality with another duration.
     */
    public function equals(Duration $other): bool
    {
        return abs($this->seconds - $other->seconds) < 0.001; // 1ms tolerance
    }

    /**
     * Check if greater than another duration.
     */
    public function greaterThan(Duration $other): bool
    {
        return $this->seconds > $other->seconds;
    }

    /**
     * Check if less than another duration.
     */
    public function lessThan(Duration $other): bool
    {
        return $this->seconds < $other->seconds;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'minutes' => $this->toMinutes(),
            'hours' => $this->toHours(),
        ];
    }
}
