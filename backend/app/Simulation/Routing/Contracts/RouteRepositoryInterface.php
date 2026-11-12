<?php

namespace App\Simulation\Routing\Contracts;

use App\Simulation\Routing\Domain\Models\Route;
use App\Simulation\Routing\Domain\Models\RouteProgress;
use App\Simulation\Routing\Domain\Models\RouteSnapshot;

/**
 * RouteRepositoryInterface.
 * Contract for route persistence and retrieval.
 * This interface defines the contract for storing and retrieving routes.
 */
interface RouteRepositoryInterface
{
    /**
     * Save a route.
     *
     * @param Route $route The route to save
     * @return void
     */
    public function save(Route $route): void;

    /**
     * Find a route by ID.
     *
     * @param string $routeId The route ID
     * @return Route|null The route, or null if not found
     */
    public function findById(string $routeId): ?Route;

    /**
     * Find all routes.
     *
     * @return Route[] Array of routes
     */
    public function findAll(): array;

    /**
     * Find routes by status.
     *
     * @param string $status The route status
     * @return Route[] Array of routes with the given status
     */
    public function findByStatus(string $status): array;

    /**
     * Find routes by provider.
     *
     * @param string $provider The provider name
     * @return Route[] Array of routes from the given provider
     */
    public function findByProvider(string $provider): array;

    /**
     * Delete a route.
     *
     * @param string $routeId The route ID
     * @return void
     */
    public function delete(string $routeId): void;

    /**
     * Save route progress.
     *
     * @param RouteProgress $progress The progress to save
     * @return void
     */
    public function saveProgress(RouteProgress $progress): void;

    /**
     * Find route progress by route ID.
     *
     * @param string $routeId The route ID
     * @return RouteProgress|null The progress, or null if not found
     */
    public function findProgressByRouteId(string $routeId): ?RouteProgress;

    /**
     * Save route snapshot.
     *
     * @param RouteSnapshot $snapshot The snapshot to save
     * @return void
     */
    public function saveSnapshot(RouteSnapshot $snapshot): void;

    /**
     * Find snapshots by route ID.
     *
     * @param string $routeId The route ID
     * @param int $limit Maximum number of snapshots to return
     * @return RouteSnapshot[] Array of snapshots
     */
    public function findSnapshotsByRouteId(string $routeId, int $limit = 100): array;

    /**
     * Delete a route and all associated data.
     *
     * @param string $routeId The route ID
     * @return void
     */
    public function deleteCascade(string $routeId): void;
}
