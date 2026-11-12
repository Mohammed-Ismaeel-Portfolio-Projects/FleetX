<?php

namespace App\Simulation\Routing\Infrastructure\Abstract;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Infrastructure\Abstract\AbstractRoutingClient;
use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Infrastructure\Configuration\ProviderConfiguration;
use App\Simulation\Routing\Infrastructure\Health\ProviderHealthChecker;
use App\Simulation\Routing\Infrastructure\Retry\RetryPolicy;
use App\Simulation\Routing\Infrastructure\Cache\CacheManager;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * AbstractRoutingProvider.
 * Base class for all routing provider implementations.
 * Provides common functionality for provider orchestration.
 * 
 * Responsibilities:
 * - Delegate networking to RoutingClient
 * - Delegate parsing to ResponseMapper
 * - Never know RouteFactory internals
 * - Use ProviderConfiguration for settings
 * - Use ProviderHealthChecker for availability
 * - Use RetryPolicy for retry logic
 * - Use CacheManager for caching
 */
abstract class AbstractRoutingProvider implements RoutingProviderInterface
{
    protected AbstractRoutingClient $client;
    protected ProviderResponseMapperInterface $mapper;
    protected ProviderConfiguration $configuration;
    protected ProviderHealthChecker $healthChecker;
    protected RetryPolicy $retryPolicy;
    protected CacheManager $cacheManager;

    public function __construct(
        AbstractRoutingClient $client,
        ProviderResponseMapperInterface $mapper,
        ProviderConfiguration $configuration,
        ProviderHealthChecker $healthChecker,
        RetryPolicy $retryPolicy,
        CacheManager $cacheManager,
    ) {
        $this->client = $client;
        $this->mapper = $mapper;
        $this->configuration = $configuration;
        $this->healthChecker = $healthChecker;
        $this->retryPolicy = $retryPolicy;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Calculate route using the provider.
     * Abstract base remains for future HTTP providers; active providers (OSRM/Simulator)
     * implement RoutingProviderInterface directly.
     *
     * @return array Raw provider response
     */
    public function calculateRoute(RoutePlanningRequest $request): array
    {
        $origin = $request->origin;
        $destination = $request->destination;
        $waypoints = $request->waypoints;
        $travelMode = $request->context->planningOptions->travelMode;

        return $this->executeWithRetry(
            fn() => $this->executeProviderCall($origin, $destination, $waypoints, $travelMode)
        );
    }

    /**
     * Check if provider is available.
     * 
     * @return bool True if available, false otherwise
     */
    public function isAvailable(): bool
    {
        return $this->healthChecker->isHealthy();
    }

    /**
     * Get provider version.
     * 
     * @return string The provider version
     */
    public function getProviderVersion(): string
    {
        return $this->configuration->getVersion();
    }

    /**
     * Execute provider call with retry policy.
     * 
     * @param callable $operation The operation to execute
     * @return array The provider response
     */
    protected function executeWithRetry(callable $operation): array
    {
        return $this->retryPolicy->execute($operation);
    }

    /**
     * Execute the actual provider call.
     * To be implemented by concrete providers.
     * 
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param Coordinate[] $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return array The provider response
     */
    abstract protected function executeProviderCall(
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): array;

    /**
     * Convert NormalizedRoute to Route.
     * This is delegated to the application layer RouteFactory.
     * 
     * @param mixed $normalizedRoute The normalized route
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param Coordinate[] $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return Route The route
     */
    abstract protected function convertToRoute(
        $normalizedRoute,
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): Route;

    /**
     * Generate cache key for route request.
     * 
     * @param Coordinate $origin Origin coordinate
     * @param Coordinate $destination Destination coordinate
     * @param Coordinate[] $waypoints Waypoints
     * @param TravelMode $travelMode Travel mode
     * @return string The cache key
     */
    protected function generateCacheKey(
        Coordinate $origin,
        Coordinate $destination,
        array $waypoints,
        TravelMode $travelMode,
    ): string {
        $key = sprintf
(
            '%s_%s_%s_%s',
            $this->getProviderName(),
            $origin->latitude . ',' . $origin->longitude,
            $destination->latitude . ',' . $destination->longitude,
            $travelMode->value
        );

        if (!empty($waypoints)) {
            $key .= '_' . md5(json_encode($waypoints));
        }

        return $key;
    }

    /**
     * Get provider configuration.
     * 
     * @return ProviderConfiguration The configuration
     */
    public function getConfiguration(): ProviderConfiguration
    {
        return $this->configuration;
    }

    /**
     * Set provider configuration.
     * 
     * @param ProviderConfiguration $configuration The configuration
     */
    public function setConfiguration(ProviderConfiguration $configuration): void
    {
        $this->configuration = $configuration;
    }
}
