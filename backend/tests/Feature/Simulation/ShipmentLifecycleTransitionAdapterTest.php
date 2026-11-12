<?php

namespace Tests\Feature\Simulation;

use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Lifecycle\ShipmentLifecycleTransitionAdapter;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 2 — Engine → canonical lifecycle adapter.
 */
class ShipmentLifecycleTransitionAdapterTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentLifecycleTransitionAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentStatusesSeeder::class);
        $this->adapter = app(ShipmentLifecycleTransitionAdapter::class);
    }

    public function test_assigned_to_in_transit_via_departure_stage(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);
        $fromId = $shipment->status_id;

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Departed);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->status->name);
        $this->assertHistoryCount(1);

        $history = ShipmentStatusHistory::first();
        $this->assertSame($shipment->id, $history->shipment_id);
        $this->assertSame($fromId, $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $history->to_status_id);
        $this->assertNull($history->changed_by_user_id);
        $this->assertNotNull($shipment->actual_pickup_date);
    }

    public function test_loaded_stage_does_not_request_in_transit(): void
    {
        // C2.9-C2.8 Step 4 — Loaded is ops-only; In Transit begins at Departed.
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Loaded);

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertHistoryCount(0);
    }

    public function test_in_transit_to_delivered(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::IN_TRANSIT);
        $fromId = $shipment->status_id;

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Delivered);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->status->name);
        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame($fromId, $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::DELIVERED), $history->to_status_id);
        $this->assertNull($history->changed_by_user_id);
        $this->assertNotNull($shipment->actual_delivery_date);
    }

    public function test_delivered_to_completed(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::DELIVERED);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Completed);

        $this->assertSame(ShipmentStatus::COMPLETED, $shipment->fresh()->status->name);
        $this->assertHistoryCount(1);
        $this->assertNull(ShipmentStatusHistory::first()->changed_by_user_id);
    }

    public function test_repeated_in_transit_request_is_idempotent(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Departed);
        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::EnRouteDelivery);
        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Departed);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertHistoryCount(1);
    }

    public function test_repeated_delivered_request_is_idempotent(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Delivered);
        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Delivered);

        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status->name);
        $this->assertHistoryCount(1);
    }

    public function test_repeated_completed_request_is_idempotent(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::DELIVERED);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Completed);
        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Completed);

        $this->assertSame(ShipmentStatus::COMPLETED, $shipment->fresh()->status->name);
        $this->assertHistoryCount(1);
    }

    public function test_waiting_assignment_to_delivered_is_rejected_without_history(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Delivered);

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
        $this->assertHistoryCount(0);
    }

    public function test_assigned_to_delivered_is_rejected_without_history(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Delivered);

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertHistoryCount(0);
    }

    public function test_in_transit_to_completed_is_rejected_without_history(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Completed);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertHistoryCount(0);
    }

    public function test_operational_only_stages_do_not_mutate_canonical_status(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        foreach ([
            ShipmentLifecycleStage::Accepted,
            ShipmentLifecycleStage::EnRoutePickup,
            ShipmentLifecycleStage::ArrivedPickup,
            ShipmentLifecycleStage::Loading,
            ShipmentLifecycleStage::Loaded,
            ShipmentLifecycleStage::ApproachingRestStop,
            ShipmentLifecycleStage::Resting,
            ShipmentLifecycleStage::ArrivedDelivery,
            ShipmentLifecycleStage::Unloading,
        ] as $stage) {
            $this->adapter->applyOperationalStage($shipment->id, $stage);
        }

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertHistoryCount(0);
    }

    public function test_engine_evaluate_uses_adapter_not_direct_status_write(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);
        $engine = app(\App\Simulation\Lifecycle\ShipmentLifecycleEngine::class);

        // Loaded + moving → Departed triggers Assigned → In Transit via adapter.
        \Illuminate\Support\Facades\Cache::put(
            \App\Simulation\Lifecycle\ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id,
            [
                'shipment_id' => $shipment->id,
                'current_stage' => ShipmentLifecycleStage::Loaded->value,
                'previous_stage' => ShipmentLifecycleStage::Loading->value,
                'transition_reason' => 'Loading completed',
                'arrival_time' => now()->subMinutes(10)->toIso8601String(),
                'loading_started' => now()->subMinutes(10)->toIso8601String(),
                'loading_completed' => now()->subMinutes(5)->toIso8601String(),
                'departure_time' => null,
                'delivery_time' => null,
                'completion_time' => null,
                'timestamp' => now()->subMinutes(5)->toIso8601String(),
                'stage_changed_this_tick' => false,
                'pickup_geofence_reached' => true,
            ],
            3600
        );

        $progress = new \App\Simulation\Progress\RouteProgressState(
            'r', $shipment->id, 'v', 0, 0, 200, 50000, 0.4,
            48.0, 11.0, 0, false, now()->toIso8601String(), 50200
        );

        $snap = $engine->evaluate(
            $shipment->id,
            'moving',
            $this->telemetry(40),
            $progress,
            now()->toIso8601String()
        );

        $this->assertSame(ShipmentLifecycleStage::Departed->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertHistoryCount(1);
        $this->assertNull(\App\Models\ShipmentStatusHistory::first()->changed_by_user_id);
    }

    public function test_legacy_ready_for_pickup_mapping_is_not_written(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::EnRoutePickup);

        $name = $shipment->fresh()->status->name;
        $this->assertSame(ShipmentStatus::ASSIGNED, $name);
        $this->assertNotSame('Ready For Pickup', $name);
        $this->assertHistoryCount(0);
    }

    private function shipmentInStatus(string $statusName): Shipment
    {
        return Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId($statusName),
        ]);
    }

    private function assertHistoryCount(int $expected): void
    {
        $this->assertSame($expected, ShipmentStatusHistory::query()->count());
    }

    private function telemetry(float $speed): \App\Simulation\Telemetry\TelemetrySnapshot
    {
        return new \App\Simulation\Telemetry\TelemetrySnapshot(
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
