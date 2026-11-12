<?php

namespace Tests\Feature\Assignment;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
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
 * C2.9-C1.3 — Assignment creation advances Waiting Assignment → Assigned.
 */
class AssignmentShipmentStatusIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

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
        $this->clientRole = Role::where('name', 'client')->firstOrFail();

        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    public function test_waiting_assignment_shipment_becomes_assigned_via_api(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment))
            ->assertCreated();

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->status->name);
        $this->assertSame(1, Assignment::query()->where('shipment_id', $shipment->id)->count());
    }

    public function test_admin_cannot_create_assignment_and_advance_shipment_status(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment, ['assignment_code' => 'C13-ADM-001']))
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
    }

    public function test_non_waiting_assignment_shipment_status_is_unchanged(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment, ['assignment_code' => 'C13-INT-001']))
            ->assertCreated();

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
    }

    public function test_failed_assignment_creation_leaves_shipment_waiting_assignment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);
        $otherShipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        Assignment::factory()->create([
            'shipment_id' => $otherShipment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status_id' => $this->assignmentStatus->id,
            'is_active' => true,
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment, [
                'assignment_code' => 'C13-DUP-DRV',
                'driver_id' => $this->driver->id,
            ]))
            ->assertStatus(422);

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
        $this->assertSame(0, Assignment::query()->where('shipment_id', $shipment->id)->count());
    }

    public function test_shipment_status_update_failure_rolls_back_assignment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        Shipment::updating(function (): void {
            throw new \RuntimeException('shipment status blocked');
        });

        try {
            $this->actingAs($dispatcher, 'api')
                ->postJson('/api/v1/assignments', $this->validPayload($shipment, ['assignment_code' => 'C13-RBK-001']))
                ->assertStatus(500);
        } finally {
            Shipment::flushEventListeners();
        }

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
        $this->assertSame(0, Assignment::query()->where('shipment_id', $shipment->id)->count());
    }

    public function test_duplicate_active_assignment_prevention_unchanged(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment, ['assignment_code' => 'C13-FIRST']))
            ->assertCreated();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment, ['assignment_code' => 'C13-SECOND']))
            ->assertStatus(422);

        $this->assertSame(1, Assignment::query()->where('shipment_id', $shipment->id)->count());
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_inactive_assignment_does_not_advance_shipment_status(): void
    {
        $action = app(CreateAssignmentAction::class);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);
        $assignedBy = User::factory()->create(['role_id' => $this->adminRole->id]);

        $result = $action->execute(array_merge($this->validPayload($shipment, [
            'assignment_code' => 'C13-INACTIVE',
            'is_active' => false,
        ]), [
            'assigned_by' => $assignedBy->id,
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
    }

    public function test_client_remains_denied_for_assignment_api(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload($shipment))
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeShipmentInStatus(string $statusName): Shipment
    {
        return Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId($statusName),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Shipment $shipment, array $overrides = []): array
    {
        return array_merge([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'C13-'.fake()->unique()->numerify('#####'),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => null,
            'status_id' => $this->assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'ended_at' => null,
            'is_active' => true,
            'notes' => 'C1.3 integration test',
        ], $overrides);
    }
}
