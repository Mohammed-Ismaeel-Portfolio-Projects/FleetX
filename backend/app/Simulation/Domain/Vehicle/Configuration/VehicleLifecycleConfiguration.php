<?php

namespace App\Simulation\Domain\Vehicle\Configuration;

/**
 * VehicleLifecycleConfiguration - Configurable durations for lifecycle state transitions.
 * All durations are in milliseconds.
 */
class VehicleLifecycleConfiguration
{
    public function __construct(
        public int $ignitionDuration = 2000, // Time to turn ignition on (ms)
        public int $startingDuration = 3000, // Time to start engine (ms)
        public int $idleDuration = 5000, // Time before auto-transition from idle (ms)
        public int $stoppingDuration = 4000, // Time to stop vehicle (ms)
        public int $parkingDuration = 3000, // Time to park vehicle (ms)
    ) {}

    /**
     * Get duration for a specific state.
     */
    public function getDurationForState(string $state): int
    {
        return match($state) {
            'ignition_on' => $this->ignitionDuration,
            'starting' => $this->startingDuration,
            'idle' => $this->idleDuration,
            'stopping' => $this->stoppingDuration,
            'parked' => $this->parkingDuration,
            default => 0,
        };
    }

    /**
     * Set duration for a specific state.
     */
    public function setDurationForState(string $state, int $durationMs): void
    {
        match($state) {
            'ignition_on' => $this->ignitionDuration = $durationMs,
            'starting' => $this->startingDuration = $durationMs,
            'idle' => $this->idleDuration = $durationMs,
            'stopping' => $this->stoppingDuration = $durationMs,
            'parked' => $this->parkingDuration = $durationMs,
            default => null,
        };
    }

    /**
     * Create from configuration array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            ignitionDuration: $config['ignition_duration'] ?? 2000,
            startingDuration: $config['starting_duration'] ?? 3000,
            idleDuration: $config['idle_duration'] ?? 5000,
            stoppingDuration: $config['stopping_duration'] ?? 4000,
            parkingDuration: $config['parking_duration'] ?? 3000,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'ignition_duration' => $this->ignitionDuration,
            'starting_duration' => $this->startingDuration,
            'idle_duration' => $this->idleDuration,
            'stopping_duration' => $this->stoppingDuration,
            'parking_duration' => $this->parkingDuration,
        ];
    }
}
