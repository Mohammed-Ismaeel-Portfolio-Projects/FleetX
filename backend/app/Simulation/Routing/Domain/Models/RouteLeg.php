<?php

namespace App\Simulation\Routing\Domain\Models;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\Polyline;

/**
 * RouteLeg Domain Model.
 * Represents a segment of a route between two waypoints.
 */
readonly class RouteLeg
{
    public function __construct(
        public string $id,
        public Coordinate $start,
        public Coordinate $end,
        public Distance $distance,
        public Duration $duration,
        public Polyline $geometry,
        public array $steps, // RouteStep[]
    ) {}

    /**
     * Create route leg.
     */
    public static function create(
        string $id,
        Coordinate $start,
        Coordinate $end,
        Distance $distance,
        Duration $duration,
        Polyline $geometry,
        array $steps = [],
    ): self {
        return new self(
            id: $id,
            start: $start,
            end: $end,
            distance: $distance,
            duration: $duration,
            geometry: $geometry,
            steps: $steps,
        );
    }

    /**
     * Get step by ID.
     */
    public function getStep(string $stepId): ?RouteStep
    {
        foreach ($this->steps as $step) {
            if ($step->id === $stepId) {
                return $step;
            }
        }
        return null;
    }

    /**
     * Get step by index.
     */
    public function getStepByIndex(int $index): ?RouteStep
    {
        return $this->steps[$index] ?? null;
    }

    /**
     * Get step count.
     */
    public function getStepCount(): int
    {
        return count($this->steps);
    }

    /**
     * Get first step.
     */
    public function getFirstStep(): ?RouteStep
    {
        return $this->steps[0] ?? null;
    }

    /**
     * Get last step.
     */
    public function getLastStep(): ?RouteStep
    {
        return $this->steps[count($this->steps) - 1] ?? null;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'start' => $this->start->toArray(),
            'end' => $this->end->toArray(),
            'distance' => $this->distance->toArray(),
            'duration' => $this->duration->toArray(),
            'geometry' => $this->geometry->toArray(),
            'steps' => array_map(fn($step) => $step->toArray(), $this->steps),
            'step_count' => $this->getStepCount(),
        ];
    }
}
