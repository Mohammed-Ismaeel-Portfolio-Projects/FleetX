<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Driver\DriverDecisionSnapshot;
use App\Simulation\Driver\DriverProfile;
use App\Simulation\Environment\EnvironmentSnapshot;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;

/**
 * VehicleHealthModule
 *
 * Priority 27 — after Driver Intelligence, before Vehicle Dynamics.
 * Produces health/fuel snapshot and operational constraints for dynamics.
 */
class VehicleHealthModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'vehicle_health';

    private const TICK_SECONDS = 60.0;

    private EngineContextInterface $context;

    public function __construct(
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
        $environment = $this->context->getRegistry(EnvironmentModule::CONTEXT_KEY, []);
        $driverIntel = $this->context->getRegistry(DriverIntelligenceModule::CONTEXT_KEY, []);

        if (!is_array($states)) {
            $states = [];
        }
        if (!is_array($environment)) {
            $environment = [];
        }
        if (!is_array($driverIntel)) {
            $driverIntel = [];
        }

        $timestamp = $this->context->getSimulationTime()->format(\DateTime::ATOM);
        $health = [];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle', 'trailer', 'driver'])
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

            $env = $this->resolveEnvironment($environment, $vehicleId);
            $driverProfile = $this->resolveDriverProfile($driverIntel, $vehicleId, $assignment);

            $snapshot = $this->healthEngine->compute(
                vehicleId: $vehicleId,
                vehicleProfile: VehicleProfileFactory::fromAssignment($assignment),
                driverProfile: $driverProfile,
                vehicleState: $operational,
                trafficDensity: $env?->trafficDensity ?? 0.3,
                roadSpeedLimitKmh: $env?->roadSpeedLimitKmh ?? 90.0,
                tickSeconds: self::TICK_SECONDS,
                timestamp: $timestamp,
            );

            $health[$vehicleId] = $snapshot->toArray();
            $health[$vehicleId]['_snapshot'] = $snapshot;
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $health);
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
        if (is_array($entry) && ($entry['_snapshot'] ?? null) instanceof EnvironmentSnapshot) {
            return $entry['_snapshot'];
        }
        if (is_array($entry)) {
            return EnvironmentSnapshot::fromArray($entry);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $driverIntel
     */
    private function resolveDriverProfile(array $driverIntel, string $vehicleId, Assignment $assignment): DriverProfile
    {
        $entry = $driverIntel[$vehicleId] ?? null;
        if ($entry instanceof DriverDecisionSnapshot) {
            return $entry->driverProfile;
        }
        if (is_array($entry) && ($entry['_decision'] ?? null) instanceof DriverDecisionSnapshot) {
            return $entry['_decision']->driverProfile;
        }
        if (is_array($entry) && isset($entry['driver_profile'])) {
            return DriverProfile::tryFrom((string) $entry['driver_profile']) ?? DriverProfile::Normal;
        }

        $driverId = $assignment->driver_id !== null ? (string) $assignment->driver_id : null;

        return DriverProfile::forIdentity($driverId, $vehicleId);
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
        return 'vehicle_health';
    }

    public function getPriority(): int
    {
        return 27;
    }

    public function getDependencies(): array
    {
        return ['vehicle_state_machine', 'environment', 'driver_intelligence'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
