<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

/**
 * Client request pickup/delivery coordinate contract.
 */
class ClientShipmentRequestCoordinatesTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Status $statusDraft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->statusDraft = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    public function test_dispatcher_show_returns_persisted_coordinates(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $dispatcher = \App\Models\User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $id = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'pickup_city' => 'Warsaw',
                'pickup_country' => 'PL',
                'pickup_latitude' => 52.2297,
                'pickup_longitude' => 21.0122,
                'delivery_city' => 'Krakow',
                'delivery_country' => 'PL',
                'delivery_latitude' => 50.0647,
                'delivery_longitude' => 19.9450,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.pickup_latitude', 52.2297)
            ->assertJsonPath('data.delivery_longitude', 19.9450);
    }

    public function test_create_persists_and_returns_coordinates(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'cargo_type' => 'general',
                'pickup_city' => 'Warsaw',
                'pickup_country' => 'PL',
                'pickup_latitude' => 52.2297,
                'pickup_longitude' => 21.0122,
                'delivery_city' => 'Krakow',
                'delivery_country' => 'PL',
                'delivery_latitude' => 50.0647,
                'delivery_longitude' => 19.9450,
            ])
            ->assertCreated()
            ->assertJsonPath('data.pickup_latitude', 52.2297)
            ->assertJsonPath('data.pickup_longitude', 21.0122)
            ->assertJsonPath('data.delivery_latitude', 50.0647)
            ->assertJsonPath('data.delivery_longitude', 19.9450);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('shipment_orders', [
            'id' => $id,
            'pickup_latitude' => 52.2297,
            'pickup_longitude' => 21.0122,
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.pickup_latitude', 52.2297)
            ->assertJsonPath('data.delivery_longitude', 19.9450);
    }

    public function test_draft_may_omit_coordinates(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'pickup_city' => 'Warsaw',
            ])
            ->assertCreated()
            ->assertJsonPath('data.pickup_latitude', null)
            ->assertJsonPath('data.delivery_longitude', null);
    }

    public function test_create_rejects_out_of_range_latitude(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'pickup_latitude' => 91,
                'pickup_longitude' => 21.0122,
            ])
            ->assertStatus(422);
    }

    public function test_create_rejects_longitude_without_latitude(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', [
                'pickup_longitude' => 21.0122,
            ])
            ->assertStatus(422);
    }

    public function test_update_draft_coordinates(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'pickup_latitude' => 33.8938,
                'pickup_longitude' => 35.5018,
                'delivery_latitude' => 33.5138,
                'delivery_longitude' => 36.2765,
            ])
            ->assertOk()
            ->assertJsonPath('data.pickup_latitude', 33.8938)
            ->assertJsonPath('data.delivery_longitude', 36.2765);
    }

    public function test_submit_without_pickup_coordinates_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'pickup_latitude' => null,
            'pickup_longitude' => null,
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
    }

    public function test_submit_without_delivery_coordinates_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'delivery_latitude' => null,
            'delivery_longitude' => null,
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $this->assertSame(0, $order->fresh()->submission_revision);
    }

    public function test_submit_with_coordinates_snapshots_them(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $order->refresh();
        $this->assertEqualsWithDelta(52.2297, (float) $order->payload_json['pickup_latitude'], 0.0001);
        $this->assertEqualsWithDelta(19.9450, (float) $order->payload_json['delivery_longitude'], 0.0001);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSubmittableDraft(Company $company, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
            'payload_json' => null,
            'submitted_by_user_id' => null,
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $this->pricingReadyOrderAttributes(), $overrides));
    }

    private function makeClient(Company $company)
    {
        return \App\Models\User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }
}
