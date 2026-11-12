<?php

namespace App\Simulation\Stores;

/**
 * Shipment Store.
 * Stores runtime shipment state data.
 */
class ShipmentStore
{
    private array $shipments = [];

    /**
     * Get shipment state by ID.
     */
    public function get(string $shipmentId): ?array
    {
        return $this->shipments[$shipmentId] ?? null;
    }

    /**
     * Set shipment state.
     */
    public function set(string $shipmentId, array $state): void
    {
        $this->shipments[$shipmentId] = $state;
    }

    /**
     * Remove shipment state.
     */
    public function remove(string $shipmentId): void
    {
        unset($this->shipments[$shipmentId]);
    }

    /**
     * Get all shipment states.
     */
    public function all(): array
    {
        return $this->shipments;
    }

    /**
     * Check if shipment exists.
     */
    public function has(string $shipmentId): bool
    {
        return isset($this->shipments[$shipmentId]);
    }

    /**
     * Get shipment count.
     */
    public function count(): int
    {
        return count($this->shipments);
    }

    /**
     * Clear all shipment states.
     */
    public function clear(): void
    {
        $this->shipments = [];
    }

    /**
     * Get shipments by status.
     */
    public function getByStatus(string $status): array
    {
        return array_filter($this->shipments, fn($s) => ($s['status'] ?? null) === $status);
    }

    /**
     * Get shipments by vehicle.
     */
    public function getByVehicle(string $vehicleId): array
    {
        return array_filter($this->shipments, fn($s) => ($s['vehicle_id'] ?? null) === $vehicleId);
    }

    /**
     * Update shipment field.
     */
    public function updateField(string $shipmentId, string $field, mixed $value): void
    {
        if (isset($this->shipments[$shipmentId])) {
            $this->shipments[$shipmentId][$field] = $value;
        }
    }
}
