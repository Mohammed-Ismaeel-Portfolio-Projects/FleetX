<?php

namespace App\Simulation\Analytics;

/**
 * FleetKpiSnapshot
 *
 * Immutable operational KPI bundle for dashboards, AI, and reports.
 */
readonly class FleetKpiSnapshot
{
    /**
     * @param array<string, mixed> $fleet
     * @param array<string, array<string, mixed>> $vehicles
     * @param array<string, array<string, mixed>> $drivers
     * @param array<string, array<string, mixed>> $shipments
     */
    public function __construct(
        public string $timestamp,
        public array $fleet,
        public array $vehicles,
        public array $drivers,
        public array $shipments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'fleet' => $this->fleet,
            'vehicles' => $this->vehicles,
            'drivers' => $this->drivers,
            'shipments' => $this->shipments,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timestamp: (string) ($data['timestamp'] ?? now()->toIso8601String()),
            fleet: is_array($data['fleet'] ?? null) ? $data['fleet'] : [],
            vehicles: is_array($data['vehicles'] ?? null) ? $data['vehicles'] : [],
            drivers: is_array($data['drivers'] ?? null) ? $data['drivers'] : [],
            shipments: is_array($data['shipments'] ?? null) ? $data['shipments'] : [],
        );
    }
}
