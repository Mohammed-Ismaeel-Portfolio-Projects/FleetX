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
 * Phase 5.4c — persisted price snapshot read integration (client).
 */
class ClientShipmentRequestPriceSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Status $statusSubmitted;

    private Status $statusDraft;

    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->statusSubmitted = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusDraft = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->policy = $this->createFullPricingPolicy();
    }

    public function test_show_returns_null_price_snapshot_when_no_breakdown(): void
    {
        [$client, $order] = $this->makeOrderPair();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.price_snapshot', null);
    }

    public function test_show_returns_persisted_price_snapshot(): void
    {
        [$client, $order] = $this->makeOrderPair();
        $this->attachSnapshot($order, 1449.23);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.price_snapshot.currency', 'USD');

        $snapshot = $response->json('data.price_snapshot');
        $this->assertEqualsWithDelta(1449.23, $snapshot['total'], 0.001);
        $this->assertEqualsWithDelta(500.0, $snapshot['distance_km'], 0.001);
        $this->assertEqualsWithDelta(1125.0, $snapshot['charges']['freight'], 0.001);
    }

    public function test_show_snapshot_does_not_expose_internal_fields(): void
    {
        [$client, $order] = $this->makeOrderPair();
        $this->attachSnapshot($order);

        $content = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->getContent();

        $snapshot = json_decode($content, true)['data']['price_snapshot'];

        $this->assertArrayNotHasKey('pricing_policy', $snapshot);
        $this->assertArrayNotHasKey('applied_rules', $snapshot);
        $this->assertArrayNotHasKey('pricing_rule_resolution', $snapshot);
        $this->assertArrayNotHasKey('input', $snapshot);
        $this->assertStringNotContainsString('pricingPolicyId', $content);
        $this->assertStringNotContainsString('baseRate', $content);
        $this->assertStringNotContainsString('appliedRules', $content);
    }

    public function test_another_company_cannot_read_price_snapshot(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $foreign = $this->makeOrder($companyB);
        $this->attachSnapshot($foreign);

        $this->actingAs($clientA, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_list_returns_null_price_summary_for_orders_without_snapshot(): void
    {
        [$client, $order] = $this->makeOrderPair();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertOk()
            ->assertJsonPath('data.data.0.price_summary', null);
    }

    public function test_list_returns_price_summary_from_columns(): void
    {
        [$client, $order] = $this->makeOrderPair();
        $calculatedAt = now()->setDate(2026, 8, 19)->setTime(18, 0, 0);
        $this->attachSnapshot($order, 1449.23, $calculatedAt);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertOk()
            ->assertJsonPath('data.data.0.price_summary.currency', 'USD')
            ->assertJsonPath('data.data.0.price_summary.total', 1449.23)
            ->assertJsonPath('data.data.0.price_summary.calculated_at', $calculatedAt->format('Y-m-d H:i:s'));
    }

    public function test_pricing_engine_is_never_called_on_show(): void
    {
        [$client, $order] = $this->makeOrderPair();
        $this->attachSnapshot($order);

        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engineMock);

        $distanceMock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $distanceMock->shouldNotReceive('calculate');
        $this->app->instance(TruckRouteDistanceCalculator::class, $distanceMock);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk();
    }

    public function test_no_database_writes_on_show(): void
    {
        [$client, $order] = $this->makeOrderPair();
        $this->attachSnapshot($order);

        $before = ShipmentOrderPriceBreakdown::query()->count();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk();

        $this->assertSame($before, ShipmentOrderPriceBreakdown::query()->count());
    }

    public function test_snapshot_unaffected_by_current_pricing_configuration_changes(): void
    {
        [$client, $order] = $this->makeOrderPair();
        $this->attachSnapshot($order, 1449.23);

        $this->policy->update(['tax_rate' => 99.0]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.price_snapshot.total', 1449.23);
    }

    public function test_malformed_breakdown_json_does_not_return_500(): void
    {
        [$client, $order] = $this->makeOrderPair();

        ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $this->policy->id,
            'breakdown_json' => ['corrupt' => true],
            'final_total' => 777.77,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.price_snapshot.total', 777.77)
            ->assertJsonPath('data.price_snapshot.warnings.0', 'Price breakdown detail unavailable');
    }

    public function test_resubmit_displays_latest_persisted_snapshot_on_show(): void
    {
        $this->mockTruckRouteDistance(500.0);

        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);

        $statusChangesRequested = Status::where('name', ShipmentOrderStatus::CHANGES_REQUESTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $order = ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $this->pricingReadyOrderAttributes(), [
            'cargo_weight' => 15,
        ]));

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $firstTotal = (float) $order->fresh()->priceBreakdown->final_total;

        $order->update([
            'status_id' => $statusChangesRequested->id,
            'cargo_weight' => 25,
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk();

        $secondTotal = (float) $order->fresh()->priceBreakdown->final_total;
        $this->assertNotEquals($firstTotal, $secondTotal);

        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engineMock);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk();

        $this->assertEqualsWithDelta($secondTotal, $response->json('data.price_snapshot.total'), 0.001);
    }

    public function test_list_does_not_lazy_load_price_breakdown(): void
    {
        Model::preventLazyLoading();

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        for ($i = 0; $i < 3; $i++) {
            $order = $this->makeOrder($company, ['request_number' => 'REQ-LIST-'.$i]);
            $this->attachSnapshot($order, 1000.0 + $i);
        }

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertOk();

        $totals = collect($response->json('data.data'))
            ->pluck('price_summary.total')
            ->filter()
            ->map(fn ($total) => (float) $total)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([1000.0, 1001.0, 1002.0], $totals);
    }

    /**
     * @return array{0: User, 1: ShipmentOrder}
     */
    private function makeOrderPair(): array
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        return [$client, $order];
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
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    private function attachSnapshot(
        ShipmentOrder $order,
        float $total = 1449.23,
        ?\DateTimeInterface $calculatedAt = null,
    ): ShipmentOrderPriceBreakdown {
        return ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $this->policy->id,
            'breakdown_json' => $this->sampleBreakdownJson(['finalTotal' => $total]),
            'final_total' => $total,
            'currency' => 'USD',
            'calculated_at' => $calculatedAt ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sampleBreakdownJson(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'USD',
            'pricingPolicyId' => $this->policy->id,
            'pricingPolicyName' => $this->policy->name,
            'input' => [
                'distance' => 500.0,
                'cargoType' => 'general',
                'trailerType' => 'dry_van',
                'cargoWeight' => 15.0,
                'shipmentPriority' => 'standard',
                'customerType' => 'corporate',
                'borderCrossing' => false,
                'hazmat' => false,
                'insuranceRequired' => false,
                'selectedServices' => [],
                'loadingWaitingTime' => 0,
                'unloadingWaitingTime' => 0,
                'trafficDelay' => 0,
                'customerDelay' => 0,
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
            'finalTotal' => 1449.23,
            'serviceItems' => [['name' => 'GPS Seal', 'price' => 15.0]],
            'appliedRules' => ['base_rate:general/dry_van(exact)'],
            'warnings' => [],
            'baseRate' => 1.45,
            'distanceTierRate' => 0.80,
            'baseRateResolutionType' => 'exact',
            'taxRate' => 8.0,
            'discountType' => 'percentage',
            'discountRate' => 5.0,
        ], $overrides);
    }
}
