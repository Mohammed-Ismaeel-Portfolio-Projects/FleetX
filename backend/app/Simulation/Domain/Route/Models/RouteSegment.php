<?php

namespace App\Simulation\Domain\Route\Models;

/**
 * RouteSegment Model.
 * Represents a segment of a route with road characteristics.
 */
class RouteSegment
{
    public function __construct(
        public string $id,
        public float $length, // kilometers
        public string $roadType, // highway, urban, rural, etc.
        public float $speedLimit, // km/h
        public float $startPoint, // logical position on route (0-1)
        public float $endPoint, // logical position on route (0-1)
    ) {}

    /**
     * Create segment.
     */
    public static function create(
        string $id,
        float $length,
        string $roadType,
        float $speedLimit,
        float $startPoint,
        float $endPoint,
    ): self {
        return new self(
            id: $id,
            length: $length,
            roadType: $roadType,
            speedLimit: $speedLimit,
            startPoint: $startPoint,
            endPoint: $endPoint,
        );
    }

    /**
     * Get travel time at speed limit.
     */
    public function getTravelTime(): float
    {
        return ($this->length / $this->speedLimit) * 3600; // seconds
    }
}
