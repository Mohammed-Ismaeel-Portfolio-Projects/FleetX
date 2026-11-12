<?php

namespace App\Simulation\Adapters;

use App\Services\TrackingSimulator\TrackingSimulatorService;
use App\Simulation\Lifecycle\ShipmentLifecycleSnapshot;
use App\Simulation\Telemetry\TelemetrySnapshot;

/**
 * TrackingSimulationAdapter
 *
 * Bridges SimulationEngine ticks to TrackingSimulatorService.
 * Passes Fleet Telemetry + Shipment Lifecycle snapshots.
 */
class TrackingSimulationAdapter
{
    /**
     * @var array{
     *     vehicles_processed?: int,
     *     tracking_logs_created: int,
     *     vehicles_moved: int,
     *     idle_vehicles: int,
     *     stopped_vehicles: int,
     *     offline_vehicles: int
     * }|null
     */
    private ?array $lastResult = null;

    /**
     * @var array<string, TelemetrySnapshot|array<string, mixed>>
     */
    private array $telemetryByVehicleId = [];

    /**
     * @var array<string, mixed>
     */
    private array $lifecycleByShipmentId = [];

    public function __construct(
        private TrackingSimulatorService $trackingSimulatorService
    ) {}

    /**
     * @param array<string, TelemetrySnapshot|array<string, mixed>> $telemetryByVehicleId
     */
    public function setTelemetryByVehicleId(array $telemetryByVehicleId): void
    {
        $this->telemetryByVehicleId = $telemetryByVehicleId;
    }

    /**
     * @param array<string, mixed> $lifecycleByShipmentId
     */
    public function setLifecycleByShipmentId(array $lifecycleByShipmentId): void
    {
        $this->lifecycleByShipmentId = $lifecycleByShipmentId;
    }

    /**
     * @deprecated Prefer setTelemetryByVehicleId — kept for transitional callers
     * @param array<string, mixed> $resolvedVehicleStates
     */
    public function setResolvedVehicleStates(array $resolvedVehicleStates): void
    {
        // no-op
    }

    /**
     * @deprecated Prefer setTelemetryByVehicleId
     * @param array<string, mixed> $dynamicsByVehicleId
     */
    public function setDynamicsByVehicleId(array $dynamicsByVehicleId): void
    {
        // no-op
    }

    /**
     * @return array{
     *     vehicles_processed: int,
     *     tracking_logs_created: int,
     *     vehicles_moved: int,
     *     idle_vehicles: int,
     *     stopped_vehicles: int,
     *     offline_vehicles: int
     * }
     */
    public function execute(): array
    {
        $this->lastResult = $this->trackingSimulatorService->simulate(
            $this->telemetryByVehicleId,
            $this->lifecycleByShipmentId
        );

        return $this->lastResult;
    }

    /**
     * @return array{
     *     vehicles_processed: int,
     *     tracking_logs_created: int,
     *     vehicles_moved: int,
     *     idle_vehicles: int,
     *     stopped_vehicles: int,
     *     offline_vehicles: int
     * }
     */
    public function getLastResult(): array
    {
        return $this->lastResult ?? [
            'vehicles_processed' => 0,
            'tracking_logs_created' => 0,
            'vehicles_moved' => 0,
            'idle_vehicles' => 0,
            'stopped_vehicles' => 0,
            'offline_vehicles' => 0,
        ];
    }

    public function clearLastResult(): void
    {
        $this->lastResult = null;
        $this->telemetryByVehicleId = [];
        $this->lifecycleByShipmentId = [];
    }
}
