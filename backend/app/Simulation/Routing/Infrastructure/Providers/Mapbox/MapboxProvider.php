<?php

namespace App\Simulation\Routing\Infrastructure\Providers\Mapbox;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;

/**
 * MapboxProvider.
 * Architecture preparation only — not registered in RoutingServiceProvider.
 */
class MapboxProvider implements RoutingProviderInterface
{
    public function calculateRoute(RoutePlanningRequest $request): array
    {
        throw new \RuntimeException('MapboxProvider is not implemented yet.');
    }

    public function getProviderName(): string
    {
        return 'mapbox';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getProviderVersion(): string
    {
        return 'v5';
    }
}
