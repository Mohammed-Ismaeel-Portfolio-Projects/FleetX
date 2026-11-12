<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

/**
 * INT-016 / INT-028 — operational golden path through existing client + dispatcher APIs.
 */
class DispatcherShipmentRequestGoldenPathTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    public function test_operational_golden_path_create_submit_review_changes_resubmit_approve_convert(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $observed = [];

        $create = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.status.name', 'draft');
        $orderId = $create->json('data.id');
        $observed[] = $create->json('data.status.name');

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$orderId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'submitted');
        $observed[] = 'submitted';

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$orderId.'/start-review')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'under_review');
        $observed[] = 'under_review';

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$orderId.'/request-changes', [
                'reason' => 'Please confirm cargo weight',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'changes_requested');
        $observed[] = 'changes_requested';

        $resubmit = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$orderId.'/resubmit')
            ->assertOk();
        $resubmitStatus = $resubmit->json('data.status.name');
        $this->assertSame('submitted', $resubmitStatus);
        $observed[] = $resubmitStatus;

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$orderId.'/start-review')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'under_review');
        $observed[] = 'under_review';

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$orderId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'approved');
        $observed[] = 'approved';

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$orderId.'/convert')
            ->assertCreated()
            ->assertJsonPath('data.status.name', 'converted');
        $observed[] = 'converted';

        $this->assertSame([
            'draft',
            'submitted',
            'under_review',
            'changes_requested',
            'submitted',
            'under_review',
            'approved',
            'converted',
        ], $observed);

        $order = ShipmentOrder::query()->findOrFail($orderId);
        $this->assertSame(ShipmentOrderStatus::CONVERTED, $order->status->name);
        $this->assertNotNull($order->converted_shipment_id);
        $this->assertSame(1, Shipment::query()->where('shipment_order_id', $orderId)->count());
    }

    public function test_invalid_http_transitions_return_422(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = $this->makeCompany();

        $draft = $this->makeOrder(ShipmentOrderStatus::DRAFT, $company);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$draft->id.'/approve')
            ->assertStatus(422);

        $submitted = $this->makeOrder(ShipmentOrderStatus::SUBMITTED, $company);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/approve')
            ->assertStatus(422);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/convert')
            ->assertStatus(422);

        $underReview = $this->makeOrder(ShipmentOrderStatus::UNDER_REVIEW, $company);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/start-review')
            ->assertStatus(422);

        $rejected = $this->makeOrder(ShipmentOrderStatus::REJECTED, $company);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$rejected->id.'/approve')
            ->assertStatus(422);

        $converted = $this->makeOrder(ShipmentOrderStatus::CONVERTED, $company);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$converted->id.'/approve')
            ->assertStatus(422);
        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$converted->id.'/convert')
            ->assertStatus(422);
    }

    public function test_client_cannot_perform_dispatcher_workflow_actions(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $submitted = $this->makeOrder(ShipmentOrderStatus::SUBMITTED, $company);
        $underReview = $this->makeOrder(ShipmentOrderStatus::UNDER_REVIEW, $company);
        $approved = $this->makeOrder(ShipmentOrderStatus::APPROVED, $company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/start-review')
            ->assertForbidden();
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/request-changes', [
                'reason' => 'Client must not request changes this way',
            ])
            ->assertForbidden();
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/approve')
            ->assertForbidden();
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/reject', [
                'reason' => 'Client must not reject',
            ])
            ->assertForbidden();
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$approved->id.'/convert')
            ->assertForbidden();
    }

    public function test_driver_cannot_start_review_or_request_changes(): void
    {
        $driver = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);
        $company = $this->makeCompany();
        $submitted = $this->makeOrder(ShipmentOrderStatus::SUBMITTED, $company);
        $underReview = $this->makeOrder(ShipmentOrderStatus::UNDER_REVIEW, $company);
        $approved = $this->makeOrder(ShipmentOrderStatus::APPROVED, $company);

        $this->actingAs($driver, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/start-review')
            ->assertForbidden();
        $this->actingAs($driver, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/request-changes', [
                'reason' => 'Driver must not request changes',
            ])
            ->assertForbidden();
        $this->actingAs($driver, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/approve')
            ->assertForbidden();
        $this->actingAs($driver, 'api')
            ->postJson('/api/v1/shipment-requests/'.$underReview->id.'/reject', [
                'reason' => 'Driver must not reject',
            ])
            ->assertForbidden();
        $this->actingAs($driver, 'api')
            ->postJson('/api/v1/shipment-requests/'.$approved->id.'/convert')
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

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCompany(): Company
    {
        return Company::factory()->create([
            'created_by' => null,
            'updated_by' => null,
        ]);
    }

    private function makeOrder(string $statusName, ?Company $company = null): ShipmentOrder
    {
        $company ??= $this->makeCompany();

        return ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => Status::where('name', $statusName)
                ->where('type', ShipmentOrderStatus::TYPE)
                ->firstOrFail()
                ->id,
            'converted_shipment_id' => null,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Golden path fixture',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'reference_number' => 'REF-GOLDEN-001',
            'cargo_type' => 'general',
            'trailer_type' => 'dry_van',
            'cargo_weight' => 15,
            'cargo_description' => 'Office supplies',
            'package_count' => 3,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'pickup_latitude' => 52.2297,
            'pickup_longitude' => 21.0122,
            'delivery_latitude' => 50.0647,
            'delivery_longitude' => 19.9450,
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ];
    }
}
