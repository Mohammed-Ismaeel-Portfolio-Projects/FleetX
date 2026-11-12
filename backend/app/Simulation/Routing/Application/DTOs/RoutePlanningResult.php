<?php

namespace App\Simulation\Routing\Application\DTOs;

use App\Simulation\Routing\Domain\Models\Route;
use App\Simulation\Routing\Application\Enums\PlanningStatus;

/**
 * RoutePlanningResult DTO.
 * Represents the result of route planning.
 */
readonly class RoutePlanningResult
{
    public function __construct(
        public string $planningId,
        public ?Route $route,
        public string $providerName,
        public float $executionTime, // milliseconds
        public PlanningStatus $planningStatus,
        public array $metadata,
        public array $warnings,
        public array $diagnostics,
    ) {}

    /**
     * Check if result is successful.
     */
    public function isSuccess(): bool
    {
        return $this->planningStatus === PlanningStatus::SUCCESS;
    }

    /**
     * Check if result is failed.
     */
    public function isFailed(): bool
    {
        return $this->planningStatus === PlanningStatus::FAILURE;
    }

    /**
     * Check if result is partial.
     */
    public function isPartial(): bool
    {
        return $this->planningStatus === PlanningStatus::PARTIAL;
    }

    /**
     * Check if result has warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Check if result has diagnostics.
     */
    public function hasDiagnostics(): bool
    {
        return !empty($this->diagnostics);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'planning_id' => $this->planningId,
            'route' => $this->route?->toArray(),
            'provider_name' => $this->providerName,
            'execution_time' => $this->executionTime,
            'planning_status' => $this->planningStatus->value,
            'metadata' => $this->metadata,
            'warnings' => $this->warnings,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
