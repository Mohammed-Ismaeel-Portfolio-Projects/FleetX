<?php

namespace App\Simulation\Domain\Telemetry\DTOs;

/**
 * TelemetrySignalCollection DTO.
 * Collection of telemetry signals for a vehicle.
 */
readonly class TelemetrySignalCollection
{
    /**
     * @param TelemetrySignal[] $signals
     */
    public function __construct(
        public string $vehicleId,
        public \DateTime $timestamp,
        public array $signals,
    ) {}

    /**
     * Add a signal to the collection.
     * Returns a new instance with the signal added.
     */
    public function addSignal(TelemetrySignal $signal): self
    {
        return new self(
            vehicleId: $this->vehicleId,
            timestamp: $this->timestamp,
            signals: [...$this->signals, $signal],
        );
    }

    /**
     * Get signal by name.
     */
    public function getSignal(string $name): ?TelemetrySignal
    {
        foreach ($this->signals as $signal) {
            if ($signal->name === $name) {
                return $signal;
            }
        }
        return null;
    }

    /**
     * Get all signals.
     */
    public function getSignals(): array
    {
        return $this->signals;
    }

    /**
     * Get signal count.
     */
    public function count(): int
    {
        return count($this->signals);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'signals' => array_map(fn($s) => $s->toArray(), $this->signals),
        ];
    }
}
