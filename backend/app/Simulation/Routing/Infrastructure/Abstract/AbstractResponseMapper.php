<?php

namespace App\Simulation\Routing\Infrastructure\Abstract;

use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * AbstractResponseMapper.
 * Base class for all provider response mappers.
 * Provides common functionality for response mapping.
 * 
 * Responsibilities:
 * - Validate provider response format
 * - Map provider response to NormalizedRoute
 * - Extract metadata and warnings
 * - Handle provider-specific geometry encoding
 */
abstract class AbstractResponseMapper implements ProviderResponseMapperInterface
{
    /**
     * Map provider response to NormalizedRoute.
     * 
     * @param array $providerResponse Raw response from routing provider
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param Coordinate[] $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return NormalizedRoute The normalized route
     */
    abstract public function mapToNormalizedRoute(
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
    abstract public function validateProviderResponse(array $providerResponse): bool;

    /**
     * Get supported provider name.
     * 
     * @return string The provider name
     */
    abstract public function getSupportedProvider(): string;

    /**
     * Extract metadata from provider response.
     * 
     * @param array $providerResponse The provider response
     * @return array The metadata
     */
    protected function extractMetadata(array $providerResponse): array
    {
        // TODO: Implement metadata extraction
        // This will extract provider-specific metadata
        return [];
    }

    /**
     * Extract warnings from provider response.
     * 
     * @param array $providerResponse The provider response
     * @return array The warnings
     */
    protected function extractWarnings(array $providerResponse): array
    {
        // TODO: Implement warning extraction
        // This will extract provider-specific warnings
        return [];
    }

    /**
     * Extract diagnostics from provider response.
     * 
     * @param array $providerResponse The provider response
     * @return array The diagnostics
     */
    protected function extractDiagnostics(array $providerResponse): array
    {
        // TODO: Implement diagnostics extraction
        // This will extract provider-specific diagnostics for debugging
        return [];
    }

    /**
     * Decode geometry from provider format.
     * 
     * @param string $encodedGeometry The encoded geometry
     * @return array The decoded coordinates
     */
    protected function decodeGeometry(string $encodedGeometry): array
    {
        // TODO: Implement geometry decoding
        // This will handle provider-specific geometry encoding (polyline, etc.)
        return [];
    }

    /**
     * Parse distance from provider response.
     * 
     * @param array $providerResponse The provider response
     * @return float The distance in meters
     */
    protected function parseDistance(array $providerResponse): float
    {
        // TODO: Implement distance parsing
        // This will handle provider-specific distance units
        return 0.0;
    }

    /**
     * Parse duration from provider response.
     * 
     * @param array $providerResponse The provider response
     * @return float The duration in seconds
     */
    protected function parseDuration(array $providerResponse): float
    {
        // TODO: Implement duration parsing
        // This will handle provider-specific duration units
        return 0.0;
    }
}
