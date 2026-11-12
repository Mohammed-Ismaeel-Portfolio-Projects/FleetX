<?php

namespace Tests\Feature\Authorization;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ADMIN-08 — Final Admin RBAC boundary verification.
 */
class AdminRbacFinalBoundaryTest extends TestCase
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
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

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

    // -------------------------------------------------------------------------
    // A–G: Admin operational denial
    // -------------------------------------------------------------------------

    public function test_admin_assignment_mutations_are_denied(): void
    {
        $admin = $this->makeAdmin();
        $assignment = $this->makeAssignment();

        $this->actingAs($admin, 'api')->getJson('/api/v1/assignments')->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/v1/assignments', $this->validAssignmentPayload())->assertForbidden();
        $this->actingAs($admin, 'api')->putJson('/api/v1/assignments/'.$assignment->id, ['notes' => 'x'])->assertForbidden();
        $this->actingAs($admin, 'api')->deleteJson('/api/v1/assignments/'.$assignment->id)->assertForbidden();
        $this->actingAs($admin, 'api')->patchJson('/api/v1/assignments/'.$assignment->id.'/status', [
            'status_id' => $this->assignmentStatus->id,
        ])->assertForbidden();
        $this->actingAs($admin, 'api')->patchJson('/api/v1/assignments/'.$assignment->id.'/restore')->assertForbidden();
    }

    public function test_admin_shipment_operational_mutations_are_denied(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create();
        $status = Status::firstOrCreate(['name' => 'Draft', 'type' => 'shipment']);

        $this->actingAs($admin, 'api')->postJson('/api/v1/shipments', [
            'customer_company_id' => $company->id,
            'priority' => 'normal',
            'status_id' => $status->id,
            'pickup_city' => 'Miami',
            'pickup_country' => 'US',
            'delivery_city' => 'Orlando',
            'delivery_country' => 'US',
        ])->assertForbidden();

        $this->actingAs($admin, 'api')->putJson('/api/v1/shipments/'.$this->shipment->id, [
            'priority' => 'high',
        ])->assertForbidden();

        $assignedStatus = Status::firstOrCreate(['name' => 'Assigned', 'type' => 'shipment']);
        $this->actingAs($admin, 'api')->patchJson('/api/v1/shipments/'.$this->shipment->id.'/status', [
            'status_id' => $assignedStatus->id,
        ])->assertForbidden();
    }

    public function test_admin_shipment_read_is_allowed(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->getJson('/api/v1/shipments')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/shipments/'.$this->shipment->id)->assertOk();
    }

    public function test_admin_shipment_order_mutations_are_denied(): void
    {
        $admin = $this->makeAdmin();
        $submitted = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);
        $underReview = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);
        $approved = $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED);

        $this->actingAs($admin, 'api')->postJson('/api/v1/shipment-requests/'.$submitted->id.'/start-review')->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/v1/shipment-requests/'.$underReview->id.'/request-changes', [
            'reason' => 'Need more detail',
        ])->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/v1/shipment-requests/'.$underReview->id.'/approve')->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/v1/shipment-requests/'.$underReview->id.'/reject', [
            'reason' => 'Not acceptable',
        ])->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/v1/shipment-requests/'.$approved->id.'/convert')->assertForbidden();
    }

    public function test_admin_shipment_order_read_is_allowed(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->actingAs($admin, 'api')->getJson('/api/v1/shipment-requests')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/shipment-requests/'.$order->id)->assertOk();
    }

    public function test_admin_convert_does_not_create_shipment_or_mutate_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED);
        $shipmentsBefore = Shipment::query()->count();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertForbidden();

        $order->refresh();
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->status->name);
        $this->assertNull($order->converted_shipment_id);
    }

    public function test_admin_cannot_reach_dispatcher_operational_workflows_via_gates(): void
    {
        $admin = $this->makeAdmin();
        $assignment = $this->makeAssignment();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED);

        $this->assertFalse(Gate::forUser($admin)->allows('assignment.create'));
        $this->assertFalse(Gate::forUser($admin)->allows('assignment.view'));
        $this->assertFalse(Gate::forUser($admin)->allows('shipment.create'));
        $this->assertFalse(Gate::forUser($admin)->allows('shipment.update'));
        $this->assertFalse(Gate::forUser($admin)->allows('shipment.change-status'));
        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.convert'));
        $this->assertFalse($admin->can('create', Assignment::class));
        $this->assertFalse($admin->can('convert', $order));
    }

    // -------------------------------------------------------------------------
    // H–N: Dispatcher + Client + Driver
    // -------------------------------------------------------------------------

    public function test_dispatcher_assignment_workflow_is_allowed(): void
    {
        $dispatcher = $this->makeDispatcher();

        $create = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/assignments', $this->validAssignmentPayload([
                'assignment_code' => 'FINAL-DSP-001',
            ]));

        $create->assertCreated();
        $assignmentId = $create->json('data.id');

        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/assignments')->assertOk();
        $this->actingAs($dispatcher, 'api')->putJson('/api/v1/assignments/'.$assignmentId, [
            'notes' => 'Updated',
        ])->assertOk();
    }

    public function test_dispatcher_shipment_workflow_is_allowed(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $status = Status::firstOrCreate(['name' => 'Draft', 'type' => 'shipment']);

        $this->actingAs($dispatcher, 'api')->postJson('/api/v1/shipments', [
            'customer_company_id' => $company->id,
            'priority' => 'normal',
            'status_id' => $status->id,
            'pickup_city' => 'Miami',
            'pickup_country' => 'US',
            'delivery_city' => 'Orlando',
            'delivery_country' => 'US',
        ])->assertCreated();
    }

    public function test_dispatcher_shipment_order_workflow_is_allowed(): void
    {
        $dispatcher = $this->makeDispatcher();
        $submitted = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);
        $approved = $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/start-review')
            ->assertOk();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$approved->id.'/convert')
            ->assertCreated()
            ->assertJsonPath('data.status.name', ShipmentOrderStatus::CONVERTED);
    }

    public function test_client_shipment_request_workflow_is_allowed(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')->getJson('/api/v1/client/shipment-requests')->assertOk();
    }

    public function test_client_cross_company_access_is_denied(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $foreignOrder = $this->makeOrderInStatus(ShipmentOrderStatus::DRAFT, $companyB);

        $this->actingAs($clientA, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$foreignOrder->id)
            ->assertNotFound();
    }

    public function test_driver_operational_access_is_denied(): void
    {
        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($driverUser, 'api')->getJson('/api/v1/dashboard/kpi')->assertForbidden();
        $this->actingAs($driverUser, 'api')->getJson('/api/v1/shipments/queue')->assertForbidden();
        $this->actingAs($driverUser, 'api')->getJson('/api/v1/assignments')->assertForbidden();
        $this->actingAs($driverUser, 'api')->getJson('/api/v1/shipment-requests')->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // O–S: Dashboard, invoice, trailer, pricing
    // -------------------------------------------------------------------------

    public function test_admin_dashboard_metrics_are_allowed(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->getJson('/api/v1/dashboard/kpi')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/dashboard/performance')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/shipments/kpis')->assertOk();
    }

    public function test_client_and_driver_platform_metrics_are_denied(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        foreach ([
            '/api/v1/dashboard/kpi',
            '/api/v1/shipments/queue',
            '/api/v1/vehicles/fleet-kpis',
            '/api/v1/drivers/kpis',
        ] as $uri) {
            $this->actingAs($client, 'api')->getJson($uri)->assertForbidden();
            $this->actingAs($driverUser, 'api')->getJson($uri)->assertForbidden();
        }
    }

    public function test_admin_invoice_lifecycle_gates_are_allowed(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('monthlyInvoice.finalize'));
        $this->assertTrue(Gate::forUser($admin)->allows('monthlyInvoice.void'));
        $this->assertTrue(Gate::forUser($admin)->allows('monthlyInvoice.view'));
    }

    public function test_dispatcher_invoice_lifecycle_mutation_is_denied(): void
    {
        $dispatcher = $this->makeDispatcher();
        $invoice = MonthlyInvoice::create([
            'company_id' => Company::factory()->create()->id,
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'currency' => 'USD',
            'subtotal' => 100,
            'total' => 100,
            'status' => 'draft',
            'issued_at' => null,
        ]);

        $this->assertFalse(Gate::forUser($dispatcher)->allows('monthlyInvoice.finalize'));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('monthlyInvoice.void'));

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertForbidden();
    }

    public function test_trailer_governance_boundary_remains_correct(): void
    {
        $admin = $this->makeAdmin();
        $dispatcher = $this->makeDispatcher();
        $status = Status::firstOrCreate(['name' => 'Available', 'type' => 'trailer']);

        $this->assertTrue(Gate::forUser($admin)->allows('trailer.create'));
        $this->assertTrue(Gate::forUser($admin)->allows('trailer.view'));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('trailer.create'));

        $this->actingAs($dispatcher, 'api')->postJson('/api/v1/trailers', [
            'trailer_code' => 'TR-FINAL-001',
            'fleet_number' => 'FT-FINAL-001',
            'plate_number' => 'PL-FINAL-001',
            'trailer_type' => 'Dry Van',
            'manufacturer' => 'Great Dane',
            'status_id' => $status->id,
        ])->assertForbidden();

        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/trailers')->assertOk();
    }

    public function test_pricing_authorization_remains_correct(): void
    {
        $admin = $this->makeAdmin();
        $dispatcher = $this->makeDispatcher();
        $client = $this->makeClient(Company::factory()->create());

        $this->assertTrue(Gate::forUser($admin)->allows('pricing.calculate'));
        $this->assertTrue(Gate::forUser($admin)->allows('pricing.admin.calculate'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('pricing.calculate'));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('pricing.admin.calculate'));
        $this->assertFalse(Gate::forUser($client)->allows('pricing.calculate'));
        $this->assertTrue(Gate::forUser($client)->allows('client.pricing.calculate'));
    }

    public function test_admin_shipment_governance_delete_remains_available(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue(Gate::forUser($admin)->allows('shipment.delete'));

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

    private function makeOrderInStatus(string $statusName, ?Company $company = null): ShipmentOrder
    {
        $company ??= Company::factory()->create();

        return ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => Status::where('name', $statusName)
                ->where('type', ShipmentOrderStatus::TYPE)
                ->firstOrFail()
                ->id,
            'converted_shipment_id' => null,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
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
            'assignment_code' => 'FINAL-'.fake()->unique()->numerify('#####'),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => null,
            'assigned_by' => $this->makeAdmin()->id,
            'status_id' => $this->assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'ended_at' => null,
            'is_active' => true,
            'notes' => 'ADMIN-08 boundary test',
        ], $overrides);
    }
}
