<?php

namespace App\Simulation\Routing\Infrastructure\Providers\Google;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;

/**
 * GoogleDirectionsProvider.
 * Architecture preparation only — not registered in RoutingServiceProvider.
 */
class GoogleDirectionsProvider implements RoutingProviderInterface
{
    public function calculateRoute(RoutePlanningRequest $request): array
    {
        throw new \RuntimeException('GoogleDirectionsProvider is not implemented yet.');
    }

    public function getProviderName(): string
    {
        return 'google';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getProviderVersion(): string
    {
        return 'v1';
    }
}
