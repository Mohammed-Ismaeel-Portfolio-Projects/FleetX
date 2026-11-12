<?php

namespace App\Simulation\Routing\Application\Pipelines;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\DTOs\RoutePlanningResult;
use App\Simulation\Routing\Application\Services\RequestValidator;
use App\Simulation\Routing\Application\Resolvers\ProviderResolver;
use App\Simulation\Routing\Application\Services\ProviderExecutor;
use App\Simulation\Routing\Application\Services\ResponseMapperResolver;
use App\Simulation\Routing\Application\Services\ResultFactory;
use App\Simulation\Routing\Application\Factories\RouteFactory;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;
use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\Exceptions\RouteCalculationException;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;

/**
 * RoutingPipeline.
 * Orchestrates the route planning process.
 * Pipeline: Validate → Resolve Provider → Execute Provider → Map Response → Build Route → Create Result
 * 
 * This pipeline only orchestrates - all business logic is delegated to specialized services.
 */
class RoutingPipeline
{
    private RequestValidator $requestValidator;
    private ProviderResolver $providerResolver;
    private ProviderExecutor $providerExecutor;
    private ResponseMapperResolver $mapperResolver;
    private RouteFactory $routeFactory;
    private ResultFactory $resultFactory;

    public function __construct(
        RequestValidator $requestValidator,
        ProviderResolver $providerResolver,
        ProviderExecutor $providerExecutor,
        ResponseMapperResolver $mapperResolver,
        RouteFactory $routeFactory,
        ResultFactory $resultFactory,
    ) {
        $this->requestValidator = $requestValidator;
        $this->providerResolver = $providerResolver;
        $this->providerExecutor = $providerExecutor;
        $this->mapperResolver = $mapperResolver;
        $this->routeFactory = $routeFactory;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Execute the routing pipeline.
     *
     * @param RoutePlanningRequest $request The route planning request
     * @return RoutePlanningResult The route planning result
     * @throws RouteCalculationException
     * @throws RoutingProviderUnavailableException
     */
    public function execute(RoutePlanningRequest $request): RoutePlanningResult
    {
        $startTime = microtime(true);

        try {
            // Step 1: Validate Request
            $this->requestValidator->validate($request);

            // Step 2: Resolve Provider
            $provider = $this->resolveProvider($request);

            // Step 3: Execute Provider (with optional fallback on HTTP/API failure)
            try {
                $providerResponse = $this->providerExecutor->execute($provider, $request);
            } catch (RoutingProviderUnavailableException $e) {
                $fallback = $this->resolveFallbackProvider($provider, $request);
                if ($fallback === null) {
                    throw $e;
                }

                $provider = $fallback;
                $providerResponse = $this->providerExecutor->execute($provider, $request);
            }

            // Step 4: Map Response to NormalizedRoute
            $normalizedRoute = $this->mapProviderResponse($provider, $providerResponse, $request);

            // Step 5: Build Route Aggregate
            $route = $this->buildRoute($normalizedRoute, $request);

            // Step 6: Create Result
            $executionTime = (microtime(true) - $startTime) * 1000;

            return $this->resultFactory->createFromNormalizedRoute(
                normalizedRoute: $normalizedRoute,
                route: $route,
                planningId: $request->planningId,
                providerName: $provider->getProviderName(),
                executionTime: $executionTime,
            );

        } catch (InvalidRouteDataException $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            return $this->resultFactory->createFailure(
                planningId: $request->planningId,
                providerName: 'unknown',
                executionTime: $executionTime,
                errorMessage: $e->getMessage(),
                diagnostics: ['exception' => get_class($e)],
            );
        } catch (RoutingProviderUnavailableException $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            return $this->resultFactory->createFailure(
                planningId: $request->planningId,
                providerName: 'unknown',
                executionTime: $executionTime,
                errorMessage: $e->getMessage(),
                diagnostics: ['exception' => get_class($e), 'provider' => $e->getProvider()],
            );
        } catch (\Throwable $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            return $this->resultFactory->createFailure(
                planningId: $request->planningId,
                providerName: $this->providerResolver->getLastResolvedProviderName() ?? 'unknown',
                executionTime: $executionTime,
                errorMessage: $e->getMessage(),
                diagnostics: [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
        }
    }

    /**
     * Resolve the appropriate provider for the request.
     *
     * @throws RoutingProviderUnavailableException
     */
    private function resolveProvider(RoutePlanningRequest $request): RoutingProviderInterface
    {
        $provider = $this->providerResolver->resolve(
            $request->context->vehicle,
            $request->context->planningOptions->travelMode,
        );

        if (!$provider) {
            throw new RoutingProviderUnavailableException(
                "No available provider for vehicle type: {$request->context->vehicle->vehicleType->value}, travel mode: {$request->context->planningOptions->travelMode->value}"
            );
        }

        return $provider;
    }

    /**
     * Resolve fallback provider when the primary provider fails at execution time.
     */
    private function resolveFallbackProvider(
        RoutingProviderInterface $failedProvider,
        RoutePlanningRequest $request,
    ): ?RoutingProviderInterface {
        if (!config('routing.fallback_enabled', false)) {
            return null;
        }

        $fallbackName = (string) config('routing.fallback_provider', 'simulator');
        if ($fallbackName === '' || $fallbackName === $failedProvider->getProviderName()) {
            return null;
        }

        $fallback = $this->providerResolver->resolveByName($fallbackName);
        if (!$fallback || !$fallback->isAvailable()) {
            return null;
        }

        return $fallback;
    }

    /**
     * Map provider response to NormalizedRoute.
     */
    private function mapProviderResponse(
        RoutingProviderInterface $provider,
        array $providerResponse,
        RoutePlanningRequest $request,
    ): NormalizedRoute {
        $mapper = $this->mapperResolver->resolve($provider);

        if (!$mapper) {
            // Fallback: create empty normalized route
            return NormalizedRoute::empty();
        }

        // Validate provider response
        if (!$mapper->validateProviderResponse($providerResponse)) {
            throw new InvalidRouteDataException('Invalid provider response format');
        }

        // Map to normalized route
        return $mapper->mapToNormalizedRoute(
            providerResponse: $providerResponse,
            origin: $request->origin,
            destination: $request->destination,
            waypoints: $request->waypoints,
            travelMode: $request->context->planningOptions->travelMode,
        );
    }

    /**
     * Build the Route aggregate from NormalizedRoute.
     *
     * @param NormalizedRoute $normalizedRoute The normalized route
     * @param RoutePlanningRequest $request The original request
     * @return Route The built route
     */
    private function buildRoute(NormalizedRoute $normalizedRoute, RoutePlanningRequest $request)
    {
        return $this->routeFactory->createFromNormalizedRoute(
            normalizedRoute: $normalizedRoute,
            origin: $request->origin,
            destination: $request->destination,
            waypoints: $request->waypoints,
            travelMode: $request->context->planningOptions->travelMode,
            providerName: $this->providerResolver->getLastResolvedProviderName(),
        );
    }

    /**
     * Set request validator.
     */
    public function setRequestValidator(RequestValidator $validator): void
    {
        $this->requestValidator = $validator;
    }

    /**
     * Set provider resolver.
     */
    public function setProviderResolver(ProviderResolver $resolver): void
    {
        $this->providerResolver = $resolver;
    }

    /**
     * Set provider executor.
     */
    public function setProviderExecutor(ProviderExecutor $executor): void
    {
        $this->providerExecutor = $executor;
    }

    /**
     * Set response mapper resolver.
     */
    public function setMapperResolver(ResponseMapperResolver $resolver): void
    {
        $this->mapperResolver = $resolver;
    }

    /**
     * Set route factory.
     */
    public function setRouteFactory(RouteFactory $factory): void
    {
        $this->routeFactory = $factory;
    }

    /**
     * Set result factory.
     */
    public function setResultFactory(ResultFactory $factory): void
    {
        $this->resultFactory = $factory;
    }
}
