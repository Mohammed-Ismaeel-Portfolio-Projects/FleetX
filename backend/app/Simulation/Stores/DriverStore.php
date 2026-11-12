<?php

namespace App\Simulation\Stores;

/**
 * Driver Store.
 * Stores runtime driver state data.
 */
class DriverStore
{
    private array $drivers = [];

    /**
     * Get driver state by ID.
     */
    public function get(string $driverId): ?array
    {
        return $this->drivers[$driverId] ?? null;
    }

    /**
     * Set driver state.
     */
    public function set(string $driverId, array $state): void
    {
        $this->drivers[$driverId] = $state;
    }

    /**
     * Remove driver state.
     */
    public function remove(string $driverId): void
    {
        unset($this->drivers[$driverId]);
    }

    /**
     * Get all driver states.
     */
    public function all(): array
    {
        return $this->drivers;
    }

    /**
     * Check if driver exists.
     */
    public function has(string $driverId): bool
    {
        return isset($this->drivers[$driverId]);
    }

    /**
     * Get driver count.
     */
    public function count(): int
    {
        return count($this->drivers);
    }

    /**
     * Clear all driver states.
     */
    public function clear(): void
    {
        $this->drivers = [];
    }

    /**
     * Get drivers by status.
     */
    public function getByStatus(string $status): array
    {
        return array_filter($this->drivers, fn($d) => ($d['status'] ?? null) === $status);
    }

    /**
     * Get drivers by vehicle.
     */
    public function getByVehicle(string $vehicleId): array
    {
        return array_filter($this->drivers, fn($d) => ($d['vehicle_id'] ?? null) === $vehicleId);
    }

    /**
     * Update driver field.
     */
    public function updateField(string $driverId, string $field, mixed $value): void
    {
        if (isset($this->drivers[$driverId])) {
            $this->drivers[$driverId][$field] = $value;
        }
    }
}
