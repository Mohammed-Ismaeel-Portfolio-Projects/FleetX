<?php

namespace Tests\Feature\Authorization;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\Shipment;
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
 * ADMIN-07 — Backend RBAC hardening: Admin ≠ Dispatcher.
 */
class AdminDispatcherSeparationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    private Role $driverRole;

    private Shipment $shipment;

    private Driver $driver;

    private Vehicle $vehicle;

    private Status $assignmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->shipment = Shipment::factory()->create();
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    public function test_admin_cannot_create_assignment(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/assignments', $this->validAssignmentPayload())
            ->assertForbidden();
    }

    public function test_admin_cannot_update_assignment(): void
    {
        $admin = $this->makeAdmin();
        $assignment = $this->makeAssignment();

        $this->actingAs($admin, 'api')
            ->putJson('/api/v1/assignments/'.$assignment->id, ['notes' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_assignment(): void
    {
        $admin = $this->makeAdmin();
        $assignment = $this->makeAssignment();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/v1/assignments/'.$assignment->id)
            ->assertForbidden();
    }

    public function test_admin_cannot_change_assignment_status(): void
    {
        $admin = $this->makeAdmin();
        $assignment = $this->makeAssignment();

        $this->actingAs($admin, 'api')
            ->patchJson('/api/v1/assignments/'.$assignment->id.'/status', [
                'status_id' => $this->assignmentStatus->id,
            ])
            ->assertForbidden();
    }

    public function test_dispatcher_can_create_assignment(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validAssignmentPayload([
                'assignment_code' => 'ADM07-CREATE',
            ]))
            ->assertCreated();
    }

    public function test_dispatcher_can_update_assignment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $assignment = $this->makeAssignment();

        $this->actingAs($dispatcher, 'api')
            ->putJson('/api/v1/assignments/'.$assignment->id, ['notes' => 'Updated'])
            ->assertOk();
    }

    public function test_dispatcher_can_delete_assignment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $assignment = $this->makeAssignment();

        $this->actingAs($dispatcher, 'api')
            ->deleteJson('/api/v1/assignments/'.$assignment->id)
            ->assertOk();
    }

    public function test_admin_cannot_create_shipment(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create();
        $status = Status::firstOrCreate(['name' => 'Draft', 'type' => 'shipment']);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipments', [
                'customer_company_id' => $company->id,
                'priority' => 'normal',
                'status_id' => $status->id,
                'pickup_city' => 'Miami',
                'pickup_country' => 'US',
                'delivery_city' => 'Orlando',
                'delivery_country' => 'US',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_update_shipment(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->putJson('/api/v1/shipments/'.$this->shipment->id, [
                'priority' => 'high',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_change_shipment_status(): void
    {
        $admin = $this->makeAdmin();
        $status = Status::firstOrCreate(['name' => 'Assigned', 'type' => 'shipment']);

        $this->actingAs($admin, 'api')
            ->patchJson('/api/v1/shipments/'.$this->shipment->id.'/status', [
                'status_id' => $status->id,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_shipments(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipments')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipments/'.$this->shipment->id)
            ->assertOk();
    }

    public function test_client_cannot_access_platform_shipment_queue_and_kpis(): void
    {
        $client = $this->makeClient(Company::factory()->create());

        $this->actingAs($client, 'api')->getJson('/api/v1/shipments/queue')->assertForbidden();
        $this->actingAs($client, 'api')->getJson('/api/v1/shipments/kpis')->assertForbidden();
    }

    public function test_driver_cannot_access_platform_shipment_queue_and_kpis(): void
    {
        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($driverUser, 'api')->getJson('/api/v1/shipments/queue')->assertForbidden();
        $this->actingAs($driverUser, 'api')->getJson('/api/v1/shipments/kpis')->assertForbidden();
    }

    public function test_dispatcher_can_access_shipment_operational_kpis(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/shipments/queue')->assertOk();
        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/shipments/kpis')->assertOk();
    }

    public function test_admin_can_access_governance_dashboard_kpis(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->getJson('/api/v1/dashboard/kpi')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/dashboard/performance')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/dashboard/activity')->assertOk();
    }

    public function test_client_and_driver_cannot_access_dashboard_kpis(): void
    {
        $client = $this->makeClient(Company::factory()->create());
        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        foreach (['/api/v1/dashboard/kpi', '/api/v1/dashboard/performance', '/api/v1/dashboard/activity'] as $uri) {
            $this->actingAs($client, 'api')->getJson($uri)->assertForbidden();
            $this->actingAs($driverUser, 'api')->getJson($uri)->assertForbidden();
        }
    }

    public function test_client_and_driver_cannot_access_vehicle_and_driver_metrics(): void
    {
        $client = $this->makeClient(Company::factory()->create());
        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        foreach ([
            '/api/v1/vehicles/availability',
            '/api/v1/vehicles/fleet-kpis',
            '/api/v1/drivers/availability',
            '/api/v1/drivers/kpis',
        ] as $uri) {
            $this->actingAs($client, 'api')->getJson($uri)->assertForbidden();
            $this->actingAs($driverUser, 'api')->getJson($uri)->assertForbidden();
        }
    }

    public function test_admin_can_finalize_and_void_monthly_invoice_via_gates(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('monthlyInvoice.finalize'));
        $this->assertTrue(Gate::forUser($admin)->allows('monthlyInvoice.void'));
    }

    public function test_dispatcher_cannot_finalize_or_void_monthly_invoice(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->assertFalse(Gate::forUser($dispatcher)->allows('monthlyInvoice.finalize'));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('monthlyInvoice.void'));

        $company = Company::factory()->create();
        $invoice = MonthlyInvoice::create([
            'company_id' => $company->id,
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'status' => 'draft',
            'issued_at' => null,
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertForbidden();
    }

    public function test_admin_retains_company_governance(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('company.create'));
        $this->assertTrue(Gate::forUser($admin)->allows('company.update'));
    }

    public function test_admin_retains_driver_registry_governance(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('driver.create'));
        $this->assertTrue(Gate::forUser($admin)->allows('driver.update'));
    }

    public function test_admin_retains_trailer_governance(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('trailer.create'));
        $this->assertTrue(Gate::forUser($admin)->allows('trailer.update'));
    }

    public function test_admin_retains_vehicle_governance(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('vehicle.create'));
        $this->assertTrue(Gate::forUser($admin)->allows('vehicle.update'));
    }

    public function test_dispatcher_can_view_trailers_after_gate_definitions(): void
    {
        $dispatcher = $this->makeDispatcher();
        Trailer::factory()->create();

        $this->assertTrue(Gate::forUser($dispatcher)->allows('trailer.view'));

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/trailers')
            ->assertOk();
    }

    public function test_dispatcher_cannot_mutate_trailer_registry(): void
    {
        $dispatcher = $this->makeDispatcher();
        $trailer = Trailer::factory()->create();
        $status = Status::firstOrCreate(['name' => 'Available', 'type' => 'trailer']);

        $this->assertFalse(Gate::forUser($dispatcher)->allows('trailer.create'));

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/trailers', [
                'trailer_code' => 'TR-DENY-001',
                'fleet_number' => 'FT-DENY-001',
                'plate_number' => 'PL-DENY-001',
                'trailer_type' => 'Dry Van',
                'manufacturer' => 'Great Dane',
                'status_id' => $status->id,
            ])
            ->assertForbidden();
    }

    public function test_admin_assignment_operational_gates_are_denied(): void
    {
        $admin = $this->makeAdmin();
        $assignment = $this->makeAssignment();

        $this->assertFalse(Gate::forUser($admin)->allows('assignment.view'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.create'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.update'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.delete'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.change-status'));
        $this->assertFalse($admin->can('create', Assignment::class));
        $this->assertFalse($admin->can('update', $assignment));
    }

    public function test_admin_can_still_soft_delete_shipment_as_platform_governance(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/v1/shipments/'.$this->shipment->id)
            ->assertOk();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAssignmentPayload(array $overrides = []): array
    {
        return array_merge([
            'shipment_id' => $this->shipment->id,
            'assignment_code' => 'ADM07-'.fake()->unique()->numerify('#####'),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => null,
            'assigned_by' => $this->makeAdmin()->id,
            'status_id' => $this->assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'ended_at' => null,
            'is_active' => true,
            'notes' => 'ADMIN-07 test assignment',
        ], $overrides);
    }
}
