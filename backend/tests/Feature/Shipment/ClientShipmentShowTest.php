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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.6-C5-D3 security / contract matrix for GET /api/v1/client/shipments/{shipment}.
 */
class ClientShipmentShowTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusInTransit;

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
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/shipments/'.Str::uuid()->toString())
            ->assertUnauthorized();
    }

    public function test_linked_client_can_show_own_shipment(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'shipment_number' => 'SHP-SHOW-001',
            'reference_number' => 'REF-SHOW-001',
            'cargo_type' => 'general',
            'cargo_description' => 'Pallets of goods',
            'package_count' => 4,
            'pickup_phone' => 'SHOULD-NOT-LEAK',
            'pickup_contact' => 'Secret Contact',
            'pickup_address' => '99 Secret Street',
            'delivery_phone' => 'SHOULD-NOT-LEAK-2',
            'delivery_contact' => 'Secret Delivery',
            'delivery_address' => '100 Secret Ave',
            'notes' => 'Internal ops note',
            'special_instructions' => 'Internal instructions',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $shipment->id)
            ->assertJsonPath('data.shipment_number', 'SHP-SHOW-001')
            ->assertJsonPath('data.reference_number', 'REF-SHOW-001')
            ->assertJsonPath('data.status.name', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonPath('data.cargo_type', 'general')
            ->assertJsonPath('data.cargo_description', 'Pallets of goods')
            ->assertJsonPath('data.package_count', 4)
            ->assertJsonPath('data.customer_company.id', $company->id)
            ->assertJsonPath('data.pickup_city', 'Warsaw')
            ->assertJsonPath('data.delivery_city', 'Krakow');

        $data = $response->json('data');
        $this->assertArrayHasKey('planned_pickup_date', $data);
        $this->assertArrayHasKey('planned_delivery_date', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('company_code', $data['customer_company']);
        $this->assertArrayHasKey('company_name', $data['customer_company']);
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);

        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id)
            ->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id)
            ->assertForbidden();
    }

    public function test_dispatcher_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id)
            ->assertForbidden();
    }

    public function test_unknown_id_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_soft_deleted_shipment_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ['shipment_number' => 'SHP-DELETED']);
        $shipment->delete();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id)
            ->assertNotFound();
    }

    public function test_another_company_shipment_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeShipment($companyB, ['shipment_number' => 'SHP-FOREIGN']);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id);

        $response->assertNotFound();
        $this->assertStringNotContainsString('SHP-FOREIGN', $response->getContent());
        $this->assertStringNotContainsString($foreign->id, (string) $response->json('data'));
    }

    public function test_customer_company_id_query_is_rejected_with_422(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $shipment = $this->makeShipment($companyA);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'?customer_company_id='.$companyB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_company_id']);
    }

    public function test_company_id_query_is_rejected_with_422(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $shipment = $this->makeShipment($companyA);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'?company_id='.$companyB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_response_excludes_forbidden_fields(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'pickup_phone' => 'SHOULD-NOT-LEAK',
            'pickup_contact' => 'Secret Contact',
            'pickup_address' => '99 Secret Street',
            'delivery_phone' => 'SHOULD-NOT-LEAK-2',
            'delivery_contact' => 'Secret Delivery',
            'delivery_address' => '100 Secret Ave',
            'notes' => 'Internal ops note',
            'special_instructions' => 'Internal instructions',
            'cargo_weight' => 12.5,
            'cargo_volume' => 3.2,
            'hazardous_cargo' => true,
            'temperature_controlled' => true,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id);

        $response->assertOk();
        $item = $response->json('data');

        foreach ([
            'pickup_phone',
            'pickup_contact',
            'pickup_address',
            'delivery_phone',
            'delivery_contact',
            'delivery_address',
            'notes',
            'special_instructions',
            'active_assignment',
            'assignments',
            'tracking_logs',
            'driver',
            'driver_id',
            'creator',
            'updater',
            'created_by',
            'updated_by',
            'deleted_at',
            'cargo_weight',
            'cargo_volume',
            'hazardous_cargo',
            'temperature_controlled',
            'delivery_proof',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item, $forbidden);
        }

        $this->assertStringNotContainsString('SHOULD-NOT-LEAK', $response->getContent());
        $this->assertStringNotContainsString('99 Secret Street', $response->getContent());
        $this->assertStringNotContainsString('Internal ops note', $response->getContent());
    }

    public function test_raw_status_name_is_preserved(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status.type', 'shipment');
    }

    public function test_ops_shipment_show_still_forbidden_for_client(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipments/'.$shipment->id)
            ->assertForbidden();
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
    private function makeShipment(Company $company, array $overrides = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusInTransit->id,
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
