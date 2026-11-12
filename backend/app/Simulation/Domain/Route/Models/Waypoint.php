<?php

namespace App\Simulation\Domain\Route\Models;

/**
 * Waypoint Model.
 * Represents a point of interest along a route.
 */
class Waypoint
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type, // stop, checkpoint, destination, etc.
        public float $positionOnRoute, // logical position (0-1)
        public ?string $segmentId = null, // Optional: segment this waypoint is on
    ) {}

    /**
     * Create waypoint.
     */
    public static function create(
        string $id,
        string $name,
        string $type,
        float $positionOnRoute,
        ?string $segmentId = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            type: $type,
            positionOnRoute: $positionOnRoute,
            segmentId: $segmentId,
        );
    }

    /**
     * Check if waypoint is a stop.
     */
    public function isStop(): bool
    {
        return $this->type === 'stop';
    }

    /**
     * Check if waypoint is destination.
     */
    public function isDestination(): bool
    {
        return $this->type === 'destination';
    }
}
