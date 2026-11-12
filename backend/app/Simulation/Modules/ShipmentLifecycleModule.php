<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleSnapshot;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\TelemetrySnapshot;

/**
 * ShipmentLifecycleModule
 *
 * Priority 65 — after Telemetry, before Tracking.
 * Advances shipment stages from telemetry + route progress + vehicle state.
 */
class ShipmentLifecycleModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'shipment_lifecycle';

    private EngineContextInterface $context;

    public function __construct(
        private ShipmentLifecycleEngine $lifecycleEngine,
        private RouteProgressEngine $routeProgressEngine,
    ) {}

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
    }

    public function start(): void
    {
        //
    }

    public function tick(): void
    {
        $states = $this->context->getRegistry(VehicleStateMachineModule::CONTEXT_KEY, []);
        $telemetry = $this->context->getRegistry(TelemetryModule::CONTEXT_KEY, []);
        if (!is_array($states)) {
            $states = [];
        }
        if (!is_array($telemetry)) {
            $telemetry = [];
        }

        $timestamp = $this->context->getSimulationTime()->format(\DateTime::ATOM);
        $lifecycles = ['by_vehicle' => []];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $vehicleId = (string) $assignment->vehicle_id;
            $shipmentId = (string) $assignment->shipment_id;

            $operational = $states[$vehicleId]
                ?? $states['by_assignment'][$assignment->id]
                ?? 'offline';
            if (!is_string($operational)) {
                $operational = 'offline';
            }

            $tel = $this->resolveTelemetry($telemetry, $vehicleId);
            $progress = $this->routeProgressEngine->getProgress($shipmentId);

            // Prefer route progress embedded in telemetry when available.
            if ($tel !== null && $progress === null && $tel->routeCompleted) {
                $progress = new RouteProgressState(
                    routeId: 'telemetry:' . $shipmentId,
                    shipmentId: $shipmentId,
                    vehicleId: $vehicleId,
                    polylineIndex: $tel->polylineIndex,
                    segmentIndex: $tel->currentSegment,
                    distanceTravelledM: $tel->distanceTravelledMeters,
                    remainingDistanceM: $tel->remainingDistanceMeters,
                    completionPercentage: $tel->completionPercentage,
                    currentLatitude: $tel->latitude,
                    currentLongitude: $tel->longitude,
                    heading: $tel->heading,
                    completed: $tel->routeCompleted,
                    lastTick: $timestamp,
                    totalDistanceM: $tel->distanceTravelledMeters + $tel->remainingDistanceMeters,
                );
            }

            $snapshot = $this->lifecycleEngine->evaluate(
                shipmentId: $shipmentId,
                vehicleState: $operational,
                telemetry: $tel,
                progress: $progress,
                timestamp: $timestamp,
            );

            $lifecycles[$shipmentId] = $snapshot->toArray();
            $lifecycles[$shipmentId]['_snapshot'] = $snapshot;
            $lifecycles['by_vehicle'][$vehicleId] = $lifecycles[$shipmentId];
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $lifecycles);
    }

    /**
     * @param array<string, mixed> $telemetry
     */
    private function resolveTelemetry(array $telemetry, string $vehicleId): ?TelemetrySnapshot
    {
        $entry = $telemetry[$vehicleId] ?? null;
        if ($entry instanceof TelemetrySnapshot) {
            return $entry;
        }
        if (is_array($entry) && ($entry['_snapshot'] ?? null) instanceof TelemetrySnapshot) {
            return $entry['_snapshot'];
        }
        if (is_array($entry) && isset($entry['shipment_id'])) {
            return TelemetrySnapshot::fromArray($entry);
        }

        return null;
    }

    public function pause(): void
    {
        //
    }

    public function resume(): void
    {
        //
    }

    public function stop(): void
    {
        //
    }

    public function shutdown(): void
    {
        //
    }

    public function getName(): string
    {
        return 'shipment_lifecycle';
    }

    public function getPriority(): int
    {
        return 65;
    }

    public function getDependencies(): array
    {
        return ['telemetry', 'route_progress', 'vehicle_state_machine'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
