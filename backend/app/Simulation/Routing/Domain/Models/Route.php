<?php

namespace App\Simulation\Routing\Domain\Models;

use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\RouteGeometry;
use App\Simulation\Routing\Enums\RouteStatus;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * Route Domain Model (Aggregate Root).
 * Represents a complete route with legs, steps, and waypoints.
 */
readonly class Route
{
    public function __construct(
        public string $id,
        public Coordinate $origin,
        public Coordinate $destination,
        public array $waypoints, // Waypoint[]
        public array $legs, // RouteLeg[]
        public RouteGeometry $geometry,
        public Distance $distance,
        public Duration $duration,
        public RouteStatus $status,
        public TravelMode $travelMode,
        public \DateTime $createdAt,
        public ?string $provider = null, // OSRM, GraphHopper, Google, etc.
    ) {}

    /**
     * Create route.
     */
    public static function create(
        string $id,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        array $legs,
        RouteGeometry $geometry,
        Distance $distance,
        Duration $duration,
        TravelMode $travelMode,
        ?string $provider = null,
    ): self {
        return new self(
            id: $id,
            origin: $origin,
            destination: $destination,
            waypoints: $waypoints,
            legs: $legs,
            geometry: $geometry,
            distance: $distance,
            duration: $duration,
            status: RouteStatus::COMPLETED,
            travelMode: $travelMode,
            createdAt: new \DateTime(),
            provider: $provider,
        );
    }

    /**
     * Create pending route (before calculation).
     */
    public static function createPending(
        string $id,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): self {
        return new self(
            id: $id,
            origin: $origin,
            destination: $destination,
            waypoints: $waypoints,
            legs: [],
            geometry: RouteGeometry::fromCoordinates([$origin, $destination]),
            distance: Distance::fromMeters(0),
            duration: Duration::fromSeconds(0),
            status: RouteStatus::PENDING,
            travelMode: $travelMode,
            createdAt: new \DateTime(),
            provider: null,
        );
    }

    /**
     * Update status.
     */
    public function withStatus(RouteStatus $status): self
    {
        return new self(
            id: $this->id,
            origin: $this->origin,
            destination: $this->destination,
            waypoints: $this->waypoints,
            legs: $this->legs,
            geometry: $this->geometry,
            distance: $this->distance,
            duration: $this->duration,
            status: $status,
            travelMode: $this->travelMode,
            createdAt: $this->createdAt,
            provider: $this->provider,
        );
    }

    /**
     * Get leg by ID.
     */
    public function getLeg(string $legId): ?RouteLeg
    {
        foreach ($this->legs as $leg) {
            if ($leg->id === $legId) {
                return $leg;
            }
        }
        return null;
    }

    /**
     * Get leg by index.
     */
    public function getLegByIndex(int $index): ?RouteLeg
    {
        return $this->legs[$index] ?? null;
    }

    /**
     * Get waypoint by ID.
     */
    public function getWaypoint(string $waypointId): ?Waypoint
    {
        foreach ($this->waypoints as $waypoint) {
            if ($waypoint->id === $waypointId) {
                return $waypoint;
            }
        }
        return null;
    }

    /**
     * Get leg count.
     */
    public function getLegCount(): int
    {
        return count($this->legs);
    }

    /**
     * Get waypoint count.
     */
    public function getWaypointCount(): int
    {
        return count($this->waypoints);
    }

    /**
     * Get total step count across all legs.
     */
    public function getTotalStepCount(): int
    {
        return array_reduce($this->legs, fn($total, $leg) => $total + $leg->getStepCount(), 0);
    }

    /**
     * Check if route is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === RouteStatus::COMPLETED;
    }

    /**
     * Check if route is pending.
     */
    public function isPending(): bool
    {
        return $this->status === RouteStatus::PENDING;
    }

    /**
     * Check if route is calculating.
     */
    public function isCalculating(): bool
    {
        return $this->status === RouteStatus::CALCULATING;
    }

    /**
     * Check if route is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === RouteStatus::FAILED;
    }

    /**
     * Create snapshot.
     */
    public function createSnapshot(?RouteProgress $progress = null): RouteSnapshot
    {
        return RouteSnapshot::fromRouteAndProgress(
            routeId: $this->id,
            origin: $this->origin,
            destination: $this->destination,
            totalDistance: $this->distance,
            totalDuration: $this->duration,
            status: $this->status,
            progress: $progress,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'waypoints' => array_map(fn($w) => $w->toArray(), $this->waypoints),
            'legs' => array_map(fn($l) => $l->toArray(), $this->legs),
            'geometry' => $this->geometry->toArray(),
            'distance' => $this->distance->toArray(),
            'duration' => $this->duration->toArray(),
            'status' => $this->status->value,
            'travel_mode' => $this->travelMode->value,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s.u'),
            'provider' => $this->provider,
            'leg_count' => $this->getLegCount(),
            'waypoint_count' => $this->getWaypointCount(),
            'total_step_count' => $this->getTotalStepCount(),
        ];
    }
}
