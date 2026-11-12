<?php

namespace Tests\Feature\Simulation;

use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
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
 * C2.9-C2.8 Step 4 — geofence stages → canonical history (exactly-once).
 */
class ShipmentLifecycleGeofenceCanonicalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentStatusesSeeder::class);
        Cache::flush();
    }

    public function test_pickup_through_departure_creates_one_in_transit_history(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
        ]);
        $engine = app(ShipmentLifecycleEngine::class);
        $t0 = now();

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id, [
            'shipment_id' => $shipment->id,
            'current_stage' => ShipmentLifecycleStage::Accepted->value,
            'previous_stage' => ShipmentLifecycleStage::Assigned->value,
            'transition_reason' => 'accepted',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => null,
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => $t0->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => 0.0,
        ], 3600);

        // At pickup
        $engine->evaluate($shipment->id, 'moving', $this->telemetry(5), $this->progress($shipment->id, 40, 49960, 50000), $t0->toIso8601String());
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);

        // Loading
        $engine->evaluate($shipment->id, 'idle', $this->telemetry(0), $this->progress($shipment->id, 40, 49960, 50000), $t0->addSeconds(1)->toIso8601String());
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);

        // Loaded (timer)
        $engine->evaluate($shipment->id, 'idle', $this->telemetry(0), $this->progress($shipment->id, 40, 49960, 50000), $t0->addSeconds(200)->toIso8601String());
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());

        // Depart → In Transit once
        $engine->evaluate($shipment->id, 'moving', $this->telemetry(40), $this->progress($shipment->id, 300, 49700, 50000), $t0->addSeconds(210)->toIso8601String());
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(1, ShipmentStatusHistory::query()->count());
        $this->assertNull(ShipmentStatusHistory::first()->changed_by_user_id);

        // En route + many ticks — no duplicate history
        for ($i = 0; $i < 10; $i++) {
            $engine->evaluate(
                $shipment->id,
                'moving',
                $this->telemetry(50),
                $this->progress($shipment->id, 1000 + ($i * 500), 49000 - ($i * 500), 50000),
                $t0->addSeconds(220 + $i)->toIso8601String()
            );
        }
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(1, ShipmentStatusHistory::query()->count());
    }

    public function test_delivery_completion_creates_one_delivered_history(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $engine = app(ShipmentLifecycleEngine::class);
        $t0 = now();

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id, [
            'shipment_id' => $shipment->id,
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'en route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => $t0->subHour()->toIso8601String(),
            'departure_time' => $t0->subHour()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => $t0->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => 49000.0,
            'last_remaining_distance_m' => 1000.0,
            'pickup_geofence_reached' => true,
        ], 3600);

        $engine->evaluate($shipment->id, 'moving', $this->telemetry(10), $this->progress($shipment->id, 49920, 80, 50000), $t0->toIso8601String());
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);

        $engine->evaluate($shipment->id, 'idle', $this->telemetry(0), $this->progress($shipment->id, 49920, 80, 50000), $t0->addSeconds(2)->toIso8601String());
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());

        // Unload timer complete → Delivered
        $engine->evaluate(
            $shipment->id,
            'idle',
            $this->telemetry(0),
            $this->progress($shipment->id, 50000, 0, 50000, true),
            $t0->addSeconds(200)->toIso8601String()
        );
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status->name);
        $this->assertSame(1, ShipmentStatusHistory::query()->count());
        $history = ShipmentStatusHistory::first();
        $this->assertNull($history->changed_by_user_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::DELIVERED), $history->to_status_id);

        // Repeat delivery-area ticks must not duplicate Delivered history
        // (next tick may advance to Completed — that is a second allowed history row)
        $engine->evaluate(
            $shipment->id,
            'idle',
            $this->telemetry(0),
            $this->progress($shipment->id, 50000, 0, 50000, true),
            $t0->addSeconds(210)->toIso8601String()
        );
        $this->assertSame(ShipmentStatus::COMPLETED, $shipment->fresh()->status->name);
        $this->assertSame(2, ShipmentStatusHistory::query()->count());
        $this->assertSame(
            1,
            ShipmentStatusHistory::query()
                ->where('to_status_id', ShipmentStatus::statusId(ShipmentStatus::DELIVERED))
                ->count()
        );
    }

    public function test_already_delivered_shipment_does_not_duplicate_on_arrived_delivery(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::DELIVERED),
        ]);
        $engine = app(ShipmentLifecycleEngine::class);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id, [
            'shipment_id' => $shipment->id,
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => null,
            'transition_reason' => null,
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => null,
            'departure_time' => null,
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => now()->toIso8601String(),
            'stage_changed_this_tick' => false,
            'pickup_geofence_reached' => true,
            'last_remaining_distance_m' => 500.0,
        ], 3600);

        $engine->evaluate(
            $shipment->id,
            'idle',
            $this->telemetry(0),
            $this->progress($shipment->id, 49950, 50, 50000),
            now()->toIso8601String()
        );

        // ArrivedDelivery is ops-only; Delivered→Completed may fire on later Delivered stage only.
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());
    }

    private function progress(
        string $shipmentId,
        float $travelled,
        float $remaining,
        float $total,
        bool $completed = false,
    ): RouteProgressState {
        return new RouteProgressState(
            'r', $shipmentId, 'v', 1, 1, $travelled, $remaining,
            $total > 0 ? ($travelled / $total) * 100 : 0,
            48.1, 11.5, 90, $completed, now()->toIso8601String(), $total
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
