<?php

namespace App\Simulation\Routing\ValueObjects;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;

/**
 * RouteGeometry Value Object.
 * Represents the geometric representation of a route.
 * Immutable value object following DDD principles.
 */
readonly class RouteGeometry
{
    public function __construct(
        public Polyline $polyline,
        public array $bounds, // [minLat, minLon, maxLat, maxLon]
    ) {
        $this->validate();
    }

    /**
     * Create from polyline.
     */
    public static function fromPolyline(Polyline $polyline): self
    {
        $bounds = self::calculateBounds($polyline->getCoordinates());
        return new self($polyline, $bounds);
    }

    /**
     * Create from coordinates.
     */
    public static function fromCoordinates(array $coordinates): self
    {
        $polyline = Polyline::fromCoordinates($coordinates);
        return self::fromPolyline($polyline);
    }

    /**
     * Calculate bounding box from coordinates.
     */
    private static function calculateBounds(array $coordinates): array
    {
        if (empty($coordinates)) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $lats = array_map(fn($c) => $c->latitude, $coordinates);
        $lons = array_map(fn($c) => $c->longitude, $coordinates);

        return [
            min($lats),
            min($lons),
            max($lats),
            max($lons),
        ];
    }

    /**
     * Validate geometry.
     */
    private function validate(): void
    {
        if (count($this->bounds) !== 4) {
            throw new \InvalidArgumentException('Bounds must contain exactly 4 values.');
        }
    }

    /**
     * Get polyline.
     */
    public function getPolyline(): Polyline
    {
        return $this->polyline;
    }

    /**
     * Get bounds.
     */
    public function getBounds(): array
    {
        return $this->bounds;
    }

    /**
     * Get minimum latitude.
     */
    public function getMinLatitude(): float
    {
        return $this->bounds[0];
    }

    /**
     * Get minimum longitude.
     */
    public function getMinLongitude(): float
    {
        return $this->bounds[1];
    }

    /**
     * Get maximum latitude.
     */
    public function getMaxLatitude(): float
    {
        return $this->bounds[2];
    }

    /**
     * Get maximum longitude.
     */
    public function getMaxLongitude(): float
    {
        return $this->bounds[3];
    }

    /**
     * Get center point.
     */
    public function getCenter(): Coordinate
    {
        return new Coordinate(
            latitude: ($this->bounds[0] + $this->bounds[2]) / 2,
            longitude: ($this->bounds[1] + $this->bounds[3]) / 2,
        );
    }

    /**
     * Get total distance.
     */
    public function getTotalDistance(): Distance
    {
        return $this->polyline->getTotalDistance();
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'polyline' => $this->polyline->toArray(),
            'bounds' => $this->bounds,
            'center' => $this->getCenter()->toArray(),
            'total_distance' => $this->getTotalDistance()->toArray(),
        ];
    }
}
