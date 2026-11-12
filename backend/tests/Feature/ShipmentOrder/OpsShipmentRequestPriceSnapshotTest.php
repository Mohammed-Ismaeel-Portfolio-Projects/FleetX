<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\PricingPolicy;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\Status;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

/**
 * Phase 5.4c — persisted price snapshot read integration (ops).
 */
class OpsShipmentRequestPriceSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $dispatcherRole;

    private Role $adminRole;

    private Status $statusSubmitted;

    private Status $statusDraft;

    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->statusSubmitted = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusDraft = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->policy = $this->createFullPricingPolicy();
    }

    public function test_ops_show_returns_null_price_snapshot_when_no_breakdown(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrder(Company::factory()->create());

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.price_snapshot', null);
    }

    public function test_ops_show_returns_full_persisted_snapshot(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrder(Company::factory()->create());
        $this->attachSnapshot($order);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.price_snapshot.pricing_policy.id', $this->policy->id)
            ->assertJsonPath('data.price_snapshot.pricing_policy.source', 'active_at_submission')
            ->assertJsonPath('data.price_snapshot.customer_type.value', 'corporate')
            ->assertJsonPath('data.price_snapshot.customer_type.source', 'company_at_submission')
            ->assertJsonPath('data.price_snapshot.distance.distance_source', 'route_at_submission')
            ->assertJsonPath('data.price_snapshot.total', 1449.23);
    }

    public function test_ops_show_exposes_applied_rules_and_resolution(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrder(Company::factory()->create());
        $this->attachSnapshot($order);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();

        $snapshot = $response->json('data.price_snapshot');
        $this->assertNotEmpty($snapshot['applied_rules']);
        $this->assertArrayHasKey('base_rate', $snapshot['pricing_rule_resolution']);
        $this->assertArrayNotHasKey('input', $snapshot);
    }

    public function test_admin_can_view_snapshot_across_companies(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeOrder(Company::factory()->create(['company_name' => 'Foreign Co']));
        $this->attachSnapshot($order, 2000.00);

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();

        $this->assertEqualsWithDelta(2000.0, $response->json('data.price_snapshot.total'), 0.001);
    }

    public function test_ops_list_includes_price_summary_across_companies(): void
    {
        $dispatcher = $this->makeDispatcher();

        $orderA = $this->makeOrder(Company::factory()->create(), ['request_number' => 'REQ-A']);
        $orderB = $this->makeOrder(Company::factory()->create(), ['request_number' => 'REQ-B']);
        $this->attachSnapshot($orderA, 1111.11);
        $this->attachSnapshot($orderB, 2222.22);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();

        $totals = collect($response->json('data.data'))->pluck('price_summary.total')->filter()->values()->all();
        $this->assertContains(1111.11, $totals);
        $this->assertContains(2222.22, $totals);
    }

    public function test_ops_list_returns_null_summary_for_draft_without_snapshot(): void
    {
        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();

        ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
            'request_number' => 'REQ-DRAFT',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ]);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();

        $item = collect($response->json('data.data'))
            ->firstWhere('request_number', 'REQ-DRAFT');

        $this->assertNotNull($item);
        $this->assertNull($item['price_summary']);
    }

    public function test_pricing_engine_is_never_called_on_ops_show(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrder(Company::factory()->create());
        $this->attachSnapshot($order);

        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engineMock);

        $distanceMock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $distanceMock->shouldNotReceive('calculate');
        $this->app->instance(TruckRouteDistanceCalculator::class, $distanceMock);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();
    }

    public function test_no_database_writes_on_ops_show(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrder(Company::factory()->create());
        $this->attachSnapshot($order);

        $before = ShipmentOrderPriceBreakdown::query()->count();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk();

        $this->assertSame($before, ShipmentOrderPriceBreakdown::query()->count());
    }

    public function test_ops_malformed_json_returns_degraded_snapshot_not_500(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrder(Company::factory()->create());

        ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $this->policy->id,
            'breakdown_json' => ['bad' => 'data'],
            'final_total' => 888.88,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.price_snapshot.detail_status', 'degraded')
            ->assertJsonPath('data.price_snapshot.total', 888.88);
    }

    public function test_ops_list_does_not_lazy_load_price_breakdown(): void
    {
        Model::preventLazyLoading();

        $dispatcher = $this->makeDispatcher();
        $company = Company::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $order = $this->makeOrder($company, ['request_number' => 'REQ-OPS-'.$i]);
            $this->attachSnapshot($order, 1500.0 + $i);
        }

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests')
            ->assertOk();
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
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
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    private function attachSnapshot(ShipmentOrder $order, float $total = 1449.23): ShipmentOrderPriceBreakdown
    {
        return ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $this->policy->id,
            'breakdown_json' => [
                'currency' => 'USD',
                'pricingPolicyId' => $this->policy->id,
                'pricingPolicyName' => $this->policy->name,
                'input' => [
                    'distance' => 500.0,
                    'customerType' => 'corporate',
                ],
                'freightCharge' => 1125.0,
                'weightSurcharge' => 0.0,
                'waitingCharge' => 0.0,
                'serviceCharges' => 265.0,
                'borderFee' => 0.0,
                'insuranceFee' => 22.5,
                'hazmatSurcharge' => 0.0,
                'heavyReeferSurcharge' => 0.0,
                'prioritySurcharge' => 0.0,
                'subtotal' => 1412.5,
                'discount' => 70.625,
                'afterDiscount' => 1341.875,
                'minimumFareAdjustment' => 0.0,
                'afterMinimumFare' => 1341.875,
                'tax' => 107.35,
                'finalTotal' => $total,
                'serviceItems' => [],
                'appliedRules' => ['base_rate:general/dry_van(exact)'],
                'warnings' => [],
                'baseRate' => 1.45,
                'distanceTierRate' => 0.80,
                'baseRateResolutionType' => 'exact',
                'taxRate' => 8.0,
                'discountType' => 'percentage',
                'discountRate' => 5.0,
            ],
            'final_total' => $total,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);
    }
}
