<?php

namespace App\Simulation\Stores;

/**
 * Telemetry Store.
 * Stores runtime telemetry data.
 */
class TelemetryStore
{
    private array $telemetry = [];
    private array $history = [];
    private int $maxHistorySize = 1000;

    /**
     * Get latest telemetry for entity.
     */
    public function get(string $entityId): ?array
    {
        return $this->telemetry[$entityId] ?? null;
    }

    /**
     * Set telemetry for entity.
     */
    public function set(string $entityId, array $data): void
    {
        $this->telemetry[$entityId] = $data;
        $this->addToHistory($entityId, $data);
    }

    /**
     * Remove telemetry for entity.
     */
    public function remove(string $entityId): void
    {
        unset($this->telemetry[$entityId]);
        unset($this->history[$entityId]);
    }

    /**
     * Get all telemetry.
     */
    public function all(): array
    {
        return $this->telemetry;
    }

    /**
     * Check if entity has telemetry.
     */
    public function has(string $entityId): bool
    {
        return isset($this->telemetry[$entityId]);
    }

    /**
     * Get entity count.
     */
    public function count(): int
    {
        return count($this->telemetry);
    }

    /**
     * Clear all telemetry.
     */
    public function clear(): void
    {
        $this->telemetry = [];
        $this->history = [];
    }

    /**
     * Get history for entity.
     */
    public function getHistory(string $entityId): array
    {
        return $this->history[$entityId] ?? [];
    }

    /**
     * Add to history with size limit.
     */
    private function addToHistory(string $entityId, array $data): void
    {
        if (!isset($this->history[$entityId])) {
            $this->history[$entityId] = [];
        }

        $this->history[$entityId][] = $data;

        if (count($this->history[$entityId]) > $this->maxHistorySize) {
            array_shift($this->history[$entityId]);
        }
    }

    /**
     * Set max history size.
     */
    public function setMaxHistorySize(int $size): void
    {
        $this->maxHistorySize = max(1, $size);
    }

    /**
     * Get telemetry by type.
     */
    public function getByType(string $type): array
    {
        return array_filter($this->telemetry, fn($t) => ($t['type'] ?? null) === $type);
    }

    /**
     * Update telemetry field.
     */
    public function updateField(string $entityId, string $field, mixed $value): void
    {
        if (isset($this->telemetry[$entityId])) {
            $this->telemetry[$entityId][$field] = $value;
        }
    }
}
