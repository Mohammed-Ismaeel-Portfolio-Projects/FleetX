<?php

namespace App\ValueObjects;

/**
 * GeoPoint Value Object
 * 
 * Immutable value object representing a geographic coordinate.
 * Used for location tracking throughout the system.
 */
class GeoPoint
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $accuracy = null,
        public readonly ?\DateTimeInterface $timestamp = null
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: $data['latitude'],
            longitude: $data['longitude'],
            accuracy: $data['accuracy'] ?? null,
            timestamp: isset($data['timestamp']) ? new \DateTime($data['timestamp']) : null
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
            'accuracy' => $this->accuracy,
            'timestamp' => $this->timestamp?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate distance to another GeoPoint in kilometers.
     */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($other->latitude - $this->latitude);
        $dLon = deg2rad($other->longitude - $this->longitude);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check equality with another GeoPoint.
     */
    public function equals(self $other): bool
    {
        return abs($this->latitude - $other->latitude) < 0.00001
            && abs($this->longitude - $other->longitude) < 0.00001;
    }
}
