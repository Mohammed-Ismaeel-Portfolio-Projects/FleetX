<?php

namespace App\Simulation\Routing\Domain\Models;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;

/**
 * RouteProgress Domain Model.
 * Tracks progress along a route.
 */
readonly class RouteProgress
{
    public function __construct(
        public string $routeId,
        public ?string $currentLegId,
        public ?string $currentStepId,
        public Coordinate $currentCoordinate,
        public Distance $remainingDistance,
        public Duration $remainingDuration,
        public array $visitedWaypointIds, // string[]
        public float $completionPercentage, // 0-100
    ) {}

    /**
     * Create initial progress.
     */
    public static function createInitial(
        string $routeId,
        Coordinate $startCoordinate,
        Distance $totalDistance,
        Duration $totalDuration,
    ): self {
        return new self(
            routeId: $routeId,
            currentLegId: null,
            currentStepId: null,
            currentCoordinate: $startCoordinate,
            remainingDistance: $totalDistance,
            remainingDuration: $totalDuration,
            visitedWaypointIds: [],
            completionPercentage: 0.0,
        );
    }

    /**
     * Update current leg.
     */
    public function withCurrentLeg(string $legId): self
    {
        return new self(
            routeId: $this->routeId,
            currentLegId: $legId,
            currentStepId: $this->currentStepId,
            currentCoordinate: $this->currentCoordinate,
            remainingDistance: $this->remainingDistance,
            remainingDuration: $this->remainingDuration,
            visitedWaypointIds: $this->visitedWaypointIds,
            completionPercentage: $this->completionPercentage,
        );
    }

    /**
     * Update current step.
     */
    public function withCurrentStep(string $stepId): self
    {
        return new self(
            routeId: $this->routeId,
            currentLegId: $this->currentLegId,
            currentStepId: $stepId,
            currentCoordinate: $this->currentCoordinate,
            remainingDistance: $this->remainingDistance,
            remainingDuration: $this->remainingDuration,
            visitedWaypointIds: $this->visitedWaypointIds,
            completionPercentage: $this->completionPercentage,
        );
    }

    /**
     * Update current coordinate.
     */
    public function withCurrentCoordinate(Coordinate $coordinate): self
    {
        return new self(
            routeId: $this->routeId,
            currentLegId: $this->currentLegId,
            currentStepId: $this->currentStepId,
            currentCoordinate: $coordinate,
            remainingDistance: $this->remainingDistance,
            remainingDuration: $this->remainingDuration,
            visitedWaypointIds: $this->visitedWaypointIds,
            completionPercentage: $this->completionPercentage,
        );
    }

    /**
     * Update remaining distance.
     */
    public function withRemainingDistance(Distance $distance): self
    {
        return new self(
            routeId: $this->routeId,
            currentLegId: $this->currentLegId,
            currentStepId: $this->currentStepId,
            currentCoordinate: $this->currentCoordinate,
            remainingDistance: $distance,
            remainingDuration: $this->remainingDuration,
            visitedWaypointIds: $this->visitedWaypointIds,
            completionPercentage: $this->completionPercentage,
        );
    }

    /**
     * Update remaining duration.
     */
    public function withRemainingDuration(Duration $duration): self
    {
        return new self(
            routeId: $this->routeId,
            currentLegId: $this->currentLegId,
            currentStepId: $this->currentStepId,
            currentCoordinate: $this->currentCoordinate,
            remainingDistance: $this->remainingDistance,
            remainingDuration: $duration,
            visitedWaypointIds: $this->visitedWaypointIds,
            completionPercentage: $this->completionPercentage,
        );
    }

    /**
     * Mark waypoint as visited.
     */
    public function withVisitedWaypoint(string $waypointId): self
    {
        $visited = $this->visitedWaypointIds;
        if (!in_array($waypointId, $visited)) {
            $visited[] = $waypointId;
        }

        return new self(
            routeId: $this->routeId,
            currentLegId: $this->currentLegId,
            currentStepId: $this->currentStepId,
            currentCoordinate: $this->currentCoordinate,
            remainingDistance: $this->remainingDistance,
            remainingDuration: $this->remainingDuration,
            visitedWaypointIds: $visited,
            completionPercentage: $this->completionPercentage,
        );
    }

    /**
     * Update completion percentage.
     */
    public function withCompletionPercentage(float $percentage): self
    {
        return new self(
            routeId: $this->routeId,
            currentLegId: $this->currentLegId,
            currentStepId: $this->currentStepId,
            currentCoordinate: $this->currentCoordinate,
            remainingDistance: $this->remainingDistance,
            remainingDuration: $this->remainingDuration,
            visitedWaypointIds: $this->visitedWaypointIds,
            completionPercentage: max(0, min(100, $percentage)),
        );
    }

    /**
     * Check if waypoint is visited.
     */
    public function isWaypointVisited(string $waypointId): bool
    {
        return in_array($waypointId, $this->visitedWaypointIds);
    }

    /**
     * Check if route is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completionPercentage >= 100.0;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'route_id' => $this->routeId,
            'current_leg_id' => $this->currentLegId,
            'current_step_id' => $this->currentStepId,
            'current_coordinate' => $this->currentCoordinate->toArray(),
            'remaining_distance' => $this->remainingDistance->toArray(),
            'remaining_duration' => $this->remainingDuration->toArray(),
            'visited_waypoint_ids' => $this->visitedWaypointIds,
            'completion_percentage' => $this->completionPercentage,
        ];
    }
}
