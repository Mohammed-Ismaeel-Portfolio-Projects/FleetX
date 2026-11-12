<?php

namespace App\Simulation\Domain\GPS\DTOs;

use App\Simulation\Domain\GPS\Models\GPSPosition;
use App\Simulation\Domain\GPS\Models\GPSSignal;

/**
 * GPSOutput DTO.
 * Combines GPS position and signal data.
 */
readonly class GPSOutput
{
    public function __construct(
        public string $vehicleId,
        public GPSPosition $position,
        public GPSSignal $signal,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'position' => $this->position->toArray(),
            'signal' => $this->signal->toArray(),
        ];
    }
}
