<?php

namespace App\Simulation\Domain\Telemetry\DTOs;

/**
 * TelemetrySignal DTO.
 * Represents a single telemetry signal with metadata.
 */
readonly class TelemetrySignal
{
    public function __construct(
        public string $name, // e.g., 'vehicle.speed', 'engine.rpm'
        public float $value,
        public string $unit, // e.g., 'km/h', 'rpm', '%', 'V'
        public \DateTime $timestamp,
        public string $quality, // 'good', 'bad', 'uncertain'
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'unit' => $this->unit,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'quality' => $this->quality,
        ];
    }
}
