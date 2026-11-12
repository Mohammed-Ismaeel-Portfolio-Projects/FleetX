<?php

namespace Tests\Feature\Simulation;

use App\Actions\Assignment\EnrollAssignmentInEngineAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Status;
use App\Models\Vehicle;
use App\Simulation\Journey\JourneyPlanStore;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Journey\RestStopPlanner;
use App\Simulation\Lifecycle\GeofenceProximityDetector;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Lifecycle\ShipmentLifecycleTransitionAdapter;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\TelemetrySnapshot;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 8 — end-to-end Engine-driven shipment lifecycle integration.
 *
 * Proves operational stage progression + canonical status/history integrity
 * through enrollment → pickup → departure → rest → delivery → completion.
 */
class EngineEndToEndLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const LOAD_SEC = 60;

    private const UNLOAD_SEC = 60;

    private const REST_DRIVE_M = 80_000;

    private ShipmentLifecycleEngine $engine;

    private EnrollAssignmentInEngineAction $enrollAction;

    private Status $assignmentStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
        Cache::flush();

        $journey = new JourneyRestEngine(
            store: new JourneyPlanStore(),
            planner: new RestStopPlanner(self::REST_DRIVE_M, 60, 20_000, 80),
            geofence: new GeofenceProximityDetector(),
            approachDistanceM: 2000,
            geofenceRadiusM: 150,
        );

        $this->engine = new ShipmentLifecycleEngine(
            150,
            8,
            self::LOAD_SEC,
            self::UNLOAD_SEC,
            app(ShipmentLifecycleTransitionAdapter::class),
            null,
            $journey,
        );
        $this->enrollAction = app(EnrollAssignmentInEngineAction::class);
        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    // ── Enrollment (1–4) ─────────────────────────────────────────────────────

    public function test_enrollment_is_idempotent_and_does_not_touch_status_or_history(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $beforeStatus = $assignment->shipment->status_id;
        $beforeHistory = ShipmentStatusHistory::query()->count();

        $first = $this->enrollAction->execute($assignment->id);
        $second = $this->enrollAction->execute($assignment->id);

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame(EngineEnrollmentStatus::ENROLLED, $assignment->fresh()->engine_enrollment_status);
        $this->assertSame($beforeStatus, $assignment->shipment->fresh()->status_id);
        $this->assertSame(ShipmentStatus::ASSIGNED, $assignment->shipment->fresh()->status->name);
        $this->assertSame($beforeHistory, ShipmentStatusHistory::query()->count());
    }

    // ── Pickup / departure (5–10) ────────────────────────────────────────────

    public function test_pickup_loading_stays_assigned_until_departure_creates_one_history(): void
    {
        $shipment = $this->makeAssignedShipment();
        $t0 = Carbon::parse('2026-08-13T10:00:00Z');
        $total = 50_000.0;

        $snap = $this->tick($shipment, 'moving', 40, 49_960, $total, $t0);
        $this->assertSame(ShipmentLifecycleStage::Accepted->value, $snap->currentStage);

        $snap = $this->tick($shipment, 'moving', 5, 100, $total, $t0->copy()->addSecond());
        $this->assertSame(ShipmentLifecycleStage::ArrivedPickup->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'idle', 0, 100, $total, $t0->copy()->addSeconds(2));
        $this->assertSame(ShipmentLifecycleStage::Loading->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());

        $snap = $this->tick($shipment, 'idle', 0, 100, $total, $t0->copy()->addSeconds(self::LOAD_SEC + 5));
        $this->assertSame(ShipmentLifecycleStage::Loaded->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'moving', 40, 300, $total, $t0->copy()->addSeconds(self::LOAD_SEC + 10));
        $this->assertSame(ShipmentLifecycleStage::Departed->value, $snap->currentStage);

        $snap = $this->tick($shipment, 'moving', 50, 1000, $total, $t0->copy()->addSeconds(self::LOAD_SEC + 11));
        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(1, ShipmentStatusHistory::query()->count());
        $history = ShipmentStatusHistory::first();
        $this->assertNull($history->changed_by_user_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::ASSIGNED), $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $history->to_status_id);
    }

    // ── Rest (11–16) ─────────────────────────────────────────────────────────

    public function test_rest_stages_keep_in_transit_without_history(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $total = 250_000.0;
        $t0 = Carbon::parse('2026-08-13T11:00:00Z');

        $this->seedInTransitEnRoute($shipment, $t0, 78_000);

        $approach = $this->tick($shipment, 'moving', 60, 78_500, $total, $t0);
        $this->assertSame(ShipmentLifecycleStage::ApproachingRestStop->value, $approach->currentStage);

        $resting = $this->tick($shipment, 'idle', 0, 80_000, $total, $t0->copy()->addSeconds(5));
        $this->assertSame(ShipmentLifecycleStage::Resting->value, $resting->currentStage);
        $this->assertTrue($resting->isResting);

        $still = $this->tick($shipment, 'idle', 0, 80_000, $total, $t0->copy()->addSeconds(30));
        $this->assertSame(ShipmentLifecycleStage::Resting->value, $still->currentStage);
        $this->assertFalse($still->stageChangedThisTick);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());

        $resume = $this->tick($shipment, 'idle', 0, 80_000, $total, $t0->copy()->addSeconds(90));
        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $resume->currentStage);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());
    }

    public function test_short_route_skips_rest_stages(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $total = 40_000.0;
        $t0 = Carbon::parse('2026-08-13T12:00:00Z');
        $this->seedInTransitEnRoute($shipment, $t0, 20_000);

        $snap = $this->tick($shipment, 'moving', 60, 25_000, $total, $t0);
        $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $snap->currentStage);
        $this->assertNull($snap->nextRestOffsetM);
    }

    // ── Delivery / completion (17–26) ────────────────────────────────────────

    public function test_delivery_unload_delivered_completed_history_and_terminal(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $total = 50_000.0;
        $t0 = Carbon::parse('2026-08-13T13:00:00Z');
        $this->seedInTransitEnRoute($shipment, $t0, 49_000, $total);

        $snap = $this->tick($shipment, 'moving', 10, 49_920, $total, $t0);
        $this->assertSame(ShipmentLifecycleStage::ArrivedDelivery->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'idle', 0, 80, $total, $t0->copy()->addSeconds(2));
        $this->assertSame(ShipmentLifecycleStage::Unloading->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'idle', 0, 0, $total, $t0->copy()->addSeconds(self::UNLOAD_SEC + 5), true);
        $this->assertSame(ShipmentLifecycleStage::Delivered->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status->name);
        $this->assertSame(1, ShipmentStatusHistory::query()->count());

        $deliveredHistory = ShipmentStatusHistory::first();
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $deliveredHistory->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::DELIVERED), $deliveredHistory->to_status_id);
        $this->assertNull($deliveredHistory->changed_by_user_id);

        // Delivered is observable before Completed on the following tick.
        $snap = $this->tick($shipment, 'idle', 0, 0, $total, $t0->copy()->addSeconds(self::UNLOAD_SEC + 6), true);
        $this->assertSame(ShipmentLifecycleStage::Completed->value, $snap->currentStage);
        $this->assertSame(ShipmentStatus::COMPLETED, $shipment->fresh()->status->name);
        $this->assertSame(2, ShipmentStatusHistory::query()->count());

        $completedHistory = ShipmentStatusHistory::query()->orderBy('id')->skip(1)->first();
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::DELIVERED), $completedHistory->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::COMPLETED), $completedHistory->to_status_id);

        // Completed is terminal — further ticks do not regress.
        $snap = $this->tick($shipment, 'idle', 0, 0, $total, $t0->copy()->addSeconds(self::UNLOAD_SEC + 60), true);
        $this->assertSame(ShipmentLifecycleStage::Completed->value, $snap->currentStage);
        $this->assertFalse($snap->stageChangedThisTick);
        $this->assertSame(ShipmentStatus::COMPLETED, $shipment->fresh()->status->name);
        $this->assertSame(2, ShipmentStatusHistory::query()->count());
    }

    // ── Tick safety (27–29) ──────────────────────────────────────────────────

    public function test_large_tick_does_not_skip_pickup_geofence(): void
    {
        $shipment = $this->makeAssignedShipment();
        $total = 50_000.0;
        $t0 = Carbon::parse('2026-08-13T14:00:00Z');

        $this->tick($shipment, 'moving', 40, 0, $total, $t0);

        // Single large jump past origin geofence must still register pickup arrival.
        $snap = $this->tick($shipment, 'moving', 40, 500, $total, $t0->copy()->addSecond());
        $this->assertContains($snap->currentStage, [
            ShipmentLifecycleStage::ArrivedPickup->value,
            ShipmentLifecycleStage::EnRoutePickup->value,
        ]);
        $this->assertTrue($snap->pickupGeofenceReached);
    }

    public function test_repeated_ticks_do_not_duplicate_history_or_regress_stages(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $total = 50_000.0;
        $t0 = Carbon::parse('2026-08-13T15:00:00Z');
        $this->seedInTransitEnRoute($shipment, $t0, 2000, $total);

        for ($i = 0; $i < 15; $i++) {
            $snap = $this->tick(
                $shipment,
                'moving',
                50,
                2000 + ($i * 100),
                $total,
                $t0->copy()->addSeconds($i)
            );
            $this->assertSame(ShipmentLifecycleStage::EnRouteDelivery->value, $snap->currentStage);
        }

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());
    }

    // ── Deterministic full lifecycle (integration) ─────────────────────────

    public function test_full_lifecycle_enrollment_through_completion_with_rest(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $shipment = $assignment->shipment;
        $this->enrollAction->execute($assignment->id);

        $total = 250_000.0;
        $t0 = Carbon::parse('2026-08-13T16:00:00Z');
        $stages = [];

        $record = function (ShipmentLifecycleStage $expected) use (&$stages, $shipment): callable {
            return function ($snap) use ($expected, &$stages, $shipment): void {
                $stages[] = $snap->currentStage;
                $this->assertSame($expected->value, $snap->currentStage);
            };
        };

        // Bootstrap → Accepted
        $snap = $this->tick($shipment, 'idle', 0, 0, $total, $t0);
        ($record(ShipmentLifecycleStage::Accepted))($snap);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);

        // Approach pickup (from origin, may skip EnRoutePickup when already inside geofence)
        $snap = $this->tick($shipment, 'moving', 40, 200, $total, $t0->copy()->addSecond());
        $stages[] = $snap->currentStage;
        $this->assertContains($snap->currentStage, [
            ShipmentLifecycleStage::EnRoutePickup->value,
            ShipmentLifecycleStage::ArrivedPickup->value,
        ]);

        if ($snap->currentStage === ShipmentLifecycleStage::EnRoutePickup->value) {
            $snap = $this->tick($shipment, 'idle', 0, 100, $total, $t0->copy()->addSeconds(2));
            ($record(ShipmentLifecycleStage::ArrivedPickup))($snap);
        }

        $snap = $this->tick($shipment, 'idle', 0, 100, $total, $t0->copy()->addSeconds(3));
        ($record(ShipmentLifecycleStage::Loading))($snap);

        $snap = $this->tick($shipment, 'idle', 0, 100, $total, $t0->copy()->addSeconds(self::LOAD_SEC + 5));
        ($record(ShipmentLifecycleStage::Loaded))($snap);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());

        // Departure → In Transit (canonical)
        $snap = $this->tick($shipment, 'moving', 50, 500, $total, $t0->copy()->addSeconds(self::LOAD_SEC + 10));
        ($record(ShipmentLifecycleStage::Departed))($snap);

        $snap = $this->tick($shipment, 'moving', 60, 2000, $total, $t0->copy()->addSeconds(self::LOAD_SEC + 11));
        ($record(ShipmentLifecycleStage::EnRouteDelivery))($snap);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(1, ShipmentStatusHistory::query()->count());

        // Rest cycle
        $snap = $this->tick($shipment, 'moving', 60, 78_500, $total, $t0->copy()->addSeconds(200));
        ($record(ShipmentLifecycleStage::ApproachingRestStop))($snap);

        $snap = $this->tick($shipment, 'idle', 0, 80_000, $total, $t0->copy()->addSeconds(205));
        ($record(ShipmentLifecycleStage::Resting))($snap);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'idle', 0, 80_000, $total, $t0->copy()->addSeconds(290));
        ($record(ShipmentLifecycleStage::EnRouteDelivery))($snap);

        // Delivery approach
        $snap = $this->tick($shipment, 'moving', 50, 249_900, $total, $t0->copy()->addSeconds(400));
        ($record(ShipmentLifecycleStage::ArrivedDelivery))($snap);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'idle', 0, 80, $total, $t0->copy()->addSeconds(402));
        ($record(ShipmentLifecycleStage::Unloading))($snap);

        $snap = $this->tick($shipment, 'idle', 0, 0, $total, $t0->copy()->addSeconds(402 + self::UNLOAD_SEC), true);
        ($record(ShipmentLifecycleStage::Delivered))($snap);
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status->name);

        $snap = $this->tick($shipment, 'idle', 0, 0, $total, $t0->copy()->addSeconds(403 + self::UNLOAD_SEC), true);
        ($record(ShipmentLifecycleStage::Completed))($snap);
        $this->assertSame(ShipmentStatus::COMPLETED, $shipment->fresh()->status->name);

        // Assert intermediate lifecycle was actually traversed (not just final DB state).
        $this->assertContains(ShipmentLifecycleStage::Loading->value, $stages);
        $this->assertContains(ShipmentLifecycleStage::Resting->value, $stages);
        $this->assertContains(ShipmentLifecycleStage::Unloading->value, $stages);
        $this->assertContains(ShipmentLifecycleStage::Delivered->value, $stages);

        $histories = ShipmentStatusHistory::query()->orderBy('id')->with(['fromStatus', 'toStatus'])->get();
        $this->assertCount(3, $histories);
        $this->assertSame(ShipmentStatus::ASSIGNED, $histories[0]->fromStatus->name);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $histories[0]->toStatus->name);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $histories[1]->fromStatus->name);
        $this->assertSame(ShipmentStatus::DELIVERED, $histories[1]->toStatus->name);
        $this->assertSame(ShipmentStatus::DELIVERED, $histories[2]->fromStatus->name);
        $this->assertSame(ShipmentStatus::COMPLETED, $histories[2]->toStatus->name);
        $histories->each(fn ($h) => $this->assertNull($h->changed_by_user_id));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeAssignedShipment(): Shipment
    {
        return Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
        ]);
    }

    private function makeEligibleAssignment(): Assignment
    {
        $shipment = $this->makeAssignedShipment();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'status_id' => $this->assignmentStatus->id,
            'is_active' => true,
            'engine_enrollment_status' => null,
            'engine_enrolled_at' => null,
        ]);
    }

    private function seedInTransitEnRoute(
        Shipment $shipment,
        Carbon $t0,
        float $travelled,
        float $total = 250_000.0,
    ): void {
        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id, [
            'shipment_id' => $shipment->id,
            'current_stage' => ShipmentLifecycleStage::EnRouteDelivery->value,
            'previous_stage' => ShipmentLifecycleStage::Departed->value,
            'transition_reason' => 'en route',
            'arrival_time' => null,
            'loading_started' => null,
            'loading_completed' => $t0->copy()->subHour()->toIso8601String(),
            'departure_time' => $t0->copy()->subHour()->toIso8601String(),
            'delivery_time' => null,
            'completion_time' => null,
            'timestamp' => $t0->toIso8601String(),
            'stage_changed_this_tick' => false,
            'last_travelled_m' => $travelled,
            'last_remaining_distance_m' => max(0, $total - $travelled),
            'pickup_geofence_reached' => true,
        ], 3600);
    }

    private function tick(
        Shipment $shipment,
        string $vehicleState,
        float $speed,
        float $travelled,
        float $total,
        Carbon $at,
        bool $completed = false,
    ) {
        $remaining = max(0, $total - $travelled);

        return $this->engine->evaluate(
            $shipment->id,
            $vehicleState,
            $this->telemetry($speed),
            new RouteProgressState(
                'r', $shipment->id, 'v', 1, 1, $travelled, $remaining,
                $total > 0 ? ($travelled / $total) * 100 : 0,
                48.1, 11.5, 90, $completed, $at->toIso8601String(), $total
            ),
            $at->toIso8601String()
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
