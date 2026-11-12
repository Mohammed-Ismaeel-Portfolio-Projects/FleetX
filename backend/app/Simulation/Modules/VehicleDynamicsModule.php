<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Driver\DriverDecisionSnapshot;
use App\Simulation\Dynamics\VehicleDynamicsEngine;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Health\VehicleHealthSnapshot;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;

/**
 * VehicleDynamicsModule
 *
 * Runs after Driver Intelligence + Vehicle Health.
 * Consumes desired target speed; applies health max-speed / accel constraints.
 */
class VehicleDynamicsModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'vehicle_dynamics';

    private const TICK_SECONDS = 60.0;

    private EngineContextInterface $context;

    public function __construct(
        private VehicleDynamicsEngine $dynamicsEngine,
        private RouteProgressEngine $routeProgressEngine,
        private VehicleHealthEngine $healthEngine,
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
        $driverDecisions = $this->context->getRegistry(DriverIntelligenceModule::CONTEXT_KEY, []);
        $health = $this->context->getRegistry(VehicleHealthModule::CONTEXT_KEY, []);
        if (!is_array($states)) {
            $states = [];
        }
        if (!is_array($driverDecisions)) {
            $driverDecisions = [];
        }
        if (!is_array($health)) {
            $health = [];
        }

        $dynamics = [];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle', 'trailer'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $vehicleId = (string) $assignment->vehicle_id;
            $operational = $states[$vehicleId]
                ?? $states['by_assignment'][$assignment->id]
                ?? 'offline';

            if (!is_string($operational)) {
                $operational = 'offline';
            }

            $progress = $this->routeProgressEngine->getProgress((string) $assignment->shipment_id);
            $remaining = $progress?->remainingDistanceM;
            $desired = $this->resolveDesiredTarget($driverDecisions, $vehicleId);
            $healthSnap = $this->resolveHealth($health, $vehicleId);

            if ($healthSnap !== null && $desired !== null) {
                $desired = min($desired, $healthSnap->recommendedMaxSpeed);
            }

            if ($healthSnap !== null && $healthSnap->operationalStatus === 'unavailable') {
                $desired = 0.0;
            }

            $profile = VehicleProfileFactory::fromAssignment($assignment);
            $result = $this->dynamicsEngine->computeTick(
                vehicleId: $vehicleId,
                operationalState: $operational,
                profile: $profile,
                tickSeconds: self::TICK_SECONDS,
                remainingDistanceM: $remaining,
                recommendedTargetSpeedKmh: $desired,
                healthMaxSpeedKmh: $healthSnap?->recommendedMaxSpeed,
                healthAccelerationScale: $healthSnap?->accelerationScale,
            );

            // Feed actual accel back into health cache for next tick's fuel model.
            if ($healthSnap !== null) {
                $this->healthEngine->rememberAcceleration($vehicleId, $result->accelerationMs2);
            }

            $dynamics[$vehicleId] = $result->toArray();
            $dynamics[$vehicleId]['_result'] = $result;
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $dynamics);
    }

    /**
     * @param array<string, mixed> $driverDecisions
     */
    private function resolveDesiredTarget(array $driverDecisions, string $vehicleId): ?float
    {
        $entry = $driverDecisions[$vehicleId] ?? null;

        if ($entry instanceof DriverDecisionSnapshot) {
            return $entry->desiredTargetSpeed;
        }

        if (is_array($entry)) {
            if (($entry['_decision'] ?? null) instanceof DriverDecisionSnapshot) {
                return $entry['_decision']->desiredTargetSpeed;
            }
            if (isset($entry['desired_target_speed'])) {
                return (float) $entry['desired_target_speed'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $health
     */
    private function resolveHealth(array $health, string $vehicleId): ?VehicleHealthSnapshot
    {
        $entry = $health[$vehicleId] ?? null;
        if ($entry instanceof VehicleHealthSnapshot) {
            return $entry;
        }
        if (is_array($entry) && ($entry['_snapshot'] ?? null) instanceof VehicleHealthSnapshot) {
            return $entry['_snapshot'];
        }
        if (is_array($entry)) {
            return VehicleHealthSnapshot::fromArray($entry);
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
        return 'vehicle_dynamics';
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getDependencies(): array
    {
        return ['vehicle_state_machine', 'driver_intelligence', 'vehicle_health'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
