<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\DTOs\RoutePlanningResult;
use App\Simulation\Routing\Application\Pipelines\RoutingPipeline;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;

/**
 * RoutePlanner Service.
 * Main entry point for route planning operations.
 * Orchestrates the routing pipeline.
 */
class RoutePlanner
{
    private RoutingPipeline $pipeline;

    public function __construct(RoutingPipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Plan a route based on the request.
     *
     * @param RoutePlanningRequest $request The route planning request
     * @return RoutePlanningResult The route planning result
     */
    public function planRoute(RoutePlanningRequest $request): RoutePlanningResult
    {
        return $this->pipeline->execute($request);
    }

    /**
     * Plan multiple routes (batch processing).
     *
     * @param RoutePlanningRequest[] $requests Array of route planning requests
     * @return RoutePlanningResult[] Array of route planning results
     */
    public function planRoutes(array $requests): array
    {
        return array_map(
            fn($request) => $this->planRoute($request),
            $requests
        );
    }

    /**
     * Set routing pipeline.
     */
    public function setPipeline(RoutingPipeline $pipeline): void
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Get routing pipeline.
     */
    public function getPipeline(): RoutingPipeline
    {
        return $this->pipeline;
    }
}
