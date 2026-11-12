<?php

namespace Tests\Feature\Notification;

use App\Actions\Shipment\CreateShipmentAction;
use App\Actions\ShipmentOrder\ApproveShipmentOrderAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Actions\ShipmentOrder\RejectShipmentOrderAction;
use App\Actions\ShipmentOrder\RequestChangesShipmentOrderAction;
use App\Actions\ShipmentOrder\ShipmentOrderConversionMapper;
use App\Core\ApiResult;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ClientNotificationAction;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * C4.2-A2.2 — request lifecycle Client notification hooks.
 */
class RequestLifecycleClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private RequestChangesShipmentOrderAction $requestChanges;

    private ApproveShipmentOrderAction $approve;

    private RejectShipmentOrderAction $reject;

    private ConvertShipmentOrderToShipmentAction $convert;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->requestChanges = app(RequestChangesShipmentOrderAction::class);
        $this->approve = app(ApproveShipmentOrderAction::class);
        $this->reject = app(RejectShipmentOrderAction::class);
        $this->convert = app(ConvertShipmentOrderToShipmentAction::class);
    }

    public function test_successful_changes_requested_creates_request_details_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $result = $this->requestChanges->execute($this->makeDispatcher(), $order->id, 'Need cargo weight');

        $this->assertTrue($result->success);
        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $order->id,
            'title' => 'Changes requested on your shipment request',
            'message' => 'FleetX has requested changes to your shipment request.',
        ]);
    }

    public function test_changes_requested_action_id_equals_request_uuid(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->requestChanges->execute($this->makeDispatcher(), $order->id, 'Need cargo weight');

        $notification = Notification::query()->firstOrFail();
        $this->assertSame($order->id, $notification->action_id);
        $this->assertSame(ClientNotificationAction::KIND_REQUEST_DETAILS, $notification->action_kind);
    }

    public function test_changes_requested_notifies_all_clients_of_the_request_company(): void
    {
        $company = Company::factory()->create();
        $first = $this->makeClient($company);
        $second = $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->requestChanges->execute($this->makeDispatcher(), $order->id, 'Need cargo weight');

        $this->assertSame(2, Notification::query()->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $first->id, 'action_id' => $order->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $second->id, 'action_id' => $order->id]);
    }

    public function test_changes_requested_does_not_notify_clients_of_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $this->makeClient($company);
        $foreign = $this->makeClient($otherCompany);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->requestChanges->execute($this->makeDispatcher(), $order->id, 'Need cargo weight');

        $this->assertDatabaseMissing('notifications', ['user_id' => $foreign->id]);
        $this->assertSame(0, Notification::query()->where('user_id', $foreign->id)->count());
    }

    public function test_changes_requested_does_not_notify_dispatcher_admin_or_driver(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $dispatcher = $this->makeCompanyUser($company, $this->dispatcherRole);
        $admin = $this->makeCompanyUser($company, $this->adminRole);
        $driver = $this->makeCompanyUser($company, $this->driverRole);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->requestChanges->execute($this->makeDispatcher(), $order->id, 'Need cargo weight');

        $this->assertDatabaseMissing('notifications', ['user_id' => $dispatcher->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $driver->id]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_failed_changes_requested_creates_no_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED, $company);

        $result = $this->requestChanges->execute($this->makeDispatcher(), $order->id, 'Too early');

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->fresh()->status->name);
    }

    public function test_successful_approval_creates_request_details_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $result = $this->approve->execute($this->makeDispatcher(), $order->id);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $order->id,
            'title' => 'Shipment request approved',
            'message' => 'Your shipment request has been approved by FleetX.',
        ]);
        $this->assertSame(0, Notification::query()->where('action_kind', ClientNotificationAction::KIND_SHIPMENT_DETAILS)->count());
    }

    public function test_approval_action_id_equals_request_uuid(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->approve->execute($this->makeDispatcher(), $order->id);

        $this->assertSame($order->id, Notification::query()->firstOrFail()->action_id);
    }

    public function test_approval_does_not_notify_clients_of_another_company(): void
    {
        $company = Company::factory()->create();
        $foreign = $this->makeClient(Company::factory()->create());
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->approve->execute($this->makeDispatcher(), $order->id);

        $this->assertDatabaseMissing('notifications', ['user_id' => $foreign->id]);
    }

    public function test_failed_approval_creates_no_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED, $company);

        $result = $this->approve->execute($this->makeDispatcher(), $order->id);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_successful_rejection_creates_request_details_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $result = $this->reject->execute($this->makeDispatcher(), $order->id, 'Outside service area');

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $order->id,
            'title' => 'Shipment request rejected',
            'message' => 'Your shipment request has been rejected by FleetX.',
        ]);
        $this->assertStringNotContainsString('Outside service area', (string) Notification::query()->firstOrFail()->message);
    }

    public function test_rejection_action_id_equals_request_uuid(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->reject->execute($this->makeDispatcher(), $order->id, 'Outside service area');

        $this->assertSame($order->id, Notification::query()->firstOrFail()->action_id);
    }

    public function test_rejection_does_not_notify_clients_of_another_company(): void
    {
        $company = Company::factory()->create();
        $foreign = $this->makeClient(Company::factory()->create());
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->reject->execute($this->makeDispatcher(), $order->id, 'Outside service area');

        $this->assertDatabaseMissing('notifications', ['user_id' => $foreign->id]);
    }

    public function test_failed_rejection_creates_no_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED, $company);

        $result = $this->reject->execute($this->makeDispatcher(), $order->id, 'Too early');

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_rejection_does_not_persist_an_invented_rejection_reason_on_the_order(): void
    {
        $this->assertFalse(Schema::hasColumn('shipment_orders', 'rejection_reason'));
        $this->assertFalse(Schema::hasColumn('shipment_orders', 'reject_reason'));
        $this->assertFalse(Schema::hasColumn('notifications', 'rejection_reason'));

        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->reject->execute($this->makeDispatcher(), $order->id, 'Outside service area');

        $fresh = $order->fresh();
        $this->assertArrayNotHasKey('rejection_reason', $fresh->getAttributes());
        $this->assertArrayNotHasKey('reject_reason', $fresh->getAttributes());
    }

    public function test_first_successful_conversion_creates_exactly_one_request_details_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $result = $this->convert->execute($this->makeDispatcher(), $order->id);

        $this->assertTrue($result->success);
        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $order->id,
            'title' => 'Shipment request converted',
            'message' => 'Your shipment request has been converted into a shipment.',
        ]);
    }

    public function test_conversion_action_id_equals_request_uuid_not_shipment_uuid(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $result = $this->convert->execute($this->makeDispatcher(), $order->id);

        $this->assertTrue($result->success);
        $shipmentId = $order->fresh()->converted_shipment_id;
        $this->assertNotNull($shipmentId);
        $this->assertNotSame($order->id, $shipmentId);

        $notification = Notification::query()->firstOrFail();
        $this->assertSame($order->id, $notification->action_id);
        $this->assertNotSame($shipmentId, $notification->action_id);
        $this->assertSame(ClientNotificationAction::KIND_REQUEST_DETAILS, $notification->action_kind);
    }

    public function test_conversion_notifies_client_users_of_the_request_company(): void
    {
        $company = Company::factory()->create();
        $first = $this->makeClient($company);
        $second = $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $this->convert->execute($this->makeDispatcher(), $order->id);

        $this->assertSame(2, Notification::query()->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $first->id, 'action_id' => $order->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $second->id, 'action_id' => $order->id]);
    }

    public function test_already_converted_invocation_creates_no_additional_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);
        $dispatcher = $this->makeDispatcher();

        $first = $this->convert->execute($dispatcher, $order->id);
        $this->assertTrue($first->success);
        $this->assertSame(1, Notification::query()->count());

        $second = $this->convert->execute($dispatcher, $order->id);

        $this->assertTrue($second->success);
        $this->assertSame('Shipment request already converted', $second->message);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_failed_conversion_creates_no_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED, $company);

        $result = $this->convert->execute($this->makeDispatcher(), $order->id);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_failed_shipment_creation_during_conversion_creates_no_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $this->mock(CreateShipmentAction::class, function ($mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(ApiResult::error('Simulated create failure', 500));
        });

        $result = app(ConvertShipmentOrderToShipmentAction::class)->execute($this->makeDispatcher(), $order->id);

        $this->assertFalse($result->success);
        $this->assertSame(0, Notification::query()->count());
        $this->assertNull($order->fresh()->converted_shipment_id);
    }

    public function test_conversion_does_not_generate_shipment_details_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $this->convert->execute($this->makeDispatcher(), $order->id);

        $this->assertSame(0, Notification::query()->where('action_kind', ClientNotificationAction::KIND_SHIPMENT_DETAILS)->count());
        $this->assertSame(1, Notification::query()->where('action_kind', ClientNotificationAction::KIND_REQUEST_DETAILS)->count());
    }

    public function test_create_shipment_action_does_not_emit_client_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);
        $dispatcher = $this->makeDispatcher();

        $result = app(CreateShipmentAction::class)->execute(
            ShipmentOrderConversionMapper::toShipmentAttributes($order, $dispatcher->id)
        );

        $this->assertTrue($result->success);
        $this->assertInstanceOf(Shipment::class, $result->data);
        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
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

    private function makeCompanyUser(Company $company, Role $role): User
    {
        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeOrderInStatus(string $statusName, Company $company): ShipmentOrder
    {
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

    private function makeApprovedOrder(Company $company): ShipmentOrder
    {
        return $this->makeOrderInStatus(ShipmentOrderStatus::APPROVED, $company);
    }
}
