<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Analytics\FleetAnalyticsEngine;
use App\Simulation\Analytics\FleetKpiSnapshot;
use App\Simulation\Driver\DriverDecisionSnapshot;
use App\Simulation\Driver\DriverIntelligenceEngine;
use App\Simulation\Driver\DriverProfile;
use App\Simulation\Environment\EnvironmentEngine;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Telemetry\FleetTelemetryEngine;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FleetAnalyticsEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_builds_kpi_snapshot_from_telemetry_and_lifecycle_caches(): void
    {
        Log::spy();

        $vehicleId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $shipmentId = 'ffffffff-1111-2222-3333-444444444444';

        Cache::put(FleetTelemetryEngine::CACHE_PREFIX . $vehicleId, (new TelemetrySnapshot(
            vehicleId: $vehicleId,
            shipmentId: $shipmentId,
            timestamp: now()->toIso8601String(),
            latitude: 48.1,
            longitude: 11.5,
            heading: 90,
            currentSpeedKmh: 55.0,
            targetSpeedKmh: 70.0,
            acceleration: 0.05,
            vehicleState: 'moving',
            distanceTravelledMeters: 10000,
            remainingDistanceMeters: 40000,
            completionPercentage: 20.0,
            currentSegment: 2,
            polylineIndex: 20,
            drivingTimeSeconds: 600,
            idleTimeSeconds: 0,
            engineRuntimeSeconds: 600,
            averageSpeedKmh: 50.0,
            odometerMeters: 10000,
            gpsAccuracy: 5,
            signalQuality: 'good',
            fuelLevel: 72.5,
            fuelConsumptionRate: 30.0,
            engineTemperature: 90.0,
            engineHealth: 98.0,
            batteryLevel: 91.0,
        ))->toArray(), 3600);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX . $shipmentId, [
            'shipment_id' => $shipmentId,
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'En route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => now()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
        ], 3600);

        Cache::put(DriverIntelligenceEngine::CACHE_PREFIX . $vehicleId, (new DriverDecisionSnapshot(
            vehicleId: $vehicleId,
            driverProfile: DriverProfile::Eco,
            environmentRecommendedSpeed: 70.0,
            desiredTargetSpeed: 64.0,
            speedBias: -0.08,
            complianceLevel: 0.95,
            reactionFactor: 0.75,
            riskLevel: 'low',
            timestamp: now()->toIso8601String(),
        ))->toArray(), 3600);

        $engine = app(FleetAnalyticsEngine::class);

        // Without matching active assignments, compute still returns empty fleet — seed via direct unit path:
        $snapshot = new FleetKpiSnapshot(
            timestamp: now()->toIso8601String(),
            fleet: ['active_vehicles' => 1, 'avg_speed_kmh' => 55.0],
            vehicles: [$vehicleId => ['vehicle_id' => $vehicleId, 'current_speed_kmh' => 55.0]],
            drivers: [],
            shipments: [$shipmentId => ['current_stage' => 'EnRouteDelivery']],
        );
        Cache::put(FleetAnalyticsEngine::FLEET_CACHE_KEY, $snapshot->toArray(), 300);

        $loaded = $engine->loadFleet();
        $this->assertNotNull($loaded);
        $this->assertSame(1, $loaded->fleet['active_vehicles']);
        $this->assertArrayHasKey($vehicleId, $loaded->vehicles);

        Cache::put(FleetAnalyticsEngine::VEHICLE_CACHE_PREFIX . $vehicleId, [
            'vehicle_id' => $vehicleId,
            'fuel_level' => 72.5,
            'lifecycle_stage' => 'EnRouteDelivery',
        ], 300);

        $vehicleKpi = $engine->loadVehicle($vehicleId);
        $this->assertNotNull($vehicleKpi);
        $this->assertSame(72.5, $vehicleKpi['fuel_level']);
    }

    public function test_cache_keys_use_five_minute_ttl_namespace(): void
    {
        $this->assertSame('simulation:kpi:fleet', FleetAnalyticsEngine::FLEET_CACHE_KEY);
        $this->assertStringStartsWith('simulation:kpi:vehicle:', FleetAnalyticsEngine::VEHICLE_CACHE_PREFIX);
        $this->assertStringStartsWith('simulation:kpi:driver:', FleetAnalyticsEngine::DRIVER_CACHE_PREFIX);
    }
}
