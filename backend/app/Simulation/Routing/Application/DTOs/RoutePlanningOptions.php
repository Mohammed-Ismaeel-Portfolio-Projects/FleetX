<?php

namespace App\Simulation\Routing\Application\DTOs;

use App\Simulation\Routing\Application\Enums\OptimizationStrategy;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * RoutePlanningOptions DTO.
 * Represents options for route planning.
 */
readonly class RoutePlanningOptions
{
    public function __construct(
        public TravelMode $travelMode,
        public OptimizationStrategy $optimizationStrategy,
        public bool $avoidTolls,
        public bool $avoidHighways,
        public bool $avoidFerries,
        public bool $avoidUnpavedRoads,
        public int $alternativeRoutes, // Number of alternative routes to request
    ) {}

    /**
     * Create default options.
     */
    public static function createDefault(): self
    {
        return new self(
            travelMode: TravelMode::DRIVING,
            optimizationStrategy: OptimizationStrategy::FASTEST,
            avoidTolls: false,
            avoidHighways: false,
            avoidFerries: false,
            avoidUnpavedRoads: false,
            alternativeRoutes: 0,
        );
    }

    /**
     * Create with custom options.
     */
    public static function create(
        TravelMode $travelMode = TravelMode::DRIVING,
        OptimizationStrategy $optimizationStrategy = OptimizationStrategy::FASTEST,
        bool $avoidTolls = false,
        bool $avoidHighways = false,
        bool $avoidFerries = false,
        bool $avoidUnpavedRoads = false,
        int $alternativeRoutes = 0,
    ): self {
        return new self(
            travelMode: $travelMode,
            optimizationStrategy: $optimizationStrategy,
            avoidTolls: $avoidTolls,
            avoidHighways: $avoidHighways,
            avoidFerries: $avoidFerries,
            avoidUnpavedRoads: $avoidUnpavedRoads,
            alternativeRoutes: $alternativeRoutes,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'travel_mode' => $this->travelMode->value,
            'optimization_strategy' => $this->optimizationStrategy->value,
            'avoid_tolls' => $this->avoidTolls,
            'avoid_highways' => $this->avoidHighways,
            'avoid_ferries' => $this->avoidFerries,
            'avoid_unpaved_roads' => $this->avoidUnpavedRoads,
            'alternative_routes' => $this->alternativeRoutes,
        ];
    }
}
