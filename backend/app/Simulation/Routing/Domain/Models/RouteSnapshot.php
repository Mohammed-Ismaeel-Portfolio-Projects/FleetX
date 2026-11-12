<?php

namespace App\Simulation\Routing\Domain\Models;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\Enums\RouteStatus;

/**
 * RouteSnapshot Domain Model.
 * Immutable snapshot of route state at a point in time.
 */
readonly class RouteSnapshot
{
    public function __construct(
        public string $routeId,
        public Coordinate $origin,
        public Coordinate $destination,
        public Distance $totalDistance,
        public Duration $totalDuration,
        public RouteStatus $status,
        public ?string $currentLegId,
        public ?string $currentStepId,
        public Coordinate $currentPosition,
        public Distance $remainingDistance,
        public Duration $remainingDuration,
        public array $visitedWaypointIds,
        public float $completionPercentage,
        public \DateTime $snapshotTime,
    ) {}

    /**
     * Create from route and progress.
     */
    public static function fromRouteAndProgress(
        string $routeId,
        Coordinate $origin,
        Coordinate $destination,
        Distance $totalDistance,
        Duration $totalDuration,
        RouteStatus $status,
        ?RouteProgress $progress,
    ): self {
        return new self(
            routeId: $routeId,
            origin: $origin,
            destination: $destination,
            totalDistance: $totalDistance,
            totalDuration: $totalDuration,
            status: $status,
            currentLegId: $progress?->currentLegId,
            currentStepId: $progress?->currentStepId,
            currentPosition: $progress?->currentCoordinate ?? $origin,
            remainingDistance: $progress?->remainingDistance ?? $totalDistance,
            remainingDuration: $progress?->remainingDuration ?? $totalDuration,
            visitedWaypointIds: $progress?->visitedWaypointIds ?? [],
            completionPercentage: $progress?->completionPercentage ?? 0.0,
            snapshotTime: new \DateTime(),
        );
    }

    /**
     * Check if snapshot is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === RouteStatus::COMPLETED || $this->completionPercentage >= 100.0;
    }

    /**
     * Check if snapshot is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === RouteStatus::IN_PROGRESS;
    }

    /**
     * Check if snapshot is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === RouteStatus::FAILED;
    }

    /**
     * Get elapsed time since snapshot.
     */
    public function getElapsedTime(): Duration
    {
        $now = new \DateTime();
        $diff = $now->diff($this->snapshotTime);
        $seconds = ($diff->h * 3600) + ($diff->i * 60) + $diff->s + ($diff->f);
        return Duration::fromSeconds($seconds);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'route_id' => $this->routeId,
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'total_distance' => $this->totalDistance->toArray(),
            'total_duration' => $this->totalDuration->toArray(),
            'status' => $this->status->value,
            'current_leg_id' => $this->currentLegId,
            'current_step_id' => $this->currentStepId,
            'current_position' => $this->currentPosition->toArray(),
            'remaining_distance' => $this->remainingDistance->toArray(),
            'remaining_duration' => $this->remainingDuration->toArray(),
            'visited_waypoint_ids' => $this->visitedWaypointIds,
            'completion_percentage' => $this->completionPercentage,
            'snapshot_time' => $this->snapshotTime->format('Y-m-d H:i:s.u'),
        ];
    }
}
