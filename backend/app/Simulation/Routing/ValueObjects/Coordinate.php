<?php

namespace App\Simulation\Routing\ValueObjects;

/**
 * Coordinate Value Object.
 * Represents a geographic coordinate with latitude and longitude.
 * Immutable value object following DDD principles.
 */
readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        $this->validate();
    }

    /**
     * Validate coordinate values.
     */
    private function validate(): void
    {
        if ($this->latitude < -90.0 || $this->latitude > 90.0) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90 degrees.');
        }
        if ($this->longitude < -180.0 || $this->longitude > 180.0) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180 degrees.');
        }
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: $data['latitude'],
            longitude: $data['longitude'],
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
     * Check equality with another coordinate.
     */
    public function equals(Coordinate $other): bool
    {
        return $this->latitude === $other->latitude
            && $this->longitude === $other->longitude;
    }

    /**
     * Calculate distance to another coordinate (Haversine formula).
     */
    public function distanceTo(Coordinate $other): Distance
    {
        $earthRadius = 6371; // kilometers

        $latDiff = deg2rad($other->latitude - $this->latitude);
        $lonDiff = deg2rad($other->longitude - $this->longitude);

        $a = sin($latDiff / 2) * sin($latDiff / 2)
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude))
            * sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return Distance::fromKilometers($earthRadius * $c);
    }

    /**
     * Calculate bearing to another coordinate.
     */
    public function bearingTo(Coordinate $other): Bearing
    {
        $latDiff = deg2rad($other->latitude - $this->latitude);
        $lonDiff = deg2rad($other->longitude - $this->longitude);

        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);

        $x = sin($lonDiff) * cos($lat2);
        $y = cos($lat1) * sin($lat2)
            - sin($lat1) * cos($lat2) * cos($lonDiff);

        $bearing = atan2($x, $y);
        $bearing = rad2deg($bearing);
        $bearing = ($bearing + 360) % 360;

        return Bearing::fromDegrees($bearing);
    }
}
