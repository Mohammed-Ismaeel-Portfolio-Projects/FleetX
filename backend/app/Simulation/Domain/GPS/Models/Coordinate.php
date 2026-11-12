<?php

namespace App\Simulation\Domain\GPS\Models;

/**
 * Coordinate Model.
 * Represents geographic coordinates.
 * No logic - data container only.
 */
readonly class Coordinate
{
    public function __construct(
        public float $latitude, // degrees
        public float $longitude, // degrees
        public ?float $altitude = null, // meters (optional)
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'altitude' => $this->altitude,
        ];
    }
}
