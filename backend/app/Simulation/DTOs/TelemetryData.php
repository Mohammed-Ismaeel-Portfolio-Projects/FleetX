<?php

namespace App\Simulation\DTOs;

/**
 * DTO for telemetry data shared between modules.
 * Generic structure for location, speed, fuel, etc.
 */
readonly class TelemetryData
{
    public function __construct(
        public string $entityId,
        public string $entityType,
        public array $metrics = [],
        public ?\DateTime $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }
}
