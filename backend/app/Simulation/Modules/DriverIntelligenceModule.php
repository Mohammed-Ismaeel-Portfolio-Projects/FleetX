<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Driver\DriverIntelligenceEngine;
use App\Simulation\Driver\DriverProfile;
use App\Simulation\Environment\EnvironmentSnapshot;

/**
 * DriverIntelligenceModule
 *
 * Priority 25 — after Environment, before Vehicle Dynamics.
 * Produces desired target speed from environment + driver profile.
 */
class DriverIntelligenceModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'driver_intelligence';

    private EngineContextInterface $context;

    public function __construct(
        private DriverIntelligenceEngine $driverIntelligenceEngine,
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
        $environment = $this->context->getRegistry(EnvironmentModule::CONTEXT_KEY, []);
        if (!is_array($states)) {
            $states = [];
        }
        if (!is_array($environment)) {
            $environment = [];
        }

        $timestamp = $this->context->getSimulationTime()->format(\DateTime::ATOM);
        $decisions = [];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle', 'driver'])
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

            $envSnapshot = $this->resolveEnvironment($environment, $vehicleId);
            if ($envSnapshot === null) {
                continue;
            }

            $driverId = $assignment->driver_id !== null ? (string) $assignment->driver_id : null;
            $profile = DriverProfile::forIdentity($driverId, $vehicleId);

            $decision = $this->driverIntelligenceEngine->decide(
                vehicleId: $vehicleId,
                profile: $profile,
                environment: $envSnapshot,
                vehicleState: $operational,
                timestamp: $timestamp,
            );

            $decisions[$vehicleId] = $decision->toArray();
            $decisions[$vehicleId]['_decision'] = $decision;
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $decisions);
    }

    /**
     * @param array<string, mixed> $environment
     */
    private function resolveEnvironment(array $environment, string $vehicleId): ?EnvironmentSnapshot
    {
        $entry = $environment[$vehicleId] ?? null;

        if ($entry instanceof EnvironmentSnapshot) {
            return $entry;
        }

        if (is_array($entry)) {
            if (($entry['_snapshot'] ?? null) instanceof EnvironmentSnapshot) {
                return $entry['_snapshot'];
            }

            return EnvironmentSnapshot::fromArray($entry);
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
        return 'driver_intelligence';
    }

    public function getPriority(): int
    {
        return 25;
    }

    public function getDependencies(): array
    {
        return ['vehicle_state_machine', 'environment'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
