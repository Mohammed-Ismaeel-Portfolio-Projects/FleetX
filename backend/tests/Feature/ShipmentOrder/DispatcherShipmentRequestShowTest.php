<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\ShipmentOrder\TransitionShipmentOrderStatusAction;
use App\Models\Company;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
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
 * C2.9-B2.1 security / contract matrix for GET /api/v1/shipment-requests/{id}.
 */
class DispatcherShipmentRequestShowTest extends TestCase
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
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);

        $this->getJson('/api/v1/shipment-requests/'.$order->id)->assertUnauthorized();
    }

    public function test_dispatcher_can_show_shipment_request(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create(['company_name' => 'Acme Logistics']);
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'submitted_by_user_id' => $client->id,
            'payload_json' => ['cargo_type' => 'Electronics', 'submission_revision' => 1],
            'dispatcher_notes' => 'Review pending',
            'priority' => 'high',
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_number', $order->request_number)
            ->assertJsonPath('data.customer_company.company_name', 'Acme Logistics')
            ->assertJsonPath('data.submitted_by.email', $client->email)
            ->assertJsonPath('data.submission_snapshot.cargo_type', 'Electronics')
            ->assertJsonPath('data.dispatcher_notes', 'Review pending')
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_admin_can_show_shipment_request(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();
    }

    public function test_client_is_forbidden_on_dispatcher_show(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertForbidden();
    }

    public function test_unlinked_client_is_forbidden_on_dispatcher_show(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertForbidden();
    }

    public function test_unknown_request_returns_404(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_dispatcher_can_show_foreign_company_request(): void
    {
        $dispatcher = $this->makeDispatcher();
        $foreignCompany = Company::factory()->create();
        $order = $this->makeOrder($foreignCompany);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_status_history_is_included_and_read_only(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithHistory($company, $client, $dispatcher);

        $historyCountBefore = ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();

        $history = $response->json('data.status_history');
        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(2, count($history));
        $this->assertSame('draft', $history[0]['from_status']['name']);
        $this->assertSame('submitted', $history[0]['to_status']['name']);
        $this->assertSame('Need weight', collect($history)->firstWhere('to_status.name', 'changes_requested')['reason']);

        $this->assertSame(
            $historyCountBefore,
            ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count()
        );
    }

    public function test_ownership_probe_parameters_return_422(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();
        $order = $this->makeOrder($company);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id.'?customer_company_id='.$company->id)
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id.'?submitted_by_user_id=999')
            ->assertStatus(422);
    }

    public function test_client_show_still_hides_payload_json(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'payload_json' => ['cargo_type' => 'secret'],
            'submitted_by_user_id' => $client->id,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk();

        $data = $response->json('data');
        $this->assertArrayNotHasKey('payload_json', $data);
        $this->assertArrayNotHasKey('submission_snapshot', $data);
        $this->assertArrayNotHasKey('dispatcher_notes', $data);
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
        $submitted = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $submitted->id,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Test shipment request',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $overrides));
    }

    private function makeOrderWithHistory(Company $company, User $client, User $dispatcher): ShipmentOrder
    {
        $transition = app(TransitionShipmentOrderStatusAction::class);

        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => Status::where('name', ShipmentOrderStatus::DRAFT)->where('type', ShipmentOrderStatus::TYPE)->firstOrFail()->id,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Test',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ]);

        $order = $transition->execute($order, ShipmentOrderStatus::SUBMITTED, $client->id);
        $order = $transition->execute($order, ShipmentOrderStatus::UNDER_REVIEW, $dispatcher->id);
        $order = $transition->execute($order, ShipmentOrderStatus::CHANGES_REQUESTED, $dispatcher->id, 'Need weight');

        return $order->fresh();
    }
}
