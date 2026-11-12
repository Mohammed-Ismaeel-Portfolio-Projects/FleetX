<?php

namespace App\Simulation\Domain\Environment\Models;

/**
 * SurfaceCondition Model.
 * Represents road surface type and friction.
 */
readonly class SurfaceCondition
{
    public function __construct(
        public string $surfaceType, // asphalt, concrete, gravel, sand, mud
        public float $frictionCoefficient, // 0-1
    ) {}

    /**
     * Create asphalt surface.
     */
    public static function asphalt(float $friction = 0.8): self
    {
        return new self('asphalt', $friction);
    }

    /**
     * Create concrete surface.
     */
    public static function concrete(float $friction = 0.7): self
    {
        return new self('concrete', $friction);
    }

    /**
     * Create gravel surface.
     */
    public static function gravel(float $friction = 0.6): self
    {
        return new self('gravel', $friction);
    }

    /**
     * Create sand surface.
     */
    public static function sand(float $friction = 0.4): self
    {
        return new self('sand', $friction);
    }

    /**
     * Create mud surface.
     */
    public static function mud(float $friction = 0.3): self
    {
        return new self('mud', $friction);
    }

    /**
     * Check if slippery.
     */
    public function isSlippery(): bool
    {
        return $this->frictionCoefficient < 0.5;
    }

    /**
     * Check if hazardous.
     */
    public function isHazardous(): bool
    {
        return $this->frictionCoefficient < 0.4;
    }
}
