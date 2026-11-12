<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Actions\ShipmentOrder\ShipmentOrderConversionMapper;
use App\Models\Company;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.9-B3.3 conversion core — action, mapper, authorization (no HTTP).
 */
class ConvertShipmentOrderToShipmentActionTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private ConvertShipmentOrderToShipmentAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->action = app(ConvertShipmentOrderToShipmentAction::class);
    }

    public function test_admin_cannot_convert_via_gate_and_policy(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeApprovedOrder();

        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.convert'));
        $this->assertFalse($admin->can('convert', $order));
    }

    public function test_dispatcher_can_convert_via_gate_and_policy(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $this->assertTrue(Gate::forUser($dispatcher)->allows('shipmentOrder.convert'));
        $this->assertTrue($dispatcher->can('convert', $order));
    }

    public function test_client_cannot_convert_via_gate_or_policy(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $this->assertFalse(Gate::forUser($client)->allows('shipmentOrder.convert'));
        $this->assertFalse($client->can('convert', $order));
    }

    public function test_unlinked_client_cannot_convert(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeApprovedOrder();

        $this->assertFalse(Gate::forUser($client)->allows('shipmentOrder.convert'));
        $this->assertFalse($client->can('convert', $order));
    }

    public function test_unauthenticated_gate_denies_convert(): void
    {
        $this->assertFalse(Gate::allows('shipmentOrder.convert'));
    }

    public function test_approved_conversion_succeeds(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $result = $this->action->execute($dispatcher, $order->id);

        $this->assertTrue($result->success);
        $this->assertSame('Shipment request converted successfully', $result->message);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $this->assertNotNull($order->converted_shipment_id);

        $shipment = Shipment::findOrFail($order->converted_shipment_id);
        $this->assertSame($order->id, $shipment->shipment_order_id);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->status->name);
    }

    public function test_non_approved_statuses_are_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipmentsBefore = Shipment::query()->count();

        foreach ([
            ShipmentOrderStatus::DRAFT,
            ShipmentOrderStatus::SUBMITTED,
            ShipmentOrderStatus::UNDER_REVIEW,
            ShipmentOrderStatus::CHANGES_REQUESTED,
            ShipmentOrderStatus::REJECTED,
            ShipmentOrderStatus::CANCELLED,
        ] as $statusName) {
            $order = $this->makeOrderInStatus($statusName);

            $result = $this->action->execute($dispatcher, $order->id);

            $this->assertFalse($result->success, "Expected failure for status {$statusName}");
            $this->assertSame(422, $result->statusCode);
            $this->assertSame($statusName, $order->fresh()->status->name);
        }

        $this->assertSame($shipmentsBefore, Shipment::query()->count());
    }

    public function test_conversion_populates_bidirectional_traceability(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $this->action->execute($dispatcher, $order->id);

        $order->refresh()->load(['convertedShipment', 'linkedShipment']);
        $shipment = $order->convertedShipment;

        $this->assertNotNull($shipment);
        $this->assertTrue($order->linkedShipment->is($shipment));
        $this->assertTrue($shipment->shipmentOrder->is($order));
        $this->assertSame($shipment->id, $order->converted_shipment_id);
        $this->assertSame($order->id, $shipment->shipment_order_id);
    }

    public function test_conversion_creates_exactly_one_history_row(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $before = ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();

        $this->action->execute($dispatcher, $order->id);

        $history = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->with(['fromStatus', 'toStatus'])
            ->orderByDesc('id')
            ->first();

        $this->assertSame($before + 1, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());
        $this->assertSame(ShipmentOrderStatus::APPROVED, $history->fromStatus->name);
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $history->toStatus->name);
        $this->assertSame($dispatcher->id, $history->changed_by_user_id);
    }

    public function test_mapper_copies_operational_fields_and_maps_dates(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeApprovedOrder($company, [
            'reference_number' => 'REF-MAP-1',
            'priority' => 'high',
            'cargo_type' => 'Electronics',
            'requested_pickup_date' => '2026-09-01',
            'requested_delivery_date' => '2026-09-05',
            'client_notes' => 'Handle with care',
            'dispatcher_notes' => 'Priority customer',
            'payload_json' => ['revision' => 2],
            'submission_revision' => 2,
        ]);

        $mapped = ShipmentOrderConversionMapper::toShipmentAttributes($order, 99);

        $this->assertSame($company->id, $mapped['customer_company_id']);
        $this->assertSame('REF-MAP-1', $mapped['reference_number']);
        $this->assertSame('high', $mapped['priority']);
        $this->assertSame('Electronics', $mapped['cargo_type']);
        $this->assertSame('2026-09-01', $mapped['planned_pickup_date']->format('Y-m-d'));
        $this->assertSame('2026-09-05', $mapped['planned_delivery_date']->format('Y-m-d'));
        $this->assertSame(ShipmentStatus::conversionInitialStatusId(), $mapped['status_id']);
        $this->assertStringContainsString('Handle with care', $mapped['notes']);
        $this->assertStringContainsString('Dispatcher: Priority customer', $mapped['notes']);
        $this->assertArrayNotHasKey('payload_json', $mapped);
        $this->assertArrayNotHasKey('submission_revision', $mapped);
        $this->assertArrayNotHasKey('submitted_by_user_id', $mapped);
    }

    public function test_mapper_defaults_null_priority_to_normal(): void
    {
        $order = $this->makeApprovedOrder(null, ['priority' => null]);
        $mapped = ShipmentOrderConversionMapper::toShipmentAttributes($order, 1);

        $this->assertSame('normal', $mapped['priority']);
    }

    public function test_mapper_does_not_use_shipment_order_status_id(): void
    {
        $order = $this->makeApprovedOrder();
        $mapped = ShipmentOrderConversionMapper::toShipmentAttributes($order, 1);

        $this->assertNotSame($order->status_id, $mapped['status_id']);
        $this->assertSame(ShipmentStatus::TYPE, Status::findOrFail($mapped['status_id'])->type);
    }

    public function test_idempotent_second_conversion_returns_existing_shipment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $first = $this->action->execute($dispatcher, $order->id);
        $historyAfterFirst = ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();

        $second = $this->action->execute($dispatcher, $order->id);

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame('Shipment request already converted', $second->message);
        $this->assertSame($first->data['shipment']->id, $second->data['shipment']->id);
        $this->assertSame(1, Shipment::query()->where('shipment_order_id', $order->id)->count());
        $this->assertSame($historyAfterFirst, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());
    }

    public function test_unknown_order_returns_404(): void
    {
        $dispatcher = $this->makeDispatcher();
        $before = Shipment::query()->count();

        $result = $this->action->execute($dispatcher, Str::uuid()->toString());

        $this->assertFalse($result->success);
        $this->assertSame(404, $result->statusCode);
        $this->assertSame($before, Shipment::query()->count());
    }

    public function test_failed_conversion_does_not_create_shipment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);
        $before = Shipment::query()->count();

        $result = $this->action->execute($dispatcher, $order->id);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame($before, Shipment::query()->count());
        $this->assertNull($order->fresh()->converted_shipment_id);
    }

    public function test_concurrent_conversion_creates_only_one_shipment(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $results = [
            $this->action->execute($dispatcher, $order->id),
            $this->action->execute($dispatcher, $order->id),
        ];

        $this->assertTrue($results[0]->success);
        $this->assertTrue($results[1]->success);
        $this->assertSame($results[0]->data['shipment']->id, $results[1]->data['shipment']->id);
        $this->assertSame(1, Shipment::query()->where('shipment_order_id', $order->id)->count());
        $this->assertSame(
            1,
            ShipmentOrderStatusHistory::query()
                ->where('shipment_order_id', $order->id)
                ->whereHas('toStatus', fn ($q) => $q->where('name', ShipmentOrderStatus::CONVERTED))
                ->count()
        );
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

    private function makeApprovedOrder(?Company $company = null, array $overrides = []): ShipmentOrder
    {
        $company ??= Company::factory()->create();

        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => Status::where('name', ShipmentOrderStatus::APPROVED)
                ->where('type', ShipmentOrderStatus::TYPE)
                ->firstOrFail()
                ->id,
            'converted_shipment_id' => null,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $overrides));
    }

    private function makeOrderInStatus(string $statusName): ShipmentOrder
    {
        return ShipmentOrder::factory()->create([
            'status_id' => Status::where('name', $statusName)
                ->where('type', ShipmentOrderStatus::TYPE)
                ->firstOrFail()
                ->id,
            'converted_shipment_id' => null,
        ]);
    }
}
