<?php

namespace App\Simulation\Routing\Infrastructure\ResponseMappers;

use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * MapboxResponseMapper.
 * Maps Mapbox provider responses to NormalizedRoute.
 * 
 * This is architecture preparation only.
 * Do not implement mapping logic yet.
 */
class MapboxResponseMapper implements ProviderResponseMapperInterface
{
    // TODO: Implement Mapbox response parsing
    // TODO: Map Mapbox JSON structure to NormalizedRoute
    // TODO: Handle Mapbox-specific geometry encoding
    // TODO: Extract Mapbox metadata and warnings
    
    public function mapToNormalizedRoute(
        array $providerResponse,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): NormalizedRoute {
        // TODO: Parse Mapbox response
        // TODO: Create NormalizedRoute from Mapbox data
        return NormalizedRoute::empty();
    }
    
    public function validateProviderResponse(array $providerResponse): bool
    {
        // TODO: Validate Mapbox response format
        return true;
    }
    
    public function getSupportedProvider(): string
    {
        return 'mapbox';
    }
}
