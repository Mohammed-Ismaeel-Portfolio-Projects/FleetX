<?php

namespace Tests\Feature\ShipmentOrder;

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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.9-B3.4 conversion API for ops Shipment Request endpoints.
 */
class DispatcherShipmentRequestConversionTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $order = $this->makeApprovedOrder();

        $this->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertUnauthorized();
    }

    public function test_client_returns_403(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeApprovedOrder($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertForbidden();
    }

    public function test_unlinked_client_returns_403(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeApprovedOrder();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertForbidden();
    }

    public function test_dispatcher_can_convert_approved_order(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $response = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertCreated()
            ->assertJsonPath('data.status.name', 'converted')
            ->assertJsonPath('data.converted_shipment.shipment_order_id', $order->id)
            ->assertJsonPath('data.converted_shipment.status.name', ShipmentStatus::WAITING_ASSIGNMENT);

        $this->assertNotNull($response->json('data.converted_shipment_id'));
    }

    public function test_admin_cannot_convert_approved_order(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeApprovedOrder();
        $shipmentsBefore = Shipment::query()->count();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertForbidden();

        $order->refresh();
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->status->name);
        $this->assertNull($order->converted_shipment_id);
    }

    public function test_approved_conversion_creates_shipment_with_traceability(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();
        $shipmentsBefore = Shipment::query()->count();

        $response = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertCreated();

        $this->assertSame($shipmentsBefore + 1, Shipment::query()->count());

        $order->refresh();
        $shipment = Shipment::findOrFail($order->converted_shipment_id);

        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $this->assertSame($order->id, $shipment->shipment_order_id);
        $this->assertSame($shipment->id, $response->json('data.converted_shipment_id'));
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->status->name);
    }

    public function test_already_converted_returns_200_without_duplicate(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $first = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertCreated();

        $historyAfterFirst = $this->historyCount($order);

        $second = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertOk()
            ->assertJsonPath('message', 'Shipment request already converted');

        $this->assertSame($first->json('data.converted_shipment.id'), $second->json('data.converted_shipment.id'));
        $this->assertSame(1, Shipment::query()->where('shipment_order_id', $order->id)->count());
        $this->assertSame($historyAfterFirst, $this->historyCount($order));
    }

    public function test_invalid_lifecycle_states_return_422(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $shipmentsBefore = Shipment::query()->count();

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
                ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
                ->assertStatus(422);

            $this->assertSame($statusName, $order->fresh()->status->name);
        }

        $this->assertSame($shipmentsBefore, Shipment::query()->count());
    }

    public function test_unknown_order_returns_404(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.Str::uuid()->toString().'/convert')
            ->assertNotFound();
    }

    public function test_prohibited_fields_return_422(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $prohibitedPayloads = [
            ['status' => 'converted'],
            ['status_id' => 1],
            ['customer_company_id' => Str::uuid()->toString()],
            ['converted_shipment_id' => Str::uuid()->toString()],
            ['shipment_order_id' => Str::uuid()->toString()],
            ['submission_revision' => 2],
            ['payload_json' => ['x' => 1]],
            ['driver_id' => 1],
            ['vehicle_id' => 1],
            ['assignment_id' => 1],
            ['tracking' => ['lat' => 1]],
            ['gps' => ['lat' => 1]],
            ['pricing' => ['amount' => 100]],
            ['shipment_number' => 'SHP-99999'],
            ['cargo_type' => 'Override'],
            ['reference_number' => 'REF-OVERRIDE'],
            ['priority' => 'high'],
            ['notes' => 'Override notes'],
        ];

        foreach ($prohibitedPayloads as $payload) {
            $this->actingAs($dispatcher, 'api')
                ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert', $payload)
                ->assertStatus(422);
        }

        $this->assertSame(ShipmentOrderStatus::APPROVED, $order->fresh()->status->name);
        $this->assertNull($order->fresh()->converted_shipment_id);
    }

    public function test_dispatcher_can_convert_foreign_company_order(): void
    {
        $dispatcher = $this->makeDispatcher();
        $foreignCompany = Company::factory()->create();
        $order = $this->makeApprovedOrder($foreignCompany);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertCreated()
            ->assertJsonPath('data.customer_company.id', $foreignCompany->id)
            ->assertJsonPath('data.status.name', 'converted');
    }

    public function test_conversion_history_records_actor(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeApprovedOrder();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/convert')
            ->assertCreated();

        $latest = $this->latestHistory($order->fresh());
        $this->assertSame(ShipmentOrderStatus::APPROVED, $latest->fromStatus->name);
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $latest->toStatus->name);
        $this->assertSame($dispatcher->id, $latest->changed_by_user_id);
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

    private function makeApprovedOrder(?Company $company = null): ShipmentOrder
    {
        $company ??= Company::factory()->create();

        return ShipmentOrder::factory()->create([
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
            'cargo_description' => 'Conversion API test',
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

    private function historyCount(ShipmentOrder $order): int
    {
        return ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();
    }

    private function latestHistory(ShipmentOrder $order): ShipmentOrderStatusHistory
    {
        return ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->with(['fromStatus', 'toStatus'])
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
