<?php

namespace App\Simulation\Domain\GPS\Events;

/**
 * GPSUpdated Event.
 * Published when GPS position is updated.
 */
readonly class GPSUpdated
{
    public function __construct(
        public string $vehicleId,
        public float $latitude,
        public float $longitude,
        public ?float $altitude,
        public float $heading,
        public float $speed,
        public float $accuracy,
        public int $satelliteCount,
        public \DateTime $timestamp,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'altitude' => $this->altitude,
            'heading' => $this->heading,
            'speed' => $this->speed,
            'accuracy' => $this->accuracy,
            'satellite_count' => $this->satelliteCount,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
