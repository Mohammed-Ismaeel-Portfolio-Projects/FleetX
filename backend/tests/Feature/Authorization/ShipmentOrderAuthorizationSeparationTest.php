<?php

namespace Tests\Feature\Authorization;

use App\Models\Company;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
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
 * ADMIN-07.6 — ShipmentOrder Admin/Dispatcher authorization separation.
 */
class ShipmentOrderAuthorizationSeparationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    private Role $driverRole;

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
    }

    public function test_admin_cannot_start_review(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.startReview'));
        $this->assertFalse($admin->can('startReview', $order));

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertForbidden();

        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->fresh()->status->name);
    }

    public function test_admin_cannot_request_changes(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.requestChanges'));

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/request-changes', [
                'reason' => 'Please update pickup details',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_approve(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.approve'));

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve')
            ->assertForbidden();
    }

    public function test_admin_cannot_reject(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.reject'));

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/reject', [
                'reason' => 'Does not meet operational criteria',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_convert(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED);

        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.convert'));
        $this->assertFalse($admin->can('convert', $order));

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertForbidden();
    }

    public function test_admin_convert_does_not_create_shipment_or_change_order_state(): void
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

    public function test_admin_can_view_any_shipment_orders(): void
    {
        $admin = $this->makeAdmin();
        ShipmentOrder::factory()->count(2)->create();

        $this->assertTrue(Gate::forUser($admin)->allows('shipmentOrder.viewAny'));
        $this->assertTrue($admin->can('viewAny', ShipmentOrder::class));

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();
    }

    public function test_admin_can_view_shipment_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->assertTrue(Gate::forUser($admin)->allows('shipmentOrder.view'));
        $this->assertTrue($admin->can('view', $order));

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();
    }

    public function test_dispatcher_can_start_review(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertOk()
            ->assertJsonPath('data.status.name', ShipmentOrderStatus::UNDER_REVIEW);
    }

    public function test_dispatcher_can_request_changes(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/request-changes', [
                'reason' => 'Need updated delivery window',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', ShipmentOrderStatus::CHANGES_REQUESTED);
    }

    public function test_dispatcher_can_approve(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status.name', ShipmentOrderStatus::APPROVED);
    }

    public function test_dispatcher_can_reject(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/reject', [
                'reason' => 'Cannot fulfill this request',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', ShipmentOrderStatus::REJECTED);
    }

    public function test_dispatcher_can_convert(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED);
        $shipmentsBefore = Shipment::query()->count();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertCreated()
            ->assertJsonPath('data.status.name', ShipmentOrderStatus::CONVERTED)
            ->assertJsonPath('data.converted_shipment.status.name', ShipmentStatus::WAITING_ASSIGNMENT);

        $this->assertSame($shipmentsBefore + 1, Shipment::query()->count());
    }

    public function test_client_shipment_request_apis_remain_unchanged(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $admin = $this->makeAdmin();
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertForbidden();
    }

    public function test_driver_is_denied_ops_shipment_order_access(): void
    {
        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->assertFalse(Gate::forUser($driverUser)->allows('shipmentOrder.view'));
        $this->assertFalse(Gate::forUser($driverUser)->allows('shipmentOrder.convert'));

        $this->actingAs($driverUser, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertForbidden();

        $this->actingAs($driverUser, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertForbidden();
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
}
