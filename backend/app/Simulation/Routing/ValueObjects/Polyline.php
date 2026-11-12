<?php

namespace App\Simulation\Routing\ValueObjects;

/**
 * Polyline Value Object.
 * Represents an encoded polyline (Google Maps style).
 * Immutable value object following DDD principles.
 */
readonly class Polyline
{
    public function __construct(
        public string $encoded,
        public array $coordinates, // Coordinate[]
    ) {
        $this->validate();
    }

    /**
     * Create from encoded string.
     */
    public static function fromEncoded(string $encoded): self
    {
        $coordinates = self::decode($encoded);
        return new self($encoded, $coordinates);
    }

    /**
     * Create from coordinates array.
     */
    public static function fromCoordinates(array $coordinates): self
    {
        $encoded = self::encode($coordinates);
        return new self($encoded, $coordinates);
    }

    /**
     * Decode polyline string to coordinates.
     */
    private static function decode(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $shift = 0;
            $result = 0;
            
            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            
            $deltaLat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $deltaLat;

            $shift = 0;
            $result = 0;
            
            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            
            $deltaLng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $deltaLng;

            $coordinates[] = new Coordinate(
                latitude: $lat / 1e5,
                longitude: $lng / 1e5,
            );
        }

        return $coordinates;
    }

    /**
     * Encode coordinates to polyline string.
     */
    private static function encode(array $coordinates): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($coordinates as $coord) {
            $lat = (int) round($coord->latitude * 1e5);
            $lng = (int) round($coord->longitude * 1e5);

            $encoded .= self::encodeNumber($lat - $prevLat);
            $encoded .= self::encodeNumber($lng - $prevLng);

            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $encoded;
    }

    /**
     * Encode a single number.
     */
    private static function encodeNumber(int $num): string
    {
        $num = ($num < 0) ? ~($num << 1) : ($num << 1);
        $encoded = '';

        while ($num >= 0x20) {
            $encoded .= chr((0x20 | ($num & 0x1F)) + 63);
            $num >>= 5;
        }

        $encoded .= chr($num + 63);
        return $encoded;
    }

    /**
     * Validate polyline.
     */
    private function validate(): void
    {
        if (empty($this->coordinates)) {
            throw new \InvalidArgumentException('Polyline must contain at least one coordinate.');
        }
    }

    /**
     * Get coordinate count.
     */
    public function getCoordinateCount(): int
    {
        return count($this->coordinates);
    }

    /**
     * Get coordinate at index.
     */
    public function getCoordinate(int $index): ?Coordinate
    {
        return $this->coordinates[$index] ?? null;
    }

    /**
     * Get all coordinates.
     */
    public function getCoordinates(): array
    {
        return $this->coordinates;
    }

    /**
     * Get total distance of polyline.
     */
    public function getTotalDistance(): Distance
    {
        $total = Distance::fromMeters(0);
        
        for ($i = 0; $i < count($this->coordinates) - 1; $i++) {
            $total = $total->add(
                $this->coordinates[$i]->distanceTo($this->coordinates[$i + 1])
            );
        }
        
        return $total;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'encoded' => $this->encoded,
            'coordinates' => array_map(fn($c) => $c->toArray(), $this->coordinates),
            'coordinate_count' => $this->getCoordinateCount(),
        ];
    }
}
