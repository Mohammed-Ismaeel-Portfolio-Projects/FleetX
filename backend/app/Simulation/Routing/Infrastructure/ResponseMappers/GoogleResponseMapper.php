<?php

namespace App\Simulation\Routing\Infrastructure\ResponseMappers;

use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * GoogleResponseMapper.
 * Maps Google Directions provider responses to NormalizedRoute.
 * 
 * This is architecture preparation only.
 * Do not implement mapping logic yet.
 */
class GoogleResponseMapper implements ProviderResponseMapperInterface
{
    // TODO: Implement Google response parsing
    // TODO: Map Google JSON structure to NormalizedRoute
    // TODO: Handle Google-specific geometry encoding
    // TODO: Extract Google metadata and warnings
    
    public function mapToNormalizedRoute(
        array $providerResponse,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): NormalizedRoute {
        // TODO: Parse Google response
        // TODO: Create NormalizedRoute from Google data
        return NormalizedRoute::empty();
    }
    
    public function validateProviderResponse(array $providerResponse): bool
    {
        // TODO: Validate Google response format
        return true;
    }
    
    public function getSupportedProvider(): string
    {
        return 'google';
    }
}
