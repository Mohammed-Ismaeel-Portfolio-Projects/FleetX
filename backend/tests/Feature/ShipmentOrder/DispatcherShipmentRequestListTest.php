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
 * C2.9-B2.1 security / contract matrix for GET /api/v1/shipment-requests.
 */
class DispatcherShipmentRequestListTest extends TestCase
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
        $this->getJson('/api/v1/shipment-requests')->assertUnauthorized();
    }

    public function test_dispatcher_can_list_shipment_requests(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $this->makeOrder($company);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_admin_can_list_shipment_requests(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $company = Company::factory()->create();
        $this->makeOrder($company);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_client_is_forbidden_on_dispatcher_list(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $this->makeOrder($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertForbidden();
    }

    public function test_unlinked_client_is_forbidden_on_dispatcher_list(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertForbidden();
    }

    public function test_dispatcher_sees_requests_across_customer_companies(): void
    {
        $dispatcher = $this->makeDispatcher();
        $companyA = Company::factory()->create(['company_name' => 'Alpha Corp']);
        $companyB = Company::factory()->create(['company_name' => 'Beta Corp']);

        $orderA = $this->makeOrder($companyA, ['request_number' => 'REQ-ALPHA-1']);
        $orderB = $this->makeOrder($companyB, ['request_number' => 'REQ-BETA-1']);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($orderA->id, $ids);
        $this->assertContains($orderB->id, $ids);
        $this->assertSame(2, $response->json('data.meta.total'));
    }

    public function test_customer_company_id_filter_is_supported(): void
    {
        $dispatcher = $this->makeDispatcher();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $this->makeOrder($companyA);
        $this->makeOrder($companyB);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests?customer_company_id='.$companyA->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_status_filter_is_supported(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $draftStatus = Status::where('name', ShipmentOrderStatus::DRAFT)->where('type', ShipmentOrderStatus::TYPE)->firstOrFail();

        $this->makeOrder($company, ['status_id' => $this->statusSubmitted->id]);
        $this->makeOrder($company, ['status_id' => $draftStatus->id]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests?status=submitted')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_unsupported_sort_filter_returns_422(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests?sort_by=customer_company_id')
            ->assertStatus(422);
    }

    public function test_list_excludes_operational_payload_and_addresses(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $this->makeOrder($company, [
            'pickup_address' => '99 Secret Street',
            'payload_json' => ['cargo_type' => 'snapshot'],
            'dispatcher_notes' => 'Internal note',
        ]);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();

        $item = $response->json('data.data.0');
        $this->assertArrayNotHasKey('payload_json', $item);
        $this->assertArrayNotHasKey('submission_snapshot', $item);
        $this->assertArrayNotHasKey('pickup_address', $item);
        $this->assertArrayNotHasKey('dispatcher_notes', $item);
        $this->assertArrayHasKey('priority', $item);
        $this->assertArrayHasKey('customer_company', $item);
    }

    public function test_list_is_newest_first(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();

        $older = $this->makeOrder($company, [
            'request_number' => 'REQ-OLDER',
            'created_at' => now()->subDay(),
        ]);
        $newer = $this->makeOrder($company, [
            'request_number' => 'REQ-NEWER',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertSame($newer->id, $ids[0]);
        $this->assertSame($older->id, $ids[1]);
    }

    public function test_client_cannot_escalate_via_dispatcher_list_with_company_probe(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $this->makeOrder($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipment-requests?customer_company_id='.$company->id)
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(Company $company, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusSubmitted->id,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Test shipment request',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $overrides));
    }
}
