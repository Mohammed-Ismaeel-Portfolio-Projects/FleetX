<?php

namespace App\Simulation\Domain\Environment\Models;

/**
 * Weather Model.
 * Represents weather conditions.
 */
readonly class Weather
{
    public function __construct(
        public string $weatherType, // clear, cloudy, rain, storm, fog, snow
        public float $intensity, // 0-1
    ) {}

    /**
     * Create clear weather.
     */
    public static function clear(float $intensity = 0.0): self
    {
        return new self('clear', $intensity);
    }

    /**
     * Create cloudy weather.
     */
    public static function cloudy(float $intensity = 0.5): self
    {
        return new self('cloudy', $intensity);
    }

    /**
     * Create rain weather.
     */
    public static function rain(float $intensity = 0.5): self
    {
        return new self('rain', $intensity);
    }

    /**
     * Create storm weather.
     */
    public static function storm(float $intensity = 0.8): self
    {
        return new self('storm', $intensity);
    }

    /**
     * Create fog weather.
     */
    public static function fog(float $intensity = 0.6): self
    {
        return new self('fog', $intensity);
    }

    /**
     * Create snow weather.
     */
    public static function snow(float $intensity = 0.5): self
    {
        return new self('snow', $intensity);
    }

    /**
     * Check if precipitation.
     */
    public function isPrecipitation(): bool
    {
        return in_array($this->weatherType, ['rain', 'storm', 'snow']);
    }

    /**
     * Check if severe.
     */
    public function isSevere(): bool
    {
        return $this->weatherType === 'storm' || $this->intensity > 0.7;
    }
}
