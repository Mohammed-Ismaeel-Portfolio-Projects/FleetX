<?php

namespace App\Simulation\Routing\Application\DTOs;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\RouteGeometry;

/**
 * NormalizedRoute DTO.
 * Provider-independent routing result.
 * All provider mappers must return this format.
 */
readonly class NormalizedRoute
{
    public function __construct(
        public Coordinate $origin,
        public Coordinate $destination,
        public RouteGeometry $geometry,
        public Distance $distance,
        public Duration $duration,
        public array $legs, // NormalizedLeg[]
        public array $steps, // NormalizedStep[]
        public array $waypoints, // NormalizedWaypoint[]
        public array $metadata,
        public array $warnings,
        public array $diagnostics,
    ) {}

    /**
     * Create empty normalized route.
     */
    public static function empty(): self
    {
        $zero = new Coordinate(0, 0);

        return new self(
            origin: $zero,
            destination: $zero,
            geometry: RouteGeometry::fromCoordinates([$zero, $zero]),
            distance: Distance::fromMeters(0),
            duration: Duration::fromSeconds(0),
            legs: [],
            steps: [],
            waypoints: [],
            metadata: [],
            warnings: [],
            diagnostics: [],
        );
    }

    /**
     * Check if route has warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Check if route has diagnostics.
     */
    public function hasDiagnostics(): bool
    {
        return !empty($this->diagnostics);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'geometry' => $this->geometry->toArray(),
            'distance' => $this->distance->toArray(),
            'duration' => $this->duration->toArray(),
            'legs' => $this->legs,
            'steps' => $this->steps,
            'waypoints' => $this->waypoints,
            'metadata' => $this->metadata,
            'warnings' => $this->warnings,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
