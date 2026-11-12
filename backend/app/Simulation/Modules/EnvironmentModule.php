<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Environment\EnvironmentEngine;
use App\Simulation\Environment\EnvironmentSnapshot;
use App\Simulation\Progress\RouteProgressEngine;

/**
 * EnvironmentModule
 *
 * Priority 20 — after Vehicle State Machine, before Vehicle Dynamics.
 * Publishes EnvironmentSnapshot for dynamics target-speed input.
 */
class EnvironmentModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'environment';

    private EngineContextInterface $context;

    public function __construct(
        private EnvironmentEngine $environmentEngine,
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
        $tickIndex = max(0, $this->context->getTickCount());
        $timestamp = $this->context->getSimulationTime()->format(\DateTime::ATOM);
        $snapshots = [];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $vehicleId = (string) $assignment->vehicle_id;
            $shipmentId = (string) $assignment->shipment_id;
            $progress = $this->routeProgressEngine->getProgress($shipmentId);
            $routeId = $progress?->routeId !== null && $progress->routeId !== ''
                ? $progress->routeId
                : 'shipment:' . $shipmentId;

            $snapshot = $this->environmentEngine->compute(
                vehicleId: $vehicleId,
                routeId: $routeId,
                tickIndex: $tickIndex,
                timestamp: $timestamp,
            );

            $snapshots[$vehicleId] = $snapshot->toArray();
            $snapshots[$vehicleId]['_snapshot'] = $snapshot;
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $snapshots);
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
        return 'environment';
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getDependencies(): array
    {
        return ['vehicle_state_machine'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
