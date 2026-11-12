<?php

namespace App\Simulation\Analytics;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Driver\DriverDecisionSnapshot;
use App\Simulation\Driver\DriverIntelligenceEngine;
use App\Simulation\Environment\EnvironmentEngine;
use App\Simulation\Environment\EnvironmentSnapshot;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Health\VehicleHealthSnapshot;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleSnapshot;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Telemetry\FleetTelemetryEngine;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FleetAnalyticsEngine
 *
 * Read-only KPI aggregation from runtime caches.
 * Does not route, track, move, or tick the simulation.
 *
 * Primary inputs: Telemetry + Shipment Lifecycle.
 * Enrichment (optional): Vehicle Health, Driver Decision, Environment caches.
 */
class FleetAnalyticsEngine
{
    public const FLEET_CACHE_KEY = 'simulation:kpi:fleet';

    public const VEHICLE_CACHE_PREFIX = 'simulation:kpi:vehicle:';

    public const DRIVER_CACHE_PREFIX = 'simulation:kpi:driver:';

    public const SHIPMENT_CACHE_PREFIX = 'simulation:kpi:shipment:';

    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private FleetTelemetryEngine $telemetryEngine,
        private ShipmentLifecycleEngine $lifecycleEngine,
        private VehicleHealthEngine $healthEngine,
        private DriverIntelligenceEngine $driverEngine,
        private EnvironmentEngine $environmentEngine,
    ) {}

    /**
     * Build and cache KPI snapshots from current runtime caches.
     */
    public function compute(?string $timestamp = null): FleetKpiSnapshot
    {
        $timestamp ??= now()->toIso8601String();

        $vehicles = [];
        $drivers = [];
        $shipments = [];

        $moving = 0;
        $idle = 0;
        $stopped = 0;
        $offline = 0;
        $speedSum = 0.0;
        $speedCount = 0;
        $fuelSum = 0.0;
        $fuelCount = 0;
        $healthSum = 0.0;
        $healthCount = 0;
        $trafficSum = 0.0;
        $trafficCount = 0;
        $completionSum = 0.0;
        $completionCount = 0;

        $stageCounts = [];
        foreach (ShipmentLifecycleStage::cases() as $stage) {
            $stageCounts[$stage->value] = 0;
        }

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle', 'driver'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $vehicleId = (string) $assignment->vehicle_id;
            $shipmentId = (string) $assignment->shipment_id;
            $driverId = $assignment->driver_id !== null ? (string) $assignment->driver_id : null;

            $telemetry = $this->telemetryEngine->load($vehicleId);
            $lifecycle = $this->lifecycleEngine->load($shipmentId);
            $health = $this->healthEngine->load($vehicleId);
            $driverDecision = $this->driverEngine->load($vehicleId);
            $environment = $this->environmentEngine->load($vehicleId);

            // Primary contract: Telemetry + Lifecycle. Skip if neither present.
            if ($telemetry === null && $lifecycle === null) {
                continue;
            }

            $vehicleKpi = $this->buildVehicleKpi(
                $vehicleId,
                $shipmentId,
                $driverId,
                $telemetry,
                $lifecycle,
                $health,
                $driverDecision,
                $environment
            );
            $vehicles[$vehicleId] = $vehicleKpi;
            $this->cacheVehicle($vehicleId, $vehicleKpi);

            $state = (string) ($vehicleKpi['vehicle_state'] ?? 'offline');
            match ($state) {
                'moving' => $moving++,
                'idle' => $idle++,
                'stopped' => $stopped++,
                default => $offline++,
            };

            if (isset($vehicleKpi['current_speed_kmh'])) {
                $speedSum += (float) $vehicleKpi['current_speed_kmh'];
                $speedCount++;
            }
            if (isset($vehicleKpi['fuel_level']) && is_numeric($vehicleKpi['fuel_level'])) {
                $fuelSum += (float) $vehicleKpi['fuel_level'];
                $fuelCount++;
            }
            if (isset($vehicleKpi['engine_health']) && is_numeric($vehicleKpi['engine_health'])) {
                $healthSum += (float) $vehicleKpi['engine_health'];
                $healthCount++;
            }
            if (isset($vehicleKpi['traffic_density']) && is_numeric($vehicleKpi['traffic_density'])) {
                $trafficSum += (float) $vehicleKpi['traffic_density'];
                $trafficCount++;
            }
            if (isset($vehicleKpi['completion_percentage'])) {
                $completionSum += (float) $vehicleKpi['completion_percentage'];
                $completionCount++;
            }

            if ($driverId !== null) {
                $driverKpi = $this->buildDriverKpi($driverId, $vehicleId, $driverDecision, $telemetry, $environment);
                $drivers[$driverId] = $driverKpi;
                $this->cacheDriver($driverId, $driverKpi);
            }

            $shipmentKpi = $this->buildShipmentKpi($shipmentId, $vehicleId, $lifecycle, $telemetry);
            $shipments[$shipmentId] = $shipmentKpi;
            $this->cacheShipment($shipmentId, $shipmentKpi);

            $stage = (string) ($shipmentKpi['current_stage'] ?? 'Assigned');
            if (!isset($stageCounts[$stage])) {
                $stageCounts[$stage] = 0;
            }
            $stageCounts[$stage]++;
        }

        $activeVehicles = count($vehicles);
        $fleet = [
            'active_vehicles' => $activeVehicles,
            'moving_vehicles' => $moving,
            'idle_vehicles' => $idle,
            'stopped_vehicles' => $stopped,
            'offline_vehicles' => $offline,
            'active_shipments' => count($shipments),
            'active_drivers' => count($drivers),
            'avg_speed_kmh' => $speedCount > 0 ? round($speedSum / $speedCount, 2) : 0.0,
            'avg_fuel_level' => $fuelCount > 0 ? round($fuelSum / $fuelCount, 2) : null,
            'avg_engine_health' => $healthCount > 0 ? round($healthSum / $healthCount, 2) : null,
            'avg_traffic_density' => $trafficCount > 0 ? round($trafficSum / $trafficCount, 4) : null,
            'avg_route_completion_pct' => $completionCount > 0 ? round($completionSum / $completionCount, 2) : 0.0,
            'shipment_stage_counts' => $stageCounts,
            'utilization_pct' => $activeVehicles > 0
                ? round(($moving / $activeVehicles) * 100, 2)
                : 0.0,
        ];

        $snapshot = new FleetKpiSnapshot(
            timestamp: $timestamp,
            fleet: $fleet,
            vehicles: $vehicles,
            drivers: $drivers,
            shipments: $shipments,
        );

        Cache::put(self::FLEET_CACHE_KEY, $snapshot->toArray(), self::CACHE_TTL_SECONDS);

        Log::info('Fleet analytics computed', [
            'active_vehicles' => $fleet['active_vehicles'],
            'active_shipments' => $fleet['active_shipments'],
            'avg_speed_kmh' => $fleet['avg_speed_kmh'],
            'utilization_pct' => $fleet['utilization_pct'],
        ]);

        return $snapshot;
    }

    public function loadFleet(): ?FleetKpiSnapshot
    {
        $data = Cache::get(self::FLEET_CACHE_KEY);
        if (!is_array($data)) {
            return null;
        }

        return FleetKpiSnapshot::fromArray($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadVehicle(string $vehicleId): ?array
    {
        $data = Cache::get(self::VEHICLE_CACHE_PREFIX . $vehicleId);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadDriver(string $driverId): ?array
    {
        $data = Cache::get(self::DRIVER_CACHE_PREFIX . $driverId);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadShipment(string $shipmentId): ?array
    {
        $data = Cache::get(self::SHIPMENT_CACHE_PREFIX . $shipmentId);

        return is_array($data) ? $data : null;
    }

    /**
     * Return cached fleet KPIs or compute fresh.
     */
    public function getOrCompute(): FleetKpiSnapshot
    {
        return $this->loadFleet() ?? $this->compute();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVehicleKpi(
        string $vehicleId,
        string $shipmentId,
        ?string $driverId,
        ?TelemetrySnapshot $telemetry,
        ?ShipmentLifecycleSnapshot $lifecycle,
        ?VehicleHealthSnapshot $health,
        ?DriverDecisionSnapshot $driverDecision,
        ?EnvironmentSnapshot $environment,
    ): array {
        return [
            'vehicle_id' => $vehicleId,
            'shipment_id' => $shipmentId,
            'driver_id' => $driverId,
            'vehicle_state' => $telemetry?->vehicleState ?? 'offline',
            'current_speed_kmh' => $telemetry?->currentSpeedKmh ?? 0.0,
            'target_speed_kmh' => $telemetry?->targetSpeedKmh ?? ($driverDecision?->desiredTargetSpeed),
            'acceleration' => $telemetry?->acceleration ?? 0.0,
            'completion_percentage' => $telemetry?->completionPercentage ?? 0.0,
            'remaining_distance_m' => $telemetry?->remainingDistanceMeters ?? null,
            'odometer_m' => $telemetry?->odometerMeters ?? null,
            'average_speed_kmh' => $telemetry?->averageSpeedKmh ?? null,
            'fuel_level' => $telemetry?->fuelLevel ?? $health?->fuelLevel,
            'fuel_consumption_rate' => $telemetry?->fuelConsumptionRate ?? $health?->fuelConsumptionRate,
            'engine_temperature' => $telemetry?->engineTemperature ?? $health?->engineTemperature,
            'engine_health' => $telemetry?->engineHealth ?? $health?->engineHealth,
            'battery_level' => $telemetry?->batteryLevel ?? $health?->batteryLevel,
            'operational_status' => $health?->operationalStatus,
            'maintenance_score' => $health?->maintenanceScore,
            'lifecycle_stage' => $lifecycle?->currentStage,
            'driver_profile' => $driverDecision?->driverProfile->value,
            'desired_target_speed' => $driverDecision?->desiredTargetSpeed,
            'environment_recommended_speed' => $driverDecision?->environmentRecommendedSpeed
                ?? $environment?->recommendedTargetSpeedKmh,
            'traffic_density' => $environment?->trafficDensity,
            'road_event' => $environment?->roadEvent,
            'latitude' => $telemetry?->latitude,
            'longitude' => $telemetry?->longitude,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDriverKpi(
        string $driverId,
        string $vehicleId,
        ?DriverDecisionSnapshot $decision,
        ?TelemetrySnapshot $telemetry,
        ?EnvironmentSnapshot $environment,
    ): array {
        $envRec = $decision?->environmentRecommendedSpeed ?? $environment?->recommendedTargetSpeedKmh;
        $desired = $decision?->desiredTargetSpeed;

        return [
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'driver_profile' => $decision?->driverProfile->value ?? 'normal',
            'compliance_level' => $decision?->complianceLevel,
            'speed_bias' => $decision?->speedBias,
            'reaction_factor' => $decision?->reactionFactor,
            'risk_level' => $decision?->riskLevel ?? 'medium',
            'environment_recommended_speed' => $envRec,
            'desired_target_speed' => $desired,
            'speed_delta_kmh' => ($desired !== null && $envRec !== null)
                ? round($desired - $envRec, 2)
                : null,
            'current_speed_kmh' => $telemetry?->currentSpeedKmh,
            'vehicle_state' => $telemetry?->vehicleState,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShipmentKpi(
        string $shipmentId,
        string $vehicleId,
        ?ShipmentLifecycleSnapshot $lifecycle,
        ?TelemetrySnapshot $telemetry,
    ): array {
        return [
            'shipment_id' => $shipmentId,
            'vehicle_id' => $vehicleId,
            'current_stage' => $lifecycle?->currentStage ?? 'Assigned',
            'previous_stage' => $lifecycle?->previousStage,
            'transition_reason' => $lifecycle?->transitionReason,
            'completion_percentage' => $telemetry?->completionPercentage ?? 0.0,
            'remaining_distance_m' => $telemetry?->remainingDistanceMeters,
            'distance_travelled_m' => $telemetry?->distanceTravelledMeters,
            'route_completed' => $telemetry?->routeCompleted ?? false,
            'arrival_time' => $lifecycle?->arrivalTime,
            'departure_time' => $lifecycle?->departureTime,
            'delivery_time' => $lifecycle?->deliveryTime,
            'completion_time' => $lifecycle?->completionTime,
            'current_speed_kmh' => $telemetry?->currentSpeedKmh,
        ];
    }

    /**
     * @param array<string, mixed> $kpi
     */
    private function cacheVehicle(string $vehicleId, array $kpi): void
    {
        Cache::put(self::VEHICLE_CACHE_PREFIX . $vehicleId, $kpi, self::CACHE_TTL_SECONDS);
    }

    /**
     * @param array<string, mixed> $kpi
     */
    private function cacheDriver(string $driverId, array $kpi): void
    {
        Cache::put(self::DRIVER_CACHE_PREFIX . $driverId, $kpi, self::CACHE_TTL_SECONDS);
    }

    /**
     * @param array<string, mixed> $kpi
     */
    private function cacheShipment(string $shipmentId, array $kpi): void
    {
        Cache::put(self::SHIPMENT_CACHE_PREFIX . $shipmentId, $kpi, self::CACHE_TTL_SECONDS);
    }
}
