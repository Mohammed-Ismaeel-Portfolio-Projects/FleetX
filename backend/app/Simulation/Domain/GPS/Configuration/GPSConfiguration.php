<?php

namespace App\Simulation\Domain\GPS\Configuration;

/**
 * GPSConfiguration.
 * Configuration for GPS simulation.
 */
class GPSConfiguration
{
    public function __construct(
        private int $updateIntervalMs = 1000, // Default 1 second
        private bool $enableNoiseModel = false,
        private float $noiseDriftMagnitude = 0.0001, // degrees
        private float $noiseOffsetMagnitude = 0.00005, // degrees
        private float $noiseJitterMagnitude = 0.00001, // degrees
        private float $baseAccuracy = 5.0, // meters
        private string $environment = 'open', // open, urban, canyon, indoor
        private bool $enableSpeedSmoothing = false,
        private float $speedSmoothingFactor = 0.5,
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            updateIntervalMs: $config['update_interval_ms'] ?? 1000,
            enableNoiseModel: $config['enable_noise_model'] ?? false,
            noiseDriftMagnitude: $config['noise_drift_magnitude'] ?? 0.0001,
            noiseOffsetMagnitude: $config['noise_offset_magnitude'] ?? 0.00005,
            noiseJitterMagnitude: $config['noise_jitter_magnitude'] ?? 0.00001,
            baseAccuracy: $config['base_accuracy'] ?? 5.0,
            environment: $config['environment'] ?? 'open',
            enableSpeedSmoothing: $config['enable_speed_smoothing'] ?? false,
            speedSmoothingFactor: $config['speed_smoothing_factor'] ?? 0.5,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'update_interval_ms' => $this->updateIntervalMs,
            'enable_noise_model' => $this->enableNoiseModel,
            'noise_drift_magnitude' => $this->noiseDriftMagnitude,
            'noise_offset_magnitude' => $this->noiseOffsetMagnitude,
            'noise_jitter_magnitude' => $this->noiseJitterMagnitude,
            'base_accuracy' => $this->baseAccuracy,
            'environment' => $this->environment,
            'enable_speed_smoothing' => $this->enableSpeedSmoothing,
            'speed_smoothing_factor' => $this->speedSmoothingFactor,
        ];
    }

    /**
     * Get update interval in milliseconds.
     */
    public function getUpdateIntervalMs(): int
    {
        return $this->updateIntervalMs;
    }

    /**
     * Set update interval in milliseconds.
     */
    public function setUpdateIntervalMs(int $intervalMs): void
    {
        $this->updateIntervalMs = $intervalMs;
    }

    /**
     * Check if noise model is enabled.
     */
    public function isNoiseModelEnabled(): bool
    {
        return $this->enableNoiseModel;
    }

    /**
     * Enable or disable noise model.
     */
    public function setNoiseModelEnabled(bool $enabled): void
    {
        $this->enableNoiseModel = $enabled;
    }

    /**
     * Get noise drift magnitude.
     */
    public function getNoiseDriftMagnitude(): float
    {
        return $this->noiseDriftMagnitude;
    }

    /**
     * Set noise drift magnitude.
     */
    public function setNoiseDriftMagnitude(float $magnitude): void
    {
        $this->noiseDriftMagnitude = $magnitude;
    }

    /**
     * Get noise offset magnitude.
     */
    public function getNoiseOffsetMagnitude(): float
    {
        return $this->noiseOffsetMagnitude;
    }

    /**
     * Set noise offset magnitude.
     */
    public function setNoiseOffsetMagnitude(float $magnitude): void
    {
        $this->noiseOffsetMagnitude = $magnitude;
    }

    /**
     * Get noise jitter magnitude.
     */
    public function getNoiseJitterMagnitude(): float
    {
        return $this->noiseJitterMagnitude;
    }

    /**
     * Set noise jitter magnitude.
     */
    public function setNoiseJitterMagnitude(float $magnitude): void
    {
        $this->noiseJitterMagnitude = $magnitude;
    }

    /**
     * Get base accuracy.
     */
    public function getBaseAccuracy(): float
    {
        return $this->baseAccuracy;
    }

    /**
     * Set base accuracy.
     */
    public function setBaseAccuracy(float $accuracy): void
    {
        $this->baseAccuracy = $accuracy;
    }

    /**
     * Get environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Set environment.
     */
    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Check if speed smoothing is enabled.
     */
    public function isSpeedSmoothingEnabled(): bool
    {
        return $this->enableSpeedSmoothing;
    }

    /**
     * Enable or disable speed smoothing.
     */
    public function setSpeedSmoothingEnabled(bool $enabled): void
    {
        $this->enableSpeedSmoothing = $enabled;
    }

    /**
     * Get speed smoothing factor.
     */
    public function getSpeedSmoothingFactor(): float
    {
        return $this->speedSmoothingFactor;
    }

    /**
     * Set speed smoothing factor.
     */
    public function setSpeedSmoothingFactor(float $factor): void
    {
        $this->speedSmoothingFactor = $factor;
    }
}
