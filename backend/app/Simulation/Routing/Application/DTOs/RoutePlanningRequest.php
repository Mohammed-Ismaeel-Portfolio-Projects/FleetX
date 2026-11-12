<?php

namespace App\Simulation\Routing\Application\DTOs;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;

/**
 * RoutePlanningRequest DTO.
 * Represents a request for route planning.
 */
readonly class RoutePlanningRequest
{
    public function __construct(
        public string $planningId,
        public Coordinate $origin,
        public Coordinate $destination,
        public array $waypoints, // Coordinate[]
        public RoutePlanningContext $context,
    ) {}

    /**
     * Create route planning request.
     */
    public static function create(
        string $planningId,
        Coordinate $origin,
        Coordinate $destination,
        RoutePlanningContext $context,
        array $waypoints = [],
    ): self {
        return new self(
            planningId: $planningId,
            origin: $origin,
            destination: $destination,
            waypoints: $waypoints,
            context: $context,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'planning_id' => $this->planningId,
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'waypoints' => array_map(fn($w) => $w->toArray(), $this->waypoints),
            'context' => $this->context->toArray(),
        ];
    }
}
