<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.9-C1.2 Assignment authorization gates — dispatcher/admin allowed, client denied.
 */
class AssignmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Shipment $shipment;

    private Driver $driver;

    private Vehicle $vehicle;

    private Status $assignmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->shipment = Shipment::factory()->create();
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    public function test_dispatcher_assignment_gates_and_policy_allow_ops(): void
    {
        $dispatcher = $this->makeDispatcher();
        $assignment = $this->makeAssignment();

        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.view'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.create'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.update'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.delete'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.change-status'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.restore'));

        $this->assertTrue($dispatcher->can('viewAny', Assignment::class));
        $this->assertTrue($dispatcher->can('view', $assignment));
        $this->assertTrue($dispatcher->can('create', Assignment::class));
        $this->assertTrue($dispatcher->can('update', $assignment));
        $this->assertTrue($dispatcher->can('delete', $assignment));
        $this->assertTrue($dispatcher->can('changeStatus', $assignment));
    }

    public function test_admin_denied_operational_assignment_access(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $assignment = $this->makeAssignment();

        $this->assertFalse(Gate::forUser($admin)->allows('assignment.view'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.create'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.update'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.delete'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.change-status'));
        $this->assertFalse($admin->can('create', Assignment::class));
        $this->assertFalse($admin->can('delete', $assignment));
    }

    public function test_admin_cannot_use_assignment_crud_api(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'assignment_code' => 'AUTH-ADM-001',
            ]))
            ->assertForbidden();

        $assignment = $this->makeAssignment();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/assignments/'.$assignment->id)
            ->assertForbidden();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/v1/assignments/'.$assignment->id)
            ->assertForbidden();
    }

    public function test_client_and_unlinked_client_denied_via_gates_and_api(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $unlinked = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $assignment = $this->makeAssignment();

        $this->assertFalse(Gate::forUser($client)->allows('assignment.view'));
        $this->assertFalse(Gate::forUser($client)->allows('assignment.create'));
        $this->assertFalse($client->can('create', Assignment::class));

        $this->actingAs($client, 'api')->getJson('/api/v1/assignments')->assertForbidden();
        $this->actingAs($client, 'api')->postJson('/api/v1/assignments', $this->validPayload())->assertForbidden();
        $this->actingAs($client, 'api')->getJson('/api/v1/assignments/'.$assignment->id)->assertForbidden();

        $this->actingAs($unlinked, 'api')->getJson('/api/v1/assignments')->assertForbidden();
        $this->actingAs($unlinked, 'api')->postJson('/api/v1/assignments', $this->validPayload())->assertForbidden();
    }

    public function test_unauthenticated_assignment_api_returns_401(): void
    {
        $assignment = $this->makeAssignment();

        $this->getJson('/api/v1/assignments')->assertUnauthorized();
        $this->postJson('/api/v1/assignments', $this->validPayload())->assertUnauthorized();
        $this->getJson('/api/v1/assignments/'.$assignment->id)->assertUnauthorized();
    }

    public function test_dispatcher_can_use_assignment_crud_api(): void
    {
        $dispatcher = $this->makeDispatcher();

        $create = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'assignment_code' => 'AUTH-DSP-001',
            ]));

        $create->assertCreated();
        $assignmentId = $create->json('data.id');

        $this->assertDatabaseHas('assignments', [
            'id' => $assignmentId,
            'assigned_by' => $dispatcher->id,
            'driver_id' => $this->driver->id,
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/assignments')
            ->assertOk();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/assignments/'.$assignmentId)
            ->assertOk();

        $this->actingAs($dispatcher, 'api')
            ->putJson('/api/v1/assignments/'.$assignmentId, [
                'notes' => 'Updated by dispatcher',
            ])
            ->assertOk();

        $this->actingAs($dispatcher, 'api')
            ->deleteJson('/api/v1/assignments/'.$assignmentId)
            ->assertOk();
    }

    public function test_shipment_order_convert_gate_unchanged_for_dispatcher(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->assertTrue(Gate::forUser($dispatcher)->allows('shipmentOrder.convert'));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('client.shipmentRequest.create'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('assignment.create'));
    }

    public function test_client_cannot_convert_shipment_order(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = ShipmentOrder::factory()->create(['customer_company_id' => $company->id]);

        $this->assertFalse(Gate::forUser($client)->allows('assignment.create'));
        $this->assertFalse(Gate::forUser($client)->allows('shipmentOrder.convert'));

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertForbidden();
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeAssignment(): Assignment
    {
        return Assignment::factory()->create([
            'shipment_id' => $this->shipment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'status_id' => $this->assignmentStatus->id,
            'is_active' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'shipment_id' => $this->shipment->id,
            'assignment_code' => 'AUTH-'.fake()->unique()->numerify('#####'),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => null,
            'status_id' => $this->assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'ended_at' => null,
            'is_active' => true,
            'notes' => 'Authorization test assignment',
        ], $overrides);
    }
}
