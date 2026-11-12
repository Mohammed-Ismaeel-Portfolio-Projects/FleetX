<?php

namespace App\Simulation\Routing\Contracts;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;

/**
 * RoutingProviderInterface.
 * Contract for external routing providers (OSRM, Simulator, GraphHopper, Google, etc.).
 *
 * Providers return raw provider JSON. Mapping to domain Route objects happens
 * in the RoutingPipeline via ProviderResponseMapper + RouteFactory.
 */
interface RoutingProviderInterface
{
    /**
     * Calculate a route for the given planning request.
     *
     * @param RoutePlanningRequest $request The route planning request
     * @return array Raw provider response payload
     */
    public function calculateRoute(RoutePlanningRequest $request): array;

    /**
     * Get provider name.
     */
    public function getProviderName(): string;

    /**
     * Check if provider is available.
     */
    public function isAvailable(): bool;

    /**
     * Get provider version.
     */
    public function getProviderVersion(): string;
}
