<?php

namespace App\Simulation\Routing\Contracts;

use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * ProviderResponseMapperInterface.
 * Contract for converting provider-specific responses to NormalizedRoute.
 * Each routing provider must implement its own mapper.
 */
interface ProviderResponseMapperInterface
{
    /**
     * Map provider response to NormalizedRoute.
     *
     * @param array $providerResponse Raw response from routing provider
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     *     array $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return NormalizedRoute The normalized route
     */
    public function mapToNormalizedRoute(
        array $providerResponse,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): NormalizedRoute;

    /**
     * Validate provider response format.
     *
     * @param array $providerResponse Raw response from routing provider
     * @return bool True if valid, false otherwise
     */
    public function validateProviderResponse(array $providerResponse): bool;

    /**
     * Get supported provider name.
     *
     * @return string The provider name
     */
    public function getSupportedProvider(): string;
}
