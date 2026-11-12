<?php

namespace App\Simulation\Modules;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Driver\DriverDecisionSnapshot;
use App\Simulation\Dynamics\DynamicsTickResult;
use App\Simulation\Environment\EnvironmentSnapshot;
use App\Simulation\Health\VehicleHealthSnapshot;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\FleetTelemetryEngine;
use App\Simulation\Telemetry\TelemetrySnapshot;

/**
 * TelemetryModule (Fleet Telemetry)
 *
 * Priority 60 — after Route Progress, before Tracking.
 * Produces one TelemetrySnapshot per active vehicle per tick.
 */
class TelemetryModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'fleet_telemetry';

    private const TICK_SECONDS = 60.0;

    private EngineContextInterface $context;

    public function __construct(
        private FleetTelemetryEngine $telemetryEngine,
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
        $dynamics = $this->context->getRegistry(VehicleDynamicsModule::CONTEXT_KEY, []);
        $routeRuntime = $this->context->getRegistry(RouteProgressModule::CONTEXT_KEY, []);
        $environment = $this->context->getRegistry(EnvironmentModule::CONTEXT_KEY, []);
        $driverIntel = $this->context->getRegistry(DriverIntelligenceModule::CONTEXT_KEY, []);
        $vehicleHealth = $this->context->getRegistry(VehicleHealthModule::CONTEXT_KEY, []);

        if (!is_array($states)) {
            $states = [];
        }
        if (!is_array($dynamics)) {
            $dynamics = [];
        }
        if (!is_array($routeRuntime)) {
            $routeRuntime = [];
        }
        if (!is_array($environment)) {
            $environment = [];
        }
        if (!is_array($driverIntel)) {
            $driverIntel = [];
        }
        if (!is_array($vehicleHealth)) {
            $vehicleHealth = [];
        }

        $snapshots = [];
        $timestamp = $this->context->getSimulationTime()->format(\DateTime::ATOM);

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle'])
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

            $tickDynamics = $this->resolveDynamics($dynamics, $vehicleId);
            $routeEntry = $routeRuntime[$vehicleId] ?? null;

            $progress = null;
            $trackingRuntime = null;

            if (is_array($routeEntry)) {
                $progress = ($routeEntry['progress'] ?? null) instanceof RouteProgressState
                    ? $routeEntry['progress']
                    : null;
                $trackingRuntime = is_array($routeEntry['tracking_runtime'] ?? null)
                    ? $routeEntry['tracking_runtime']
                    : null;
                if (isset($routeEntry['operational_state']) && is_string($routeEntry['operational_state'])) {
                    $operational = $routeEntry['operational_state'];
                }
            }

            $snapshot = $this->telemetryEngine->build(
                vehicleId: $vehicleId,
                shipmentId: (string) $assignment->shipment_id,
                vehicleState: $operational,
                dynamics: $tickDynamics,
                routeProgress: $progress,
                trackingRuntime: $trackingRuntime,
                tickSeconds: self::TICK_SECONDS,
                timestamp: $timestamp,
            );

            // Populate existing telemetry placeholders from health (engine structure unchanged).
            $healthSnap = $this->resolveHealth($vehicleHealth, $vehicleId);
            if ($healthSnap !== null) {
                $data = array_merge($snapshot->toArray(), $healthSnap->toTelemetryPlaceholders());
                $snapshot = TelemetrySnapshot::fromArray($data);
                $this->telemetryEngine->store($snapshot);
            }

            $snapshots[$vehicleId] = $snapshot->toArray();
            $snapshots[$vehicleId]['_snapshot'] = $snapshot;

            // Environment / driver / health references — FleetTelemetryEngine structure unchanged.
            $envRef = $this->resolveEnvironmentReference($environment, $vehicleId);
            if ($envRef !== null) {
                $snapshots[$vehicleId]['environment'] = $envRef;
            }

            $driverRef = $this->resolveDriverReference($driverIntel, $vehicleId);
            if ($driverRef !== null) {
                $snapshots[$vehicleId]['driver'] = $driverRef;
            }

            if ($healthSnap !== null) {
                $snapshots[$vehicleId]['vehicle_health'] = [
                    'operational_status' => $healthSnap->operationalStatus,
                    'maintenance_score' => $healthSnap->maintenanceScore,
                    'brake_wear' => $healthSnap->brakeWear,
                    'tyre_wear' => $healthSnap->tyreWear,
                    'recommended_max_speed' => $healthSnap->recommendedMaxSpeed,
                ];
            }
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $snapshots);
    }

    /**
     * @param array<string, mixed> $dynamics
     */
    private function resolveDynamics(array $dynamics, string $vehicleId): ?DynamicsTickResult
    {
        $cached = $dynamics[$vehicleId] ?? null;
        if ($cached instanceof DynamicsTickResult) {
            return $cached;
        }
        if (is_array($cached) && ($cached['_result'] ?? null) instanceof DynamicsTickResult) {
            return $cached['_result'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $environment
     * @return array<string, mixed>|null
     */
    private function resolveEnvironmentReference(array $environment, string $vehicleId): ?array
    {
        $entry = $environment[$vehicleId] ?? null;

        if ($entry instanceof EnvironmentSnapshot) {
            return $entry->toTelemetryReference();
        }

        if (is_array($entry)) {
            if (($entry['_snapshot'] ?? null) instanceof EnvironmentSnapshot) {
                return $entry['_snapshot']->toTelemetryReference();
            }

            return [
                'road_speed_limit_kmh' => $entry['road_speed_limit_kmh'] ?? null,
                'traffic_density' => $entry['traffic_density'] ?? null,
                'traffic_factor' => $entry['traffic_factor'] ?? null,
                'recommended_target_speed_kmh' => $entry['recommended_target_speed_kmh'] ?? null,
                'road_event' => $entry['road_event'] ?? null,
                'weather_condition' => $entry['weather_condition'] ?? null,
                'road_condition' => $entry['road_condition'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $driverIntel
     * @return array<string, mixed>|null
     */
    private function resolveDriverReference(array $driverIntel, string $vehicleId): ?array
    {
        $entry = $driverIntel[$vehicleId] ?? null;

        if ($entry instanceof DriverDecisionSnapshot) {
            return $entry->toTelemetryReference();
        }

        if (is_array($entry)) {
            if (($entry['_decision'] ?? null) instanceof DriverDecisionSnapshot) {
                return $entry['_decision']->toTelemetryReference();
            }

            return [
                'driver_profile' => $entry['driver_profile'] ?? null,
                'environment_recommended_speed' => $entry['environment_recommended_speed'] ?? null,
                'desired_target_speed' => $entry['desired_target_speed'] ?? null,
                'compliance_level' => $entry['compliance_level'] ?? null,
                'risk_level' => $entry['risk_level'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $vehicleHealth
     */
    private function resolveHealth(array $vehicleHealth, string $vehicleId): ?VehicleHealthSnapshot
    {
        $entry = $vehicleHealth[$vehicleId] ?? null;
        if ($entry instanceof VehicleHealthSnapshot) {
            return $entry;
        }
        if (is_array($entry) && ($entry['_snapshot'] ?? null) instanceof VehicleHealthSnapshot) {
            return $entry['_snapshot'];
        }
        if (is_array($entry) && isset($entry['fuel_level'])) {
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
        return 'telemetry';
    }

    public function getPriority(): int
    {
        return 60;
    }

    public function getDependencies(): array
    {
        return ['vehicle_state_machine', 'vehicle_dynamics', 'route_progress'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
