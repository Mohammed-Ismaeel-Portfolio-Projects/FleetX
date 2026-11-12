<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Routing\Application\DTOs\RoutePlanningResult;
use App\Simulation\Routing\Domain\Models\Route;
use App\Simulation\Routing\Application\DTOs\NormalizedRoute;
use App\Simulation\Routing\Application\Enums\PlanningStatus;

/**
 * ResultFactory Service.
 * Creates RoutePlanningResult from Route and metadata.
 */
class ResultFactory
{
    /**
     * Create successful result.
     *
     * @param Route $route The route
     * @param string $planningId The planning ID
     * @param string $providerName The provider name
     * @param float $executionTime Execution time in milliseconds
     * @param array $metadata Metadata
     * @param array $warnings Warnings
     * @param array $diagnostics Diagnostics
     * @return RoutePlanningResult The result
     */
    public function createSuccess(
        Route $route,
        string $planningId,
        string $providerName,
        float $executionTime,
        array $metadata = [],
        array $warnings = [],
        array $diagnostics = [],
    ): RoutePlanningResult {
        return new RoutePlanningResult(
            planningId: $planningId,
            route: $route,
            providerName: $providerName,
            executionTime: $executionTime,
            planningStatus: PlanningStatus::SUCCESS,
            metadata: $metadata,
            warnings: $warnings,
            diagnostics: $diagnostics,
        );
    }

    /**
     * Create failed result.
     *
     * @param string $planningId The planning ID
     * @param string $providerName The provider name
     * @param float $executionTime Execution time in milliseconds
     * @param string $errorMessage Error message
     * @param array $diagnostics Diagnostics
     * @return RoutePlanningResult The result
     */
    public function createFailure(
        string $planningId,
        string $providerName,
        float $executionTime,
        string $errorMessage,
        array $diagnostics = [],
    ): RoutePlanningResult {
        return new RoutePlanningResult(
            planningId: $planningId,
            route: null,
            providerName: $providerName,
            executionTime: $executionTime,
            planningStatus: PlanningStatus::FAILURE,
            metadata: [],
            warnings: [],
            diagnostics: array_merge($diagnostics, ['error' => $errorMessage]),
        );
    }

    /**
     * Create partial result (fallback).
     *
     * @param Route $route The route
     * @param string $planningId The planning ID
     * @param string $providerName The provider name
     * @param float $executionTime Execution time in milliseconds
     * @param array $warnings Warnings
     * @param array $diagnostics Diagnostics
     * @return RoutePlanningResult The result
     */
    public function createPartial(
        Route $route,
        string $planningId,
        string $providerName,
        float $executionTime,
        array $warnings = [],
        array $diagnostics = [],
    ): RoutePlanningResult {
        return new RoutePlanningResult(
            planningId: $planningId,
            route: $route,
            providerName: $providerName,
            executionTime: $executionTime,
            planningStatus: PlanningStatus::PARTIAL,
            metadata: [],
            warnings: $warnings,
            diagnostics: $diagnostics,
        );
    }

    /**
     * Create result from NormalizedRoute.
     *
     * @param NormalizedRoute $normalizedRoute The normalized route
     * @param Route $route The built route aggregate
     * @param string $planningId The planning ID
     * @param string $providerName The provider name
     * @param float $executionTime Execution time in milliseconds
     * @return RoutePlanningResult The result
     */
    public function createFromNormalizedRoute(
        NormalizedRoute $normalizedRoute,
        Route $route,
        string $planningId,
        string $providerName,
        float $executionTime,
    ): RoutePlanningResult {
        return $this->createSuccess(
            route: $route,
            planningId: $planningId,
            providerName: $providerName,
            executionTime: $executionTime,
            metadata: $normalizedRoute->metadata,
            warnings: $normalizedRoute->warnings,
            diagnostics: $normalizedRoute->diagnostics,
        );
    }
}
