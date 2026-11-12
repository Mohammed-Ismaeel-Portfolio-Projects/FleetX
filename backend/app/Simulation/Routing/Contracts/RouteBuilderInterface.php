<?php

namespace App\Simulation\Routing\Contracts;

use App\Simulation\Routing\Domain\Models\Route;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * RouteBuilderInterface.
 * Contract for building routes from provider responses.
 * This interface defines the contract for converting provider-specific
 * responses into domain Route objects.
 */
interface RouteBuilderInterface
{
    /**
     * Build a route from provider response data.
     *
     * @param array $providerResponse Raw response from routing provider
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param TravelMode $travelMode Mode of travel
     * @return Route The constructed route
     */
    public function buildFromProviderResponse(
        array $providerResponse,
        Coordinate $origin,
        Coordinate $destination,
        TravelMode $travelMode = TravelMode::DRIVING,
    ): Route;

    /**
     * Validate provider response format.
     *
     * @param array $providerResponse Raw response from routing provider
     * @return bool True if valid, false otherwise
     */
    public function validateProviderResponse(array $providerResponse): bool;

    /**
     * Get supported provider name.
     */
    public function getSupportedProvider(): string;
}
