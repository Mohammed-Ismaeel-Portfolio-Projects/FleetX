<?php

namespace Tests\Feature\Shipment;

use App\Actions\Shipment\BuildShipmentEngineOpsReadModelAction;
use App\Models\Assignment;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use App\Simulation\Journey\JourneyPlan;
use App\Simulation\Journey\JourneyPlanStore;
use App\Simulation\Journey\RestStopStatus;
use App\Simulation\Journey\RestStopWaypoint;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 6 — Dispatcher Engine ops visibility (read-only API).
 */
class ShipmentEngineOpsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
        $this->dispatcherRole = \App\Models\Role::where('name', 'dispatcher')->firstOrFail();
        Cache::flush();
    }

    public function test_show_includes_engine_ops_and_enrollment_without_mutating_status(): void
    {
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'is_active' => true,
            'engine_enrollment_status' => EngineEnrollmentStatus::ACTIVE,
            'engine_enrolled_at' => now(),
        ]);

        Cache::put(ShipmentLifecycleEngine::CACHE_PREFIX.$shipment->id, [
            'shipment_id' => $shipment->id,
            'current_stage' => 'Resting',
            'previous_stage' => 'ApproachingRestStop',
            'transition_reason' => 'Entered rest stop',
            'timestamp' => now()->toIso8601String(),
            'is_resting' => true,
            'pickup_geofence_reached' => true,
            'delivery_geofence_reached' => false,
            'driving_since_rest_m' => 80000,
            'next_rest_offset_m' => 80000,
        ], 3600);

        Cache::forever('simulation:route_progress:'.$shipment->id, [
            'route_id' => 'r1',
            'shipment_id' => $shipment->id,
            'vehicle_id' => (string) $assignment->vehicle_id,
            'polyline_index' => 10,
            'segment_index' => 2,
            'distance_travelled_m' => 80000,
            'remaining_distance_m' => 120000,
            'completion_percentage' => 40,
            'current_latitude' => 48.1,
            'current_longitude' => 11.5,
            'heading' => 90,
            'completed' => false,
            'last_tick' => now()->toIso8601String(),
            'total_distance_m' => 200000,
        ]);

        $plan = new JourneyPlan(
            shipmentId: $shipment->id,
            totalDistanceM: 200000,
            maxDrivingDistanceM: 80000,
            restDurationSeconds: 900,
            estimatedTravelSeconds: 9000,
            restStops: [
                new RestStopWaypoint(
                    id: $shipment->id.'-rest-1',
                    sequence: 1,
                    routeOffsetM: 80000,
                    durationSeconds: 900,
                    reason: 'max_driving_distance',
                    status: RestStopStatus::Active,
                    restStartedAt: now()->toIso8601String(),
                ),
            ],
            isResting: true,
            activeRestStopId: $shipment->id.'-rest-1',
            restStartedAt: now()->toIso8601String(),
        );
        (new JourneyPlanStore())->put($plan);

        $beforeStatus = $shipment->status_id;

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipments/'.$shipment->id);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $data['status']['name'] ?? null);
        $this->assertSame(EngineEnrollmentStatus::ACTIVE, $data['active_assignment']['engine_enrollment_status'] ?? null);
        $this->assertArrayHasKey('engine_ops', $data);
        $this->assertTrue($data['engine_ops']['available']);
        $this->assertTrue($data['engine_ops']['simulation_eligible']);
        $this->assertSame('Resting', $data['engine_ops']['operational_stage']);
        $this->assertTrue($data['engine_ops']['is_resting']);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $data['engine_ops']['canonical_status']);
        $this->assertNotNull($data['engine_ops']['route_progress']);
        $this->assertSame(40.0, (float) $data['engine_ops']['route_progress']['completion_percentage']);
        $this->assertTrue($data['engine_ops']['rest']['active']);
        $this->assertFalse($data['engine_ops']['enrollment']['can_enroll']);
        $this->assertTrue($data['engine_ops']['enrollment']['already_enrolled']);

        $shipment->refresh();
        $this->assertSame($beforeStatus, $shipment->status_id);
        $this->assertSame(0, ShipmentStatusHistory::query()->count());
    }

    public function test_non_enrolled_assignment_is_not_simulation_eligible(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
        ]);
        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'is_active' => true,
            'engine_enrollment_status' => null,
        ]);

        $ops = app(BuildShipmentEngineOpsReadModelAction::class)->execute(
            $shipment->fresh(['status', 'activeAssignment'])
        );

        $this->assertFalse($ops['simulation_eligible']);
        $this->assertNull($ops['enrollment']['status']);
        $this->assertStringContainsString('not enrolled', strtolower($ops['enrollment']['label']));
        $this->assertFalse($ops['available']);
        $this->assertNull($ops['operational_stage']);
        $this->assertTrue($ops['enrollment']['can_enroll']);
        $this->assertFalse($ops['enrollment']['already_enrolled']);
    }

    public function test_waiting_assignment_label_marks_engine_unavailable(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::WAITING_ASSIGNMENT),
        ]);

        $ops = app(BuildShipmentEngineOpsReadModelAction::class)->execute(
            $shipment->fresh(['status', 'activeAssignment'])
        );

        $this->assertStringContainsString('Waiting for Assignment', $ops['enrollment']['label']);
        $this->assertFalse($ops['simulation_eligible']);
    }
}
