<?php

namespace Tests\Feature\Simulation;

use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Simulation\Journey\JourneyPlanStore;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Journey\RestStopPlanner;
use App\Simulation\Lifecycle\GeofenceProximityDetector;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\TelemetrySnapshot;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 5 — rest keeps canonical In Transit; history exactly-once.
 */
class ShipmentLifecycleRestCanonicalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentStatusesSeeder::class);
        Cache::flush();
    }

    public function test_rest_stages_do_not_mutate_in_transit_or_history(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);

        $journey = new JourneyRestEngine(
            store: new JourneyPlanStore(),
            planner: new RestStopPlanner(80_000, 60, 20_000, 80),
            geofence: new GeofenceProximityDetector(),
            approachDistanceM: 2000,
            geofenceRadiusM: 150,
        );
        $journey->ensurePlan($shipment->id, 250_000);

        $engine = new ShipmentLifecycleEngine(
            150,
            8,
            60,
            60,
            app(\App\Simulation\Lifecycle\ShipmentLifecycleTransitionAdapter::class),
            null,
            $journey,
        );

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id, [
            'shipment_id' => $shipment->id,
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'en route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => now()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => 78_000.0,
            'pickup_geofence_reached' => true,
        ], 3600);

        $engine->evaluate(
            $shipment->id,
            'moving',
            $this->telemetry(60),
            $this->progress($shipment->id, 78_500, 171_500, 250_000),
            now()->toIso8601String()
        );
        $engine->evaluate(
            $shipment->id,
            'idle',
            $this->telemetry(0),
            $this->progress($shipment->id, 80_000, 170_000, 250_000),
            now()->addSeconds(5)->toIso8601String()
        );
        $engine->evaluate(
            $shipment->id,
            'idle',
            $this->telemetry(0),
            $this->progress($shipment->id, 80_000, 170_000, 250_000),
            now()->addSeconds(20)->toIso8601String()
        );
        $engine->evaluate(
            $shipment->id,
            'idle',
            $this->telemetry(0),
            $this->progress($shipment->id, 80_000, 170_000, 250_000),
            now()->addSeconds(90)->toIso8601String()
        );

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());
    }

    private function progress(string $id, float $t, float $r, float $total): RouteProgressState
    {
        return new RouteProgressState(
            'r', $id, 'v', 1, 1, $t, $r, ($t / $total) * 100,
            48.1, 11.5, 90, false, now()->toIso8601String(), $total
        );
    }

    private function telemetry(float $speed): TelemetrySnapshot
    {
        return new TelemetrySnapshot(
            vehicleId: 'v',
            shipmentId: 's',
            timestamp: now()->toIso8601String(),
            latitude: 48.1,
            longitude: 11.5,
            heading: 90,
            currentSpeedKmh: $speed,
            targetSpeedKmh: $speed,
            acceleration: 0,
            vehicleState: $speed > 1 ? 'moving' : 'idle',
            distanceTravelledMeters: 1000,
            remainingDistanceMeters: 100,
            completionPercentage: 50,
            currentSegment: 1,
            polylineIndex: 10,
            drivingTimeSeconds: 60,
            idleTimeSeconds: 0,
            engineRuntimeSeconds: 60,
            averageSpeedKmh: $speed,
            odometerMeters: 1000,
            gpsAccuracy: 5,
            signalQuality: 'good',
        );
    }
}
