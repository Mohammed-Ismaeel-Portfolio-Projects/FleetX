<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\Shipment\CreateShipmentAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Actions\ShipmentOrder\ShipmentOrderConversionMapper;
use App\Core\ApiResult;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.9-B3.5 conversion hardening — concurrency, idempotency, rollback, invariants, regression.
 */
class ShipmentOrderConversionConcurrencyTest extends TestCase
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

    // -------------------------------------------------------------------------
    // 1. Concurrency
    // -------------------------------------------------------------------------

    public function test_api_sequential_conversion_attempts_yield_201_then_200(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $historyBefore = $this->conversionHistoryCount($order);

        $first = $this->actingAs($dispatcher, 'api')
            ->postJson($this->convertUrl($order));

        $second = $this->actingAs($dispatcher, 'api')
            ->postJson($this->convertUrl($order));

        $first->assertCreated();
        $second->assertOk()
            ->assertJsonPath('message', 'Shipment request already converted');

        $this->assertConversionInvariants($order, $historyBefore + 1);
        $this->assertSame(
            $first->json('data.converted_shipment.id'),
            $second->json('data.converted_shipment.id')
        );
    }

    public function test_action_sequential_conversion_attempts_create_single_side_effects(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $historyBefore = $this->conversionHistoryCount($order);
        $shipmentsBefore = Shipment::query()->count();

        $first = $this->action->execute($dispatcher, $order->id);
        $second = $this->action->execute($dispatcher, $order->id);

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame('Shipment request already converted', $second->message);
        $this->assertSame($shipmentsBefore + 1, Shipment::query()->count());
        $this->assertSame($historyBefore + 1, $this->conversionHistoryCount($order));
        $this->assertSame(
            $first->data['shipment']->id,
            $second->data['shipment']->id
        );
    }

    // -------------------------------------------------------------------------
    // 2. Retry / idempotency (triple POST)
    // -------------------------------------------------------------------------

    public function test_triple_convert_requests_remain_idempotent(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $responses = [
            $this->actingAs($dispatcher, 'api')->postJson($this->convertUrl($order)),
            $this->actingAs($dispatcher, 'api')->postJson($this->convertUrl($order)),
            $this->actingAs($dispatcher, 'api')->postJson($this->convertUrl($order)),
        ];

        $this->assertSame(201, $responses[0]->status());
        $this->assertSame(200, $responses[1]->status());
        $this->assertSame(200, $responses[2]->status());

        $shipmentId = $responses[0]->json('data.converted_shipment.id');
        $this->assertSame($shipmentId, $responses[1]->json('data.converted_shipment.id'));
        $this->assertSame($shipmentId, $responses[2]->json('data.converted_shipment.id'));
        $this->assertSame(1, Shipment::query()->where('shipment_order_id', $order->id)->count());
        $this->assertSame(1, $this->conversionHistoryCount($order));

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $this->assertSame($shipmentId, $order->converted_shipment_id);
    }

    // -------------------------------------------------------------------------
    // 3. Database invariants
    // -------------------------------------------------------------------------

    public function test_successful_conversion_satisfies_database_invariants(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $order = $this->makeApprovedOrder($company, [
            'reference_number' => 'INV-001',
            'requested_pickup_date' => '2026-10-01',
            'requested_delivery_date' => '2026-10-05',
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson($this->convertUrl($order))
            ->assertCreated();

        $order->refresh()->load(['status', 'linkedShipment.shipmentOrder', 'convertedShipment.shipmentOrder']);

        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $this->assertNotNull($order->converted_shipment_id);

        $shipment = $order->linkedShipment;
        $this->assertNotNull($shipment);
        $this->assertSame($order->id, $shipment->shipment_order_id);
        $this->assertTrue($order->linkedShipment->is($shipment));
        $this->assertTrue($shipment->shipmentOrder->is($order));
        $this->assertTrue($order->convertedShipment->is($shipment));
    }

    public function test_database_unique_constraint_prevents_duplicate_shipment_order_link(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $this->action->execute($dispatcher, $order->id);

        $this->expectException(QueryException::class);
        Shipment::factory()->create(['shipment_order_id' => $order->id]);
    }

    // -------------------------------------------------------------------------
    // 4. Lifecycle invariants
    // -------------------------------------------------------------------------

    public function test_only_approved_origin_is_convertible_via_api(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();

        foreach ([
            ShipmentOrderStatus::DRAFT,
            ShipmentOrderStatus::SUBMITTED,
            ShipmentOrderStatus::UNDER_REVIEW,
            ShipmentOrderStatus::CHANGES_REQUESTED,
            ShipmentOrderStatus::REJECTED,
            ShipmentOrderStatus::CANCELLED,
        ] as $statusName) {
            $order = $this->makeOrderInStatus($statusName, $company);

            $this->actingAs($dispatcher, 'api')
                ->postJson($this->convertUrl($order))
                ->assertStatus(422);

            $this->assertSame($statusName, $order->fresh()->status->name);
            $this->assertNull($order->fresh()->converted_shipment_id);
        }
    }

    public function test_conversion_creates_exactly_one_approved_to_converted_history_row(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $this->action->execute($dispatcher, $order->id);

        $rows = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->whereHas('fromStatus', fn ($q) => $q->where('name', ShipmentOrderStatus::APPROVED))
            ->whereHas('toStatus', fn ($q) => $q->where('name', ShipmentOrderStatus::CONVERTED))
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($dispatcher->id, $rows->first()->changed_by_user_id);
    }

    // -------------------------------------------------------------------------
    // 5. Transaction / rollback hardening
    // -------------------------------------------------------------------------

    public function test_failed_shipment_creation_leaves_order_approved_without_side_effects(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $shipmentsBefore = Shipment::query()->count();

        $this->mock(CreateShipmentAction::class, function ($mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(ApiResult::error('Simulated create failure', 500));
        });

        $result = app(ConvertShipmentOrderToShipmentAction::class)->execute($dispatcher, $order->id);

        $this->assertFalse($result->success);
        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertSame(0, $this->conversionHistoryCount($order));
    }

    public function test_shipment_model_exception_during_create_rolls_back(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $shipmentsBefore = Shipment::query()->count();

        Shipment::creating(function (): void {
            throw new \RuntimeException('create blocked');
        });

        try {
            $this->action->execute($dispatcher, $order->id);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('create blocked', $e->getMessage());
        } finally {
            Shipment::flushEventListeners();
        }

        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertSame(0, $this->conversionHistoryCount($order));
    }

    public function test_fk_linking_failure_rolls_back_shipment_and_order(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $shipmentsBefore = Shipment::query()->count();

        Shipment::updating(function (Shipment $shipment): void {
            if ($shipment->isDirty('shipment_order_id')) {
                throw new \RuntimeException('fk link blocked');
            }
        });

        try {
            $this->action->execute($dispatcher, $order->id);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('fk link blocked', $e->getMessage());
        } finally {
            Shipment::flushEventListeners();
        }

        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertSame(0, $this->conversionHistoryCount($order));
    }

    public function test_transition_failure_rolls_back_entire_conversion(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $shipmentsBefore = Shipment::query()->count();

        ShipmentOrderStatusHistory::creating(function (): void {
            throw new \RuntimeException('history blocked');
        });

        try {
            $this->action->execute($dispatcher, $order->id);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('history blocked', $e->getMessage());
        } finally {
            ShipmentOrderStatusHistory::flushEventListeners();
        }

        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertSame(0, $this->conversionHistoryCount($order));
    }

    // -------------------------------------------------------------------------
    // 6. Authorization regression
    // -------------------------------------------------------------------------

    public function test_conversion_authorization_matrix(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeApprovedOrder($company);

        $this->postJson($this->convertUrl($order))->assertUnauthorized();

        $client = $this->makeClient($company);
        $this->actingAs($client, 'api')->postJson($this->convertUrl($order))->assertForbidden();

        $unlinked = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($unlinked, 'api')->postJson($this->convertUrl($order))->assertForbidden();

        $dispatcher = $this->makeDispatcher();
        $this->actingAs($dispatcher, 'api')->postJson($this->convertUrl($order))->assertCreated();

        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $order2 = $this->makeApprovedOrder($company);
        $this->actingAs($admin, 'api')->postJson($this->convertUrl($order2))->assertForbidden();
    }

    public function test_conversion_uses_dedicated_gate_not_shipment_create(): void
    {
        $dispatcher = $this->makeDispatcher();
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $client = $this->makeClient(Company::factory()->create());
        $order = $this->makeApprovedOrder();

        $this->assertTrue(Gate::forUser($dispatcher)->allows('shipmentOrder.convert'));
        $this->assertFalse(Gate::forUser($admin)->allows('shipmentOrder.convert'));
        $this->assertFalse(Gate::forUser($client)->allows('shipmentOrder.convert'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('shipment.create'));
        $this->assertFalse(Gate::forUser($admin)->allows('shipment.create'));
        $this->assertFalse($client->can('convert', $order));
    }

    // -------------------------------------------------------------------------
    // 7. OPS global visibility
    // -------------------------------------------------------------------------

    public function test_dispatcher_converts_foreign_company_order(): void
    {
        $foreign = Company::factory()->create();
        $order = $this->makeApprovedOrder($foreign);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson($this->convertUrl($order))
            ->assertCreated()
            ->assertJsonPath('data.customer_company.id', $foreign->id);
    }

    public function test_unknown_order_returns_404_for_dispatcher(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.Str::uuid()->toString().'/convert')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // 8. Request hardening regression
    // -------------------------------------------------------------------------

    public function test_prohibited_request_fields_return_422_without_mutation(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $fields = [
            'status' => 'converted',
            'status_id' => 1,
            'customer_company_id' => Str::uuid()->toString(),
            'converted_shipment_id' => Str::uuid()->toString(),
            'shipment_order_id' => Str::uuid()->toString(),
            'submission_revision' => 3,
            'payload_json' => ['x' => 1],
            'driver_id' => 1,
            'assignment_id' => 1,
            'tracking' => ['x' => 1],
            'gps' => ['lat' => 1],
            'pricing' => ['amount' => 1],
            'shipment_number' => 'SHP-00001',
            'priority' => 'high',
            'planned_pickup_date' => '2026-01-01',
            'notes' => 'override',
        ];

        foreach ($fields as $field => $value) {
            $this->actingAs($dispatcher, 'api')
                ->postJson($this->convertUrl($order), [$field => $value])
                ->assertStatus(422);
        }

        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
    }

    // -------------------------------------------------------------------------
    // 9. Mapping regression
    // -------------------------------------------------------------------------

    public function test_conversion_mapping_derives_shipment_from_persisted_order(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder(null, [
            'reference_number' => 'MAP-B35',
            'priority' => null,
            'requested_pickup_date' => '2026-11-01',
            'requested_delivery_date' => '2026-11-10',
            'client_notes' => 'Client note',
            'dispatcher_notes' => 'Dispatcher note',
            'payload_json' => ['revision' => 9],
            'submission_revision' => 9,
        ]);

        $expected = ShipmentOrderConversionMapper::toShipmentAttributes($order, $dispatcher->id);

        $this->action->execute($dispatcher, $order->id);

        $shipment = Shipment::query()->where('shipment_order_id', $order->id)->firstOrFail();

        $this->assertSame('normal', $expected['priority']);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->status->name);
        $this->assertSame('MAP-B35', $shipment->reference_number);
        $this->assertSame('2026-11-01', $shipment->planned_pickup_date->format('Y-m-d'));
        $this->assertSame('2026-11-10', $shipment->planned_delivery_date->format('Y-m-d'));
        $this->assertNotSame($order->status_id, $shipment->status_id);
        $this->assertStringContainsString('Client note', $shipment->notes);
        $this->assertStringContainsString('Dispatcher: Dispatcher note', $shipment->notes);
    }

    // -------------------------------------------------------------------------
    // 10. API + resource regression
    // -------------------------------------------------------------------------

    public function test_api_response_exposes_conversion_traceability_fields(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $this->actingAs($dispatcher, 'api')
            ->postJson($this->convertUrl($order))
            ->assertCreated()
            ->assertJsonPath('data.status.name', 'converted')
            ->assertJsonStructure([
                'data' => [
                    'converted_shipment_id',
                    'converted_shipment' => [
                        'id',
                        'shipment_number',
                        'shipment_order_id',
                        'status' => ['id', 'name', 'type'],
                    ],
                ],
            ])
            ->assertJsonPath('data.converted_shipment.shipment_order_id', $order->id)
            ->assertJsonPath('data.converted_shipment.status.name', ShipmentStatus::WAITING_ASSIGNMENT);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function convertUrl(ShipmentOrder $order): string
    {
        return '/api/v1/shipment-requests/'.$order->id.'/convert';
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
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Hardening test',
        ], $overrides));
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

    private function conversionHistoryCount(ShipmentOrder $order): int
    {
        return ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->whereHas('toStatus', fn ($q) => $q->where('name', ShipmentOrderStatus::CONVERTED))
            ->count();
    }

    private function assertConversionInvariants(ShipmentOrder $order, int $expectedHistoryCount): void
    {
        $order->refresh()->load(['status', 'linkedShipment']);

        $this->assertSame(1, Shipment::query()->where('shipment_order_id', $order->id)->count());
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $this->assertNotNull($order->converted_shipment_id);
        $this->assertSame($order->id, $order->linkedShipment->shipment_order_id);
        $this->assertSame($expectedHistoryCount, $this->conversionHistoryCount($order));
    }
}
