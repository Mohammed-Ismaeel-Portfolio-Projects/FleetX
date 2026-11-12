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
 * C2.9-A3.4.1 security / contract matrix for GET /api/v1/client/shipment-requests.
 */
class ClientShipmentRequestListTest extends TestCase
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
        $this->getJson('/api/v1/client/shipment-requests')->assertUnauthorized();
    }

    public function test_linked_client_can_list_own_company_requests_only(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);

        $own = $this->makeOrder($companyA, [
            'request_number' => 'REQ-OWN-001',
            'pickup_phone' => 'SHOULD-NOT-LEAK',
            'pickup_address' => '99 Secret Street',
            'dispatcher_notes' => 'Internal dispatcher note',
            'payload_json' => ['cargo_type' => 'secret snapshot'],
            'submitted_by_user_id' => $client->id,
        ]);
        $this->makeOrder($companyB, ['request_number' => 'REQ-OTHER-001']);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1);

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($own->id, $ids);

        $item = $response->json('data.data.0');
        $this->assertSame('submitted', $item['status']['name']);
        $this->assertSame('shipment_order', $item['status']['type']);
        $this->assertArrayNotHasKey('customer_company_id', $item);
        $this->assertArrayNotHasKey('submitted_by_user_id', $item);
        $this->assertArrayNotHasKey('payload_json', $item);
        $this->assertArrayNotHasKey('converted_shipment_id', $item);
        $this->assertArrayNotHasKey('dispatcher_notes', $item);
        $this->assertArrayNotHasKey('pickup_phone', $item);
        $this->assertArrayNotHasKey('pickup_address', $item);
        $this->assertArrayNotHasKey('client_notes', $item);
        $this->assertStringNotContainsString('SHOULD-NOT-LEAK', $response->getContent());
        $this->assertStringNotContainsString('99 Secret Street', $response->getContent());
        $this->assertStringNotContainsString('Internal dispatcher note', $response->getContent());
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertForbidden();
    }

    public function test_admin_and_dispatcher_are_forbidden_on_client_endpoint(): void
    {
        $company = Company::factory()->create();
        $this->makeOrder($company);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertForbidden();
    }

    public function test_linked_client_with_zero_requests_gets_empty_200(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_customer_company_id_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?customer_company_id='.$company->id)
            ->assertStatus(422);
    }

    public function test_company_id_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?company_id='.$company->id)
            ->assertStatus(422);
    }

    public function test_submitted_by_user_id_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?submitted_by_user_id='.$client->id)
            ->assertStatus(422);
    }

    public function test_list_is_newest_first_and_company_scoped(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);

        $older = $this->makeOrder($companyA, [
            'request_number' => 'REQ-OLDER',
            'created_at' => now()->subDays(2),
        ]);
        $newer = $this->makeOrder($companyA, [
            'request_number' => 'REQ-NEWER',
            'created_at' => now()->subDay(),
        ]);
        $this->makeOrder($companyB, ['request_number' => 'REQ-FOREIGN']);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?page=1&per_page=50');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertSame([$newer->id, $older->id], $ids);

        $this->assertTrue(
            ShipmentOrder::query()
                ->whereIn('id', $ids)
                ->where('customer_company_id', '!=', $companyA->id)
                ->doesntExist()
        );
    }

    public function test_status_uses_shipment_order_domain_only(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $this->makeOrder($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests');

        $response->assertOk();
        $this->assertSame('shipment_order', $response->json('data.data.0.status.type'));
        $this->assertNotSame('shipment', $response->json('data.data.0.status.type'));
    }

    public function test_status_filter_returns_matching_requests_only(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $draftStatus = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $changesStatus = Status::where('name', ShipmentOrderStatus::CHANGES_REQUESTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $draft = $this->makeOrder($company, [
            'status_id' => $draftStatus->id,
            'submission_revision' => 0,
            'request_number' => 'REQ-DRAFT',
        ]);
        $this->makeOrder($company, [
            'status_id' => $this->statusSubmitted->id,
            'request_number' => 'REQ-SUBMITTED',
        ]);
        $changes = $this->makeOrder($company, [
            'status_id' => $changesStatus->id,
            'request_number' => 'REQ-CHANGES',
        ]);

        $byDraft = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?status=draft');
        $byDraft->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->assertSame([$draft->id], collect($byDraft->json('data.data'))->pluck('id')->all());

        $byChanges = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?status=changes_requested');
        $byChanges->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->assertSame([$changes->id], collect($byChanges->json('data.data'))->pluck('id')->all());
    }

    public function test_status_filter_cannot_bypass_company_scope(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $draftStatus = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $own = $this->makeOrder($companyA, [
            'status_id' => $draftStatus->id,
            'submission_revision' => 0,
            'request_number' => 'REQ-OWN-DRAFT',
        ]);
        $this->makeOrder($companyB, [
            'status_id' => $draftStatus->id,
            'submission_revision' => 0,
            'request_number' => 'REQ-FOREIGN-DRAFT',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?status=draft');

        $response->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->assertSame([$own->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_invalid_status_filter_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?status=not_a_real_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_status_and_status_id_together_return_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?status=submitted&status_id='.$this->statusSubmitted->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_search_query_is_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?search=anything')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_filtered_list_does_not_include_review_feedback(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $changesStatus = Status::where('name', ShipmentOrderStatus::CHANGES_REQUESTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->makeOrder($company, [
            'status_id' => $changesStatus->id,
            'dispatcher_notes' => 'Must not leak on list',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests?status=changes_requested');

        $response->assertOk();
        $item = $response->json('data.data.0');
        $this->assertArrayNotHasKey('review_feedback', $item);
        $this->assertArrayNotHasKey('dispatcher_notes', $item);
        $this->assertArrayNotHasKey('status_history', $item);
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
