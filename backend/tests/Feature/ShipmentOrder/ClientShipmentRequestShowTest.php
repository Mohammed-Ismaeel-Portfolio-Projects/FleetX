<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.9-A3.4.1 security / contract matrix for GET /api/v1/client/shipment-requests/{shipmentOrder}.
 */
class ClientShipmentRequestShowTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusSubmitted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->statusSubmitted = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/shipment-requests/'.Str::uuid()->toString())
            ->assertUnauthorized();
    }

    public function test_linked_client_can_show_own_request(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'request_number' => 'REQ-SHOW-001',
            'reference_number' => 'REF-SHOW-001',
            'cargo_type' => 'general',
            'cargo_description' => 'Pallets of goods',
            'trailer_type' => 'dry_van',
            'cargo_weight' => 1200.50,
            'package_count' => 4,
            'pickup_contact' => 'Pickup Person',
            'pickup_phone' => '+48111222333',
            'pickup_address' => '10 Pickup Street',
            'delivery_contact' => 'Delivery Person',
            'delivery_phone' => '+48444555666',
            'delivery_address' => '20 Delivery Street',
            'special_instructions' => 'Handle with care',
            'client_notes' => 'Customer note',
            'dispatcher_notes' => 'Internal dispatcher note',
            'payload_json' => ['cargo_type' => 'secret snapshot'],
            'submitted_by_user_id' => $client->id,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.request_number', 'REQ-SHOW-001')
            ->assertJsonPath('data.reference_number', 'REF-SHOW-001')
            ->assertJsonPath('data.status.name', 'submitted')
            ->assertJsonPath('data.status.type', 'shipment_order')
            ->assertJsonPath('data.submission_revision', 1)
            ->assertJsonPath('data.cargo_type', 'general')
            ->assertJsonPath('data.cargo_description', 'Pallets of goods')
            ->assertJsonPath('data.trailer_type', 'dry_van')
            ->assertJsonPath('data.package_count', 4)
            ->assertJsonPath('data.pickup_city', 'Warsaw')
            ->assertJsonPath('data.pickup_address', '10 Pickup Street')
            ->assertJsonPath('data.delivery_city', 'Krakow')
            ->assertJsonPath('data.special_instructions', 'Handle with care')
            ->assertJsonPath('data.client_notes', 'Customer note');

        $data = $response->json('data');
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('hazardous_cargo', $data);
        $this->assertArrayHasKey('temperature_controlled', $data);
        $this->assertArrayHasKey('review_feedback', $data);
        $this->assertNull($data['review_feedback']);
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);

        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertForbidden();
    }

    public function test_dispatcher_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertForbidden();
    }

    public function test_unknown_id_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_another_company_request_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeOrder($companyB);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_customer_company_id_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id.'?customer_company_id='.$company->id)
            ->assertStatus(422);
    }

    public function test_company_id_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id.'?company_id='.$company->id)
            ->assertStatus(422);
    }

    public function test_submitted_by_user_id_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id.'?submitted_by_user_id='.$client->id)
            ->assertStatus(422);
    }

    public function test_response_excludes_forbidden_fields(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'dispatcher_notes' => 'Must not leak',
            'payload_json' => ['secret' => true],
            'submitted_by_user_id' => $client->id,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk();
        $content = $response->getContent();
        $data = $response->json('data');

        $this->assertArrayNotHasKey('customer_company_id', $data);
        $this->assertArrayNotHasKey('submitted_by_user_id', $data);
        $this->assertArrayNotHasKey('payload_json', $data);
        $this->assertArrayNotHasKey('converted_shipment_id', $data);
        $this->assertArrayNotHasKey('dispatcher_notes', $data);
        $this->assertArrayNotHasKey('priority', $data);
        $this->assertArrayNotHasKey('driver', $data);
        $this->assertArrayNotHasKey('vehicle', $data);
        $this->assertStringNotContainsString('Must not leak', $content);
        $this->assertStringNotContainsString('"secret"', $content);
    }

    public function test_status_uses_shipment_order_domain_only(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk();
        $this->assertSame('shipment_order', $response->json('data.status.type'));
        $this->assertSame('submitted', $response->json('data.status.name'));
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
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(Company $company, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusSubmitted->id,
            'submission_revision' => 1,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_company' => 'Pickup Co',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_company' => 'Delivery Co',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }
}
