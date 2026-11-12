<?php

namespace App\Simulation\Stores;

/**
 * Vehicle State Store.
 * Stores runtime vehicle state data.
 */
class VehicleStateStore
{
    private array $vehicles = [];

    /**
     * Get vehicle state by ID.
     */
    public function get(string $vehicleId): ?array
    {
        return $this->vehicles[$vehicleId] ?? null;
    }

    /**
     * Set vehicle state.
     */
    public function set(string $vehicleId, array $state): void
    {
        $this->vehicles[$vehicleId] = $state;
    }

    /**
     * Remove vehicle state.
     */
    public function remove(string $vehicleId): void
    {
        unset($this->vehicles[$vehicleId]);
    }

    /**
     * Get all vehicle states.
     */
    public function all(): array
    {
        return $this->vehicles;
    }

    /**
     * Check if vehicle exists.
     */
    public function has(string $vehicleId): bool
    {
        return isset($this->vehicles[$vehicleId]);
    }

    /**
     * Get vehicle count.
     */
    public function count(): int
    {
        return count($this->vehicles);
    }

    /**
     * Clear all vehicle states.
     */
    public function clear(): void
    {
        $this->vehicles = [];
    }

    /**
     * Get vehicles by status.
     */
    public function getByStatus(string $status): array
    {
        return array_filter($this->vehicles, fn($v) => ($v['status'] ?? null) === $status);
    }

    /**
     * Update vehicle field.
     */
    public function updateField(string $vehicleId, string $field, mixed $value): void
    {
        if (isset($this->vehicles[$vehicleId])) {
            $this->vehicles[$vehicleId][$field] = $value;
        }
    }
}
