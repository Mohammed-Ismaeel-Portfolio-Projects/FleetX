<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Models\TrackingLog;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Domain\Vehicle\Services\VehicleStateMachineService;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Progress\RouteProgressEngine;
use App\Services\TrackingSimulator\EuropeanFleetLocations;

/**
 * VehicleStateMachineModule
 *
 * Runs before TrackingSimulationModule. Resolves each active vehicle's
 * VehicleSimulationState via StateTransitionMap and stores operational
 * states on the engine context for the tracking adapter.
 */
class VehicleStateMachineModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'resolved_vehicle_states';

    private EngineContextInterface $context;

    public function __construct(
        private VehicleStateMachineService $stateMachine,
        private EuropeanFleetLocations $fleetLocations,
        private RouteProgressEngine $routeProgressEngine,
        private ?JourneyRestEngine $journeyRestEngine = null,
    ) {}

    private function journey(): JourneyRestEngine
    {
        return $this->journeyRestEngine ??= JourneyRestEngine::fromContainer();
    }

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
        $resolved = [];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $vehicleId = (string) $assignment->vehicle_id;
            $tickContext = $this->buildTickContext($assignment);
            $result = $this->stateMachine->resolveForTick($vehicleId, $tickContext);

            $resolved[$vehicleId] = $result['operational_state'];
            $resolved['by_assignment'][$assignment->id] = $result['operational_state'];
            $resolved['details'][$vehicleId] = [
                'simulation_state' => $result['simulation_state']->value,
                'operational_state' => $result['operational_state'],
                'transitions' => $result['transitions'],
            ];
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $resolved);
    }

    /**
     * @return array{has_active_assignment: bool, arrived_at_destination: bool, has_gps_fix: bool}
     */
    private function buildTickContext(Assignment $assignment): array
    {
        $shipmentId = (string) $assignment->shipment_id;
        $progress = $this->routeProgressEngine->getProgress($shipmentId);
        $lastLog = TrackingLog::where('shipment_id', $assignment->shipment_id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $arrived = $progress?->completed === true;
        if (!$arrived && $progress !== null && $progress->remainingDistanceM <= 100.0) {
            $arrived = true;
        }

        // Fallback arrival check only when no route progress exists yet.
        if (!$arrived && $progress === null && $lastLog !== null) {
            $shipment = $assignment->shipment;
            $deliveryCity = (string) ($shipment->delivery_city ?? 'Paris');
            $destination = $this->fleetLocations->resolveCity($deliveryCity);
            $distance = $this->distanceMeters(
                (float) $lastLog->latitude,
                (float) $lastLog->longitude,
                (float) $destination['latitude'],
                (float) $destination['longitude']
            );
            $arrived = $distance <= 100.0;
        }

        return [
            'has_active_assignment' => true,
            'arrived_at_destination' => $arrived,
            'has_gps_fix' => $lastLog !== null || $progress !== null,
            'is_resting' => $this->journey()->shouldForceIdle($shipmentId),
        ];
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Reuse movement service bearing path distance via haversine on fleet helper
        $earthRadius = 6371000;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
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
        return 'vehicle_state_machine';
    }

    public function getPriority(): int
    {
        // Lower runs first — before TrackingSimulationModule (50).
        return 10;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
