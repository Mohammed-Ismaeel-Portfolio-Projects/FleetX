<?php

namespace App\Simulation\DTOs;

/**
 * DTO for location data.
 * Used for GPS coordinates and position tracking.
 */
readonly class LocationData
{
    public function __construct(
        public string $entityId,
        public float $latitude,
        public float $longitude,
        public ?float $altitude = null,
        public ?float $heading = null,
        public ?float $speed = null,
        public ?\DateTime $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }
}
