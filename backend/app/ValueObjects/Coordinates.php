<?php

namespace App\ValueObjects;

/**
 * Coordinates Value Object
 * 
 * Immutable value object representing simple coordinates.
 * Lightweight alternative to GeoPoint when accuracy and timestamp are not needed.
 */
class Coordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: $data['latitude'],
            longitude: $data['longitude']
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * Convert to GeoPoint.
     */
    public function toGeoPoint(?float $accuracy = null, ?\DateTimeInterface $timestamp = null): GeoPoint
    {
        return new GeoPoint(
            latitude: $this->latitude,
            longitude: $this->longitude,
            accuracy: $accuracy,
            timestamp: $timestamp
        );
    }

    /**
     * Check equality with another Coordinates.
     */
    public function equals(self $other): bool
    {
        return abs($this->latitude - $other->latitude) < 0.00001
            && abs($this->longitude - $other->longitude) < 0.00001;
    }
}
