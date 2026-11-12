<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Assignment;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.9-A3.4.2 security / contract matrix for POST /api/v1/client/shipment-requests.
 */
class ClientShipmentRequestCreateTest extends TestCase
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

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/client/shipment-requests', [])
            ->assertUnauthorized();
    }

    public function test_linked_client_can_create_draft(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $payload = $this->validPayload();

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.name', 'draft')
            ->assertJsonPath('data.status.type', 'shipment_order')
            ->assertJsonPath('data.submission_revision', 0)
            ->assertJsonPath('data.cargo_type', 'general')
            ->assertJsonPath('data.pickup_city', 'Warsaw')
            ->assertJsonPath('data.delivery_city', 'Krakow');

        $this->assertMatchesRegularExpression('/^REQ-\d{5}$/', $response->json('data.request_number'));
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_admin_is_forbidden(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_dispatcher_is_forbidden(): void
    {
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_customer_company_id_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'customer_company_id' => $other->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_company_id_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'company_id' => $other->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_submitted_by_user_id_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'submitted_by_user_id' => $client->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_status_id_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $approved = Status::where('name', ShipmentOrderStatus::APPROVED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'status_id' => $approved->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_status_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'status' => 'approved',
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_submission_revision_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'submission_revision' => 5,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_payload_json_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'payload_json' => ['cargo_type' => 'injected'],
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_converted_shipment_id_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'converted_shipment_id' => Str::uuid()->toString(),
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_request_number_supplied_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'request_number' => 'REQ-99999',
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_created_record_belongs_to_authenticated_users_company(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload());

        $response->assertCreated();

        $order = ShipmentOrder::query()->findOrFail($response->json('data.id'));
        $this->assertSame($company->id, $order->customer_company_id);
    }

    public function test_draft_initialization_semantics(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload());

        $response->assertCreated();

        $order = ShipmentOrder::query()->with('status')->findOrFail($response->json('data.id'));
        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->status->name);
        $this->assertSame(ShipmentOrderStatus::TYPE, $order->status->type);
        $this->assertSame(0, $order->submission_revision);
        $this->assertNull($order->payload_json);
        $this->assertNull($order->submitted_by_user_id);
    }

    public function test_no_status_history_row_is_created(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload());

        $response->assertCreated();

        $this->assertSame(
            0,
            ShipmentOrderStatusHistory::query()
                ->where('shipment_order_id', $response->json('data.id'))
                ->count()
        );
    }

    public function test_request_number_is_generated_server_side_and_unique(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'request_number' => 'REQ-01000',
        ]);

        $first = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload());

        $second = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload());

        $first->assertCreated();
        $second->assertCreated();

        $this->assertSame('REQ-01001', $first->json('data.request_number'));
        $this->assertSame('REQ-01002', $second->json('data.request_number'));
    }

    public function test_response_excludes_server_owned_fields(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload());

        $response->assertCreated();
        $data = $response->json('data');

        $this->assertArrayNotHasKey('customer_company_id', $data);
        $this->assertArrayNotHasKey('submitted_by_user_id', $data);
        $this->assertArrayNotHasKey('payload_json', $data);
        $this->assertArrayNotHasKey('converted_shipment_id', $data);
        $this->assertArrayNotHasKey('dispatcher_notes', $data);
        $this->assertArrayNotHasKey('priority', $data);
    }

    public function test_shipment_operational_status_cannot_be_injected(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipmentStatus = Status::firstOrCreate(
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE]
        );

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', array_merge($this->validPayload(), [
                'status_id' => $shipmentStatus->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_no_shipment_is_created(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $before = Shipment::query()->count();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload())
            ->assertCreated();

        $this->assertSame($before, Shipment::query()->count());
    }

    public function test_no_assignment_is_created(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $before = Assignment::query()->count();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload())
            ->assertCreated();

        $this->assertSame($before, Assignment::query()->count());
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'reference_number' => 'REF-CREATE-001',
            'cargo_type' => 'general',
            'trailer_type' => 'dry_van',
            'cargo_description' => 'Office supplies',
            'package_count' => 3,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
            'special_instructions' => 'Fragile',
            'client_notes' => 'Please call before pickup',
        ];
    }

    public function test_create_rejects_non_enum_cargo_type(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'cargo_type' => 'General Freight',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cargo_type']);

        $this->assertDatabaseCount('shipment_orders', 0);
    }

    public function test_create_allows_incomplete_draft_without_cargo_or_trailer(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'reference_number' => 'REF-EMPTY-DRAFT',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status.name', 'draft')
            ->assertJsonPath('data.cargo_type', null)
            ->assertJsonPath('data.trailer_type', null);
    }

    public function test_create_persists_trailer_type_and_exposes_on_show(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $created = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'cargo_type' => 'refrigerated',
                'trailer_type' => 'reefer',
                'cargo_description' => 'Cold goods',
            ])
            ->assertCreated()
            ->assertJsonPath('data.cargo_type', 'refrigerated')
            ->assertJsonPath('data.trailer_type', 'reefer');

        $id = $created->json('data.id');

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.cargo_type', 'refrigerated')
            ->assertJsonPath('data.trailer_type', 'reefer')
            ->assertJsonPath('data.cargo_description', 'Cold goods');
    }
}
