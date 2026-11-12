<?php

namespace App\Support;

/**
 * Signed longitudinal observation from two live speed samples (D15-B).
 * accelerationMs2 < 0 is braking. Units are explicit in property names.
 */
final readonly class ObservedLongitudinalMotion
{
    public function __construct(
        public float $speed0Kmh,
        public float $speed1Kmh,
        public float $speed0MetersPerSecond,
        public float $speed1MetersPerSecond,
        public float $deltaTimeSeconds,
        public float $accelerationMs2,
    ) {}

    public function isDeceleration(): bool
    {
        return $this->accelerationMs2 < 0.0;
    }

    public function isAcceleration(): bool
    {
        return $this->accelerationMs2 > 0.0;
    }

    /**
     * |a| when braking; otherwise 0.
     */
    public function decelerationMagnitudeMs2(): float
    {
        return $this->accelerationMs2 < 0.0 ? abs($this->accelerationMs2) : 0.0;
    }
}
