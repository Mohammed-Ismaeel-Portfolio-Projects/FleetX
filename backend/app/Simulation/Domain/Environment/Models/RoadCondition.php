<?php

namespace App\Simulation\Domain\Environment\Models;

/**
 * RoadCondition Model.
 * Represents road surface conditions.
 */
readonly class RoadCondition
{
    public function __construct(
        public string $condition, // dry, wet, flooded, icy, construction
    ) {}

    /**
     * Create dry condition.
     */
    public static function dry(): self
    {
        return new self('dry');
    }

    /**
     * Create wet condition.
     */
    public static function wet(): self
    {
        return new self('wet');
    }

    /**
     * Create flooded condition.
     */
    public static function flooded(): self
    {
        return new self('flooded');
    }

    /**
     * Create icy condition.
     */
    public static function icy(): self
    {
        return new self('icy');
    }

    /**
     * Create construction condition.
     */
    public static function construction(): self
    {
        return new self('construction');
    }

    /**
     * Check if hazardous.
     */
    public function isHazardous(): bool
    {
        return in_array($this->condition, ['flooded', 'icy', 'construction']);
    }

    /**
     * Check if wet.
     */
    public function isWet(): bool
    {
        return in_array($this->condition, ['wet', 'flooded']);
    }
}
