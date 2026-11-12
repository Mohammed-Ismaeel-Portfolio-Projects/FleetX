<?php

namespace App\Simulation\Stores;

/**
 * Environment Store.
 * Stores runtime environment data (weather, traffic, etc.).
 */
class EnvironmentStore
{
    private array $weather = [];
    private array $traffic = [];
    private array $conditions = [];

    /**
     * Get weather for location.
     */
    public function getWeather(string $locationId): ?array
    {
        return $this->weather[$locationId] ?? null;
    }

    /**
     * Set weather for location.
     */
    public function setWeather(string $locationId, array $weatherData): void
    {
        $this->weather[$locationId] = $weatherData;
    }

    /**
     * Get all weather data.
     */
    public function allWeather(): array
    {
        return $this->weather;
    }

    /**
     * Get traffic for location.
     */
    public function getTraffic(string $locationId): ?array
    {
        return $this->traffic[$locationId] ?? null;
    }

    /**
     * Set traffic for location.
     */
    public function setTraffic(string $locationId, array $trafficData): void
    {
        $this->traffic[$locationId] = $trafficData;
    }

    /**
     * Get all traffic data.
     */
    public function allTraffic(): array
    {
        return $this->traffic;
    }

    /**
     * Get global condition.
     */
    public function getCondition(string $key): mixed
    {
        return $this->conditions[$key] ?? null;
    }

    /**
     * Set global condition.
     */
    public function setCondition(string $key, mixed $value): void
    {
        $this->conditions[$key] = $value;
    }

    /**
     * Get all conditions.
     */
    public function allConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Clear all environment data.
     */
    public function clear(): void
    {
        $this->weather = [];
        $this->traffic = [];
        $this->conditions = [];
    }

    /**
     * Get weather by type.
     */
    public function getWeatherByType(string $type): array
    {
        return array_filter($this->weather, fn($w) => ($w['type'] ?? null) === $type);
    }

    /**
     * Get traffic by level.
     */
    public function getTrafficByLevel(string $level): array
    {
        return array_filter($this->traffic, fn($t) => ($t['level'] ?? null) === $level);
    }
}
