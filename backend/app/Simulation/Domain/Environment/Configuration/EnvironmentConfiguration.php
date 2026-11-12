<?php

namespace App\Simulation\Domain\Environment\Configuration;

/**
 * EnvironmentConfiguration.
 * Configuration for environment simulation.
 */
class EnvironmentConfiguration
{
    public function __construct(
        private int $updateIntervalMs = 5000, // Default 5 seconds
        private string $weatherScenario = 'normal', // normal, rain, storm, fog, snow, extreme
        private string $trafficScenario = 'normal', // normal, rush_hour, congested, blocked
        private string $defaultSurfaceType = 'asphalt', // asphalt, concrete, gravel, sand, mud
        private float $baseTemperature = 20.0, // Celsius
        private float $baseHumidity = 50.0, // percentage
        private float $baseWindSpeed = 10.0, // km/h
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            updateIntervalMs: $config['update_interval_ms'] ?? 5000,
            weatherScenario: $config['weather_scenario'] ?? 'normal',
            trafficScenario: $config['traffic_scenario'] ?? 'normal',
            defaultSurfaceType: $config['default_surface_type'] ?? 'asphalt',
            baseTemperature: $config['base_temperature'] ?? 20.0,
            baseHumidity: $config['base_humidity'] ?? 50.0,
            baseWindSpeed: $config['base_wind_speed'] ?? 10.0,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'update_interval_ms' => $this->updateIntervalMs,
            'weather_scenario' => $this->weatherScenario,
            'traffic_scenario' => $this->trafficScenario,
            'default_surface_type' => $this->defaultSurfaceType,
            'base_temperature' => $this->baseTemperature,
            'base_humidity' => $this->baseHumidity,
            'base_wind_speed' => $this->baseWindSpeed,
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
     * Get weather scenario.
     */
    public function getWeatherScenario(): string
    {
        return $this->weatherScenario;
    }

    /**
     * Set weather scenario.
     */
    public function setWeatherScenario(string $scenario): void
    {
        $this->weatherScenario = $scenario;
    }

    /**
     * Get traffic scenario.
     */
    public function getTrafficScenario(): string
    {
        return $this->trafficScenario;
    }

    /**
     * Set traffic scenario.
     */
    public function setTrafficScenario(string $scenario): void
    {
        $this->trafficScenario = $scenario;
    }

    /**
     * Get default surface type.
     */
    public function getDefaultSurfaceType(): string
    {
        return $this->defaultSurfaceType;
    }

    /**
     * Set default surface type.
     */
    public function setDefaultSurfaceType(string $surfaceType): void
    {
        $this->defaultSurfaceType = $surfaceType;
    }

    /**
     * Get base temperature.
     */
    public function getBaseTemperature(): float
    {
        return $this->baseTemperature;
    }

    /**
     * Set base temperature.
     */
    public function setBaseTemperature(float $temperature): void
    {
        $this->baseTemperature = $temperature;
    }

    /**
     * Get base humidity.
     */
    public function getBaseHumidity(): float
    {
        return $this->baseHumidity;
    }

    /**
     * Set base humidity.
     */
    public function setBaseHumidity(float $humidity): void
    {
        $this->baseHumidity = $humidity;
    }

    /**
     * Get base wind speed.
     */
    public function getBaseWindSpeed(): float
    {
        return $this->baseWindSpeed;
    }

    /**
     * Set base wind speed.
     */
    public function setBaseWindSpeed(float $windSpeed): void
    {
        $this->baseWindSpeed = $windSpeed;
    }
}
