<?php

namespace App\Simulation\Domain\Fuel\Events;

/**
 * FuelLevelChanged Event.
 * Published when fuel level changes.
 */
readonly class FuelLevelChanged
{
    public function __construct(
        public string $vehicleId,
        public float $previousLevel,
        public float $newLevel,
        public float $previousPercentage,
        public float $newPercentage,
        public \DateTime $timestamp,
        public string $reason,
    ) {}

    /**
     * Create from fuel tank data.
     */
    public static function fromTankData(
        string $vehicleId,
        float $previousLevel,
        float $newLevel,
        float $previousPercentage,
        float $newPercentage,
        string $reason,
    ): self {
        return new self(
            vehicleId: $vehicleId,
            previousLevel: $previousLevel,
            newLevel: $newLevel,
            previousPercentage: $previousPercentage,
            newPercentage: $newPercentage,
            timestamp: new \DateTime(),
            reason: $reason,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'previous_level' => $this->previousLevel,
            'new_level' => $this->newLevel,
            'previous_percentage' => $this->previousPercentage,
            'new_percentage' => $this->newPercentage,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'reason' => $this->reason,
        ];
    }
}
