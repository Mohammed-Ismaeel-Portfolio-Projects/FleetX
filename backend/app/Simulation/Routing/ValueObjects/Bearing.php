<?php

namespace App\Simulation\Routing\ValueObjects;

/**
 * Bearing Value Object.
 * Represents a compass bearing/direction.
 * Immutable value object following DDD principles.
 */
readonly class Bearing
{
    private float $degrees;

    private function __construct(float $degrees)
    {
        $this->degrees = $this->normalize($degrees);
    }

    /**
     * Create from degrees.
     */
    public static function fromDegrees(float $degrees): self
    {
        return new self($degrees);
    }

    /**
     * Create from radians.
     */
    public static function fromRadians(float $radians): self
    {
        return new self(rad2deg($radians));
    }

    /**
     * Normalize bearing to 0-360 range.
     */
    private function normalize(float $degrees): float
    {
        $normalized = $degrees % 360;
        if ($normalized < 0) {
            $normalized += 360;
        }
        return $normalized;
    }

    /**
     * Get bearing in degrees.
     */
    public function toDegrees(): float
    {
        return $this->degrees;
    }

    /**
     * Get bearing in radians.
     */
    public function toRadians(): float
    {
        return deg2rad($this->degrees);
    }

    /**
     * Get cardinal direction.
     */
    public function toCardinal(): string
    {
        return match(true) {
            $this->degrees >= 337.5 || $this->degrees < 22.5 => 'N',
            $this->degrees >= 22.5 && $this->degrees < 67.5 => 'NE',
            $this->degrees >= 67.5 && $this->degrees < 112.5 => 'E',
            $this->degrees >= 112.5 && $this->degrees < 157.5 => 'SE',
            $this->degrees >= 157.5 && $this->degrees < 202.5 => 'S',
            $this->degrees >= 202.5 && $this->degrees < 247.5 => 'SW',
            $this->degrees >= 247.5 && $this->degrees < 292.5 => 'W',
            $this->degrees >= 292.5 && $this->degrees < 337.5 => 'NW',
            default => 'N',
        };
    }

    /**
     * Check if pointing north.
     */
    public function isNorth(): bool
    {
        return $this->degrees >= 337.5 || $this->degrees < 22.5;
    }

    /**
     * Check if pointing east.
     */
    public function isEast(): bool
    {
        return $this->degrees >= 67.5 && $this->degrees < 112.5;
    }

    /**
     * Check if pointing south.
     */
    public function isSouth(): bool
    {
        return $this->degrees >= 157.5 && $this->degrees < 202.5;
    }

    /**
     * Check if pointing west.
     */
    public function isWest(): bool
    {
        return $this->degrees >= 247.5 && $this->degrees < 292.5;
    }

    /**
     * Check equality with another bearing.
     */
    public function equals(Bearing $other): bool
    {
        return abs($this->degrees - $other->degrees) < 0.1; // 0.1 degree tolerance
    }

    /**
     * Calculate difference from another bearing.
     */
    public function differenceFrom(Bearing $other): float
    {
        $diff = abs($this->degrees - $other->degrees);
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'degrees' => $this->degrees,
            'radians' => $this->toRadians(),
            'cardinal' => $this->toCardinal(),
        ];
    }
}
