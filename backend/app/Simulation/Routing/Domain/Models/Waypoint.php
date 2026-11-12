<?php

namespace App\Simulation\Routing\Domain\Models;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;

/**
 * Waypoint Domain Model.
 * Represents a point of interest along a route.
 */
readonly class Waypoint
{
    public function __construct(
        public string $id,
        public Coordinate $coordinate,
        public ?string $name,
        public Distance $arrivalRadius,
        public bool $visited,
        public int $sequenceNumber,
    ) {}

    /**
     * Create waypoint.
     */
    public static function create(
        string $id,
        Coordinate $coordinate,
        ?string $name,
        int $sequenceNumber,
        ?Distance $arrivalRadius = null,
    ): self {
        return new self(
            id: $id,
            coordinate: $coordinate,
            name: $name,
            arrivalRadius: $arrivalRadius ?? Distance::fromMeters(50),
            visited: false,
            sequenceNumber: $sequenceNumber,
        );
    }

    /**
     * Mark as visited.
     */
    public function markAsVisited(): self
    {
        return new self(
            id: $this->id,
            coordinate: $this->coordinate,
            name: $this->name,
            arrivalRadius: $this->arrivalRadius,
            visited: true,
            sequenceNumber: $this->sequenceNumber,
        );
    }

    /**
     * Check if waypoint is a destination.
     */
    public function isDestination(): bool
    {
        return $this->name === 'destination' || str_contains($this->name ?? '', 'destination');
    }

    /**
     * Check if waypoint is an origin.
     */
    public function isOrigin(): bool
    {
        return $this->name === 'origin' || str_contains($this->name ?? '', 'origin');
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'coordinate' => $this->coordinate->toArray(),
            'name' => $this->name,
            'arrival_radius' => $this->arrivalRadius->toArray(),
            'visited' => $this->visited,
            'sequence_number' => $this->sequenceNumber,
        ];
    }
}
