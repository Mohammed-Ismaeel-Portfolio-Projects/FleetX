<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;

/**
 * ProviderExecutor Service.
 * Executes routing provider calls.
 * Placeholder for future HTTP implementation.
 */
class ProviderExecutor
{
    /**
     * Execute routing provider.
     *
     * @param RoutingProviderInterface $provider The provider to execute
     * @param RoutePlanningRequest $request The route planning request
     * @return array The provider response
     */
    public function execute(
        RoutingProviderInterface $provider,
        RoutePlanningRequest $request,
    ): array {
        // For simulator provider, call directly (no HTTP needed)
        // For real providers (OSRM, Google, etc.), this would make HTTP calls
        return $provider->calculateRoute($request);
    }

    /**
     * Execute provider with retry logic.
     *
     * @param RoutingProviderInterface $provider The provider to execute
     * @param RoutePlanningRequest $request The route planning request
     * @param int $maxRetries Maximum number of retries
     * @return array The provider response
     */
    public function executeWithRetry(
        RoutingProviderInterface $provider,
        RoutePlanningRequest $request,
        int $maxRetries = 3,
    ): array {
        // TODO: Implement retry logic
        // This is architecture preparation only
        return $this->execute($provider, $request);
    }

    /**
     * Execute provider with timeout.
     *
     * @param RoutingProviderInterface $provider The provider to execute
     * @param RoutePlanningRequest $request The route planning request
     * @param float $timeoutSeconds Timeout in seconds
     * @return array The provider response
     */
    public function executeWithTimeout(
        RoutingProviderInterface $provider,
        RoutePlanningRequest $request,
        float $timeoutSeconds = 30.0,
    ): array {
        // TODO: Implement timeout logic
        // This is architecture preparation only
        return $this->execute($provider, $request);
    }
}
