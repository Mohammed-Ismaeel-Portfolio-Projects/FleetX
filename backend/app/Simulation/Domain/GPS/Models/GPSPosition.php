<?php

namespace App\Simulation\Domain\GPS\Models;

/**
 * GPSPosition Model.
 * Represents GPS position with heading and speed.
 */
readonly class GPSPosition
{
    public function __construct(
        public Coordinate $coordinate,
        public float $heading, // degrees (0-360, 0 = North)
        public float $speed, // km/h
        public \DateTime $timestamp,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'coordinate' => $this->coordinate->toArray(),
            'heading' => $this->heading,
            'speed' => $this->speed,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
        ];
    }
}
