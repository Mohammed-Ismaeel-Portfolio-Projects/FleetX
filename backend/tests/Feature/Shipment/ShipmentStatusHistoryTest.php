<?php

namespace Tests\Feature\Shipment;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Exceptions\InvalidShipmentTransitionException;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.9-C1.8 — Shipment status transition history.
 */
class ShipmentStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private TransitionShipmentStatusAction $transitionAction;

    private Driver $driver;

    private Vehicle $vehicle;

    private Status $assignmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->transitionAction = app(TransitionShipmentStatusAction::class);
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    public function test_waiting_assignment_to_assigned_creates_one_history_row(): void
    {
        $actor = User::factory()->create(['role_id' => $this->adminRole->id]);
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);
        $fromId = $shipment->status_id;

        $this->transitionAction->execute($shipment, ShipmentStatus::ASSIGNED, $actor->id);

        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame($shipment->id, $history->shipment_id);
        $this->assertSame($fromId, $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::ASSIGNED), $history->to_status_id);
        $this->assertSame($actor->id, $history->changed_by_user_id);
    }

    public function test_assigned_to_in_transit_creates_one_history_row(): void
    {
        $actor = User::factory()->create(['role_id' => $this->adminRole->id]);
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->transitionAction->execute($shipment, ShipmentStatus::IN_TRANSIT, $actor->id);

        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::ASSIGNED), $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $history->to_status_id);
        $this->assertSame($actor->id, $history->changed_by_user_id);
    }

    public function test_in_transit_to_delivered_creates_one_history_row(): void
    {
        $actor = User::factory()->create(['role_id' => $this->adminRole->id]);
        $shipment = $this->shipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->transitionAction->execute($shipment, ShipmentStatus::DELIVERED, $actor->id);

        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::DELIVERED), $history->to_status_id);
    }

    public function test_rejected_transition_creates_no_history(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::DRAFT);

        try {
            $this->transitionAction->execute($shipment, ShipmentStatus::ASSIGNED, 1);
            $this->fail('Expected InvalidShipmentTransitionException');
        } catch (InvalidShipmentTransitionException) {
            // expected
        }

        $this->assertHistoryCount(0);
        $this->assertSame(ShipmentStatus::DRAFT, $shipment->fresh()->status->name);
    }

    public function test_patch_status_creates_one_history_row(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->shipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertOk();

        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame($dispatcher->id, $history->changed_by_user_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT), $history->to_status_id);
    }

    public function test_put_with_status_id_creates_one_history_row(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->shipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::DELIVERED),
            ])
            ->assertOk();

        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame($dispatcher->id, $history->changed_by_user_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::DELIVERED), $history->to_status_id);
    }

    public function test_assignment_triggered_transition_creates_one_history_row(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', [
                'shipment_id' => $shipment->id,
                'assignment_code' => 'C18-HIST-001',
                'driver_id' => $this->driver->id,
                'vehicle_id' => $this->vehicle->id,
                'trailer_id' => null,
                'assigned_by' => User::factory()->create(['role_id' => $this->adminRole->id])->id,
                'status_id' => $this->assignmentStatus->id,
                'assigned_at' => now()->toDateTimeString(),
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertHistoryCount(1);
        $history = ShipmentStatusHistory::first();
        $this->assertSame($shipment->id, $history->shipment_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::WAITING_ASSIGNMENT), $history->from_status_id);
        $this->assertSame(ShipmentStatus::statusId(ShipmentStatus::ASSIGNED), $history->to_status_id);
        $this->assertSame($dispatcher->id, $history->changed_by_user_id);
    }

    public function test_rejected_api_transition_creates_no_history(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->shipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertStatus(422);

        $this->assertHistoryCount(0);
    }

    public function test_transition_is_atomic_when_history_insert_fails(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);
        $originalStatusId = $shipment->status_id;

        ShipmentStatusHistory::creating(function (): void {
            throw new \RuntimeException('history blocked');
        });

        try {
            $this->transitionAction->execute($shipment, ShipmentStatus::ASSIGNED, 1);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('history blocked', $e->getMessage());
        } finally {
            ShipmentStatusHistory::flushEventListeners();
        }

        $this->assertHistoryCount(0);
        $this->assertSame($originalStatusId, $shipment->fresh()->status_id);
    }

    public function test_assignment_rollback_leaves_no_history_when_transition_fails(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        Shipment::updating(function (): void {
            throw new \RuntimeException('shipment status blocked');
        });

        try {
            $this->actingAs($dispatcher, 'api')
                ->postJson('/api/v1/assignments', [
                    'shipment_id' => $shipment->id,
                    'assignment_code' => 'C18-RBK-HIST',
                    'driver_id' => $this->driver->id,
                    'vehicle_id' => $this->vehicle->id,
                    'trailer_id' => null,
                    'assigned_by' => User::factory()->create(['role_id' => $this->adminRole->id])->id,
                    'status_id' => $this->assignmentStatus->id,
                    'assigned_at' => now()->toDateTimeString(),
                    'is_active' => true,
                ])
                ->assertStatus(500);
        } finally {
            Shipment::flushEventListeners();
        }

        $this->assertHistoryCount(0);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
        $this->assertSame(0, Assignment::query()->where('shipment_id', $shipment->id)->count());
    }

    public function test_sequential_transitions_create_distinct_history_rows(): void
    {
        $actor = User::factory()->create(['role_id' => $this->adminRole->id]);
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->transitionAction->execute($shipment, ShipmentStatus::ASSIGNED, $actor->id);
        $shipment->refresh();
        $this->transitionAction->execute($shipment, ShipmentStatus::IN_TRANSIT, $actor->id);
        $shipment->refresh();
        $this->transitionAction->execute($shipment, ShipmentStatus::DELIVERED, $actor->id);

        $this->assertHistoryCount(3);
        $this->assertSame(
            [
                ShipmentStatus::WAITING_ASSIGNMENT,
                ShipmentStatus::ASSIGNED,
                ShipmentStatus::IN_TRANSIT,
            ],
            ShipmentStatusHistory::query()
                ->with(['fromStatus', 'toStatus'])
                ->orderBy('id')
                ->get()
                ->map(fn (ShipmentStatusHistory $row) => $row->fromStatus->name)
                ->all()
        );
    }

    public function test_shipment_has_status_history_relationship(): void
    {
        $actor = User::factory()->create(['role_id' => $this->adminRole->id]);
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->transitionAction->execute($shipment, ShipmentStatus::ASSIGNED, $actor->id);

        $shipment->load('statusHistory.toStatus', 'latestStatusHistory');

        $this->assertNotNull($shipment->latestStatusHistory);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->latestStatusHistory->toStatus->name);
    }

    public function test_direct_create_assignment_action_creates_one_history_row(): void
    {
        $shipment = $this->shipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);
        $assignedBy = User::factory()->create(['role_id' => $this->adminRole->id]);
        $action = app(CreateAssignmentAction::class);

        $result = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'C18-ACT-HIST',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => null,
            'assigned_by' => $assignedBy->id,
            'status_id' => $this->assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'is_active' => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertHistoryCount(1);
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
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
}
