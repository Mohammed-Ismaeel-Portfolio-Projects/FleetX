<?php

namespace App\Simulation\Domain\GPS\Services;

use App\Simulation\Domain\GPS\Models\Coordinate;

/**
 * GPSNoiseModel Service.
 * Simulates GPS drift, signal offset, and jitter.
 * Optional and configurable.
 */
class GPSNoiseModel
{
    private bool $enabled = false;
    private float $driftMagnitude = 0.0; // degrees
    private float $offsetMagnitude = 0.0; // degrees
    private float $jitterMagnitude = 0.0; // degrees

    /**
     * Apply noise to coordinate.
     */
    public function applyNoise(Coordinate $coordinate): Coordinate
    {
        if (!$this->enabled) {
            return $coordinate;
        }

        // Apply drift (systematic error)
        $driftLat = $this->driftMagnitude * 0.5; // Half magnitude for latitude
        $driftLon = $this->driftMagnitude * 0.5; // Half magnitude for longitude

        // Apply offset (constant error)
        $offsetLat = $this->offsetMagnitude * 0.3;
        $offsetLon = $this->offsetMagnitude * 0.3;

        // Apply jitter (random error)
        $jitterLat = $this->jitterMagnitude * (rand(-100, 100) / 100);
        $jitterLon = $this->jitterMagnitude * (rand(-100, 100) / 100);

        return new Coordinate(
            latitude: $coordinate->latitude + $driftLat + $offsetLat + $jitterLat,
            longitude: $coordinate->longitude + $driftLon + $offsetLon + $jitterLon,
            altitude: $coordinate->altitude,
        );
    }

    /**
     * Enable noise model.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable noise model.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if noise model is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set drift magnitude.
     */
    public function setDriftMagnitude(float $magnitude): void
    {
        $this->driftMagnitude = $magnitude;
    }

    /**
     * Set offset magnitude.
     */
    public function setOffsetMagnitude(float $magnitude): void
    {
        $this->offsetMagnitude = $magnitude;
    }

    /**
     * Set jitter magnitude.
     */
    public function setJitterMagnitude(float $magnitude): void
    {
        $this->jitterMagnitude = $magnitude;
    }

    /**
     * Get drift magnitude.
     */
    public function getDriftMagnitude(): float
    {
        return $this->driftMagnitude;
    }

    /**
     * Get offset magnitude.
     */
    public function getOffsetMagnitude(): float
    {
        return $this->offsetMagnitude;
    }

    /**
     * Get jitter magnitude.
     */
    public function getJitterMagnitude(): float
    {
        return $this->jitterMagnitude;
    }
}
