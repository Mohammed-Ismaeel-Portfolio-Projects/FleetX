<?php

namespace Tests\Feature\Shipment;

use App\Models\Company;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.6-C2 security / contract matrix for GET /api/v1/client/shipments.
 */
class ClientShipmentListTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusInTransit;

    private Status $statusDelivered;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->statusInTransit = Status::firstOrCreate(
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE]
        );
        $this->statusDelivered = Status::firstOrCreate(
            ['name' => ShipmentStatus::DELIVERED, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::DELIVERED, 'type' => ShipmentStatus::TYPE]
        );
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/shipments')->assertUnauthorized();
    }

    public function test_linked_client_can_list_own_company_shipments_only(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $client = $this->makeClient($companyA);

        $own = $this->makeShipment($companyA, $this->statusInTransit, [
            'shipment_number' => 'SHP-OWN-001',
            'pickup_phone' => 'SHOULD-NOT-LEAK',
            'pickup_contact' => 'Secret Contact',
            'pickup_address' => '99 Secret Street',
            'delivery_phone' => 'SHOULD-NOT-LEAK-2',
            'delivery_contact' => 'Secret Delivery',
            'delivery_address' => '100 Secret Ave',
            'notes' => 'Internal ops note',
            'special_instructions' => 'Internal instructions',
        ]);
        $other = $this->makeShipment($companyB, $this->statusDelivered, [
            'shipment_number' => 'SHP-OTHER-001',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1);

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);

        $item = $response->json('data.data.0');
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $item['status']['name']);
        $this->assertSame($companyA->id, $item['customer_company']['id']);
        $this->assertArrayNotHasKey('pickup_phone', $item);
        $this->assertArrayNotHasKey('pickup_contact', $item);
        $this->assertArrayNotHasKey('pickup_address', $item);
        $this->assertArrayNotHasKey('delivery_phone', $item);
        $this->assertArrayNotHasKey('delivery_contact', $item);
        $this->assertArrayNotHasKey('delivery_address', $item);
        $this->assertArrayNotHasKey('notes', $item);
        $this->assertArrayNotHasKey('special_instructions', $item);
        $this->assertArrayNotHasKey('active_assignment', $item);
        $this->assertArrayNotHasKey('tracking_logs', $item);
        $this->assertArrayNotHasKey('driver', $item);
        $this->assertStringNotContainsString('SHOULD-NOT-LEAK', $response->getContent());
        $this->assertStringNotContainsString('99 Secret Street', $response->getContent());
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments')
            ->assertForbidden();
    }

    public function test_admin_and_dispatcher_are_forbidden_on_client_endpoint(): void
    {
        $company = Company::factory()->create();
        $this->makeShipment($company, $this->statusInTransit);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipments')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipments')
            ->assertForbidden();
    }

    public function test_customer_company_id_query_is_rejected_with_422(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);

        $this->makeShipment($companyA, $this->statusInTransit);
        $this->makeShipment($companyB, $this->statusDelivered);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?customer_company_id='.$companyB->id);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_company_id']);
    }

    public function test_linked_client_with_zero_shipments_gets_empty_200(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_soft_deleted_shipments_are_excluded(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $active = $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'SHP-ACTIVE',
        ]);
        $deleted = $this->makeShipment($company, $this->statusDelivered, [
            'shipment_number' => 'SHP-DELETED',
        ]);
        $deleted->delete();

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
    }

    public function test_status_filter_uses_exact_raw_status_name(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $inTransit = $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'SHP-IT',
        ]);
        $this->makeShipment($company, $this->statusDelivered, [
            'shipment_number' => 'SHP-DL',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?status='.rawurlencode(ShipmentStatus::IN_TRANSIT));

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $inTransit->id)
            ->assertJsonPath('data.data.0.status.name', ShipmentStatus::IN_TRANSIT);

        // Legacy snake_case must not invent matches against canonical Title Case names
        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?status=in_transit')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_status_and_status_id_together_return_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?status='.rawurlencode(ShipmentStatus::IN_TRANSIT).'&status_id='.$this->statusInTransit->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_pagination_and_search_cannot_bypass_company_scope(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);

        $this->makeShipment($companyA, $this->statusInTransit, [
            'shipment_number' => 'SHP-SCOPE-A',
            'reference_number' => 'REF-SCOPE-A',
        ]);
        $this->makeShipment($companyB, $this->statusInTransit, [
            'shipment_number' => 'SHP-SCOPE-B',
            'reference_number' => 'REF-SCOPE-B',
        ]);

        $bySearch = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?search=SHP-SCOPE');

        $bySearch->assertOk();
        $numbers = collect($bySearch->json('data.data'))->pluck('shipment_number')->all();
        $this->assertContains('SHP-SCOPE-A', $numbers);
        $this->assertNotContains('SHP-SCOPE-B', $numbers);

        $page = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?page=1&per_page=50&sort_by=created_at&sort_direction=desc');

        $page->assertOk();
        $pageIds = collect($page->json('data.data'))->pluck('id');
        $this->assertTrue(
            Shipment::query()
                ->whereIn('id', $pageIds)
                ->where('customer_company_id', '!=', $companyA->id)
                ->doesntExist()
        );
    }

    public function test_ops_shipments_index_still_forbidden_for_client(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $this->makeShipment($company, $this->statusInTransit);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipments')
            ->assertForbidden();
    }

    public function test_priority_filter_returns_matching_shipments_only(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $high = $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'SHP-HIGH',
            'priority' => 'high',
        ]);
        $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'SHP-NORMAL',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?priority=high');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $high->id)
            ->assertJsonPath('data.data.0.priority', 'high');
    }

    public function test_search_status_and_priority_compose(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $match = $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'SHP-COMPOSE-1',
            'priority' => 'urgent',
        ]);
        $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'SHP-COMPOSE-2',
            'priority' => 'low',
        ]);
        $this->makeShipment($company, $this->statusDelivered, [
            'shipment_number' => 'SHP-COMPOSE-3',
            'priority' => 'urgent',
        ]);
        $this->makeShipment($company, $this->statusInTransit, [
            'shipment_number' => 'OTHER-URGENT',
            'priority' => 'urgent',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson(
                '/api/v1/client/shipments?search=SHP-COMPOSE&status='
                .rawurlencode(ShipmentStatus::IN_TRANSIT)
                .'&priority=urgent'
            );

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $match->id);
    }

    public function test_invalid_priority_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments?priority=critical')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
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
    private function makeShipment(Company $company, Status $status, array $overrides = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $status->id,
            'priority' => 'normal',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_company' => 'Pickup Co',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_company' => 'Delivery Co',
            'planned_pickup_date' => now()->addDay()->toDateString(),
            'planned_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }
}
