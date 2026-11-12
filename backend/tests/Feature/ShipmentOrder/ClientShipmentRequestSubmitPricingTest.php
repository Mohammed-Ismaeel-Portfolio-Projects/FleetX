<?php

namespace Tests\Feature\ShipmentOrder;

use App\Enums\CustomerType;
use App\Models\Company;
use App\Models\PricingPolicy;
use App\Models\PricingServiceCatalog;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\Status;
use App\Models\User;
use App\Services\Pricing\Exceptions\ShipmentOrderPricingException;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\ShipmentOrderPricingService;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

/**
 * Phase 5.3 — ShipmentOrder submission pricing integration.
 */
class ClientShipmentRequestSubmitPricingTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Status $statusDraft;

    private Status $statusChangesRequested;

    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->statusDraft = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusChangesRequested = Status::where('name', ShipmentOrderStatus::CHANGES_REQUESTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->policy = $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    public function test_successful_submission_creates_price_snapshot(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->assertDatabaseCount('shipment_order_price_breakdowns', 1);
        $this->assertNotNull($order->fresh()->priceBreakdown);
    }

    public function test_snapshot_contains_pricing_policy_id(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $snapshot = $order->fresh()->priceBreakdown;
        $this->assertSame($this->policy->id, $snapshot->pricing_policy_id);
    }

    public function test_snapshot_contains_complete_breakdown_json(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $json = $order->fresh()->priceBreakdown->breakdown_json;
        $this->assertIsArray($json);
        $this->assertArrayHasKey('freightCharge', $json);
        $this->assertArrayHasKey('finalTotal', $json);
        $this->assertArrayHasKey('input', $json);
        $this->assertArrayHasKey('serviceItems', $json);
        $this->assertArrayHasKey('appliedRules', $json);
        $this->assertArrayHasKey('taxRate', $json);
    }

    public function test_snapshot_final_total_matches_pricing_breakdown(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $snapshot = $order->fresh()->priceBreakdown;
        $this->assertEqualsWithDelta(
            $snapshot->breakdown_json['finalTotal'],
            (float) $snapshot->final_total,
            0.01
        );
    }

    public function test_snapshot_currency_matches_pricing_breakdown(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $snapshot = $order->fresh()->priceBreakdown;
        $this->assertSame($snapshot->breakdown_json['currency'], $snapshot->currency);
        $this->assertSame('USD', $snapshot->currency);
    }

    public function test_snapshot_calculated_at_is_populated(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->assertNotNull($order->fresh()->priceBreakdown->calculated_at);
    }

    public function test_customer_type_resolved_from_company_not_request(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::CORPORATE->value]);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'customer_type' => 'vip',
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $input = $order->fresh()->priceBreakdown->breakdown_json['input'];
        $this->assertSame('corporate', $input['customerType']);
        $this->assertGreaterThan(0, $order->fresh()->priceBreakdown->breakdown_json['discount']);
    }

    public function test_distance_is_server_calculated_not_client_controlled(): void
    {
        $this->mockTruckRouteDistance(500.0);
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'distance' => 1,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->assertEqualsWithDelta(
            500.0,
            (float) $order->fresh()->priceBreakdown->breakdown_json['input']['distance'],
            0.001
        );
    }

    public function test_service_prices_are_not_accepted_from_client_on_submit(): void
    {
        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'Liftgate',
            'price' => 75.00,
            'enabled' => true,
        ]);

        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'selected_service_ids' => [$service->id],
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'service_price' => 0.01,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $items = $order->fresh()->priceBreakdown->breakdown_json['serviceItems'];
        $this->assertNotEmpty($items);
        $this->assertEqualsWithDelta(75.0, $items[0]['price'], 0.01);
    }

    public function test_customer_cannot_select_pricing_policy_id_on_submit(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'pricing_policy_id' => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_missing_customer_type_prevents_submission(): void
    {
        $company = Company::factory()->create(['customer_type' => null]);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Company customer type is not configured. Please contact support.');

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
        $this->assertDatabaseCount('shipment_order_price_breakdowns', 0);
    }

    public function test_routing_failure_prevents_submission(): void
    {
        $mock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $mock->shouldReceive('calculate')
            ->andThrow(new RoutingProviderUnavailableException('GraphHopper unavailable'));
        $this->app->instance(TruckRouteDistanceCalculator::class, $mock);

        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(503);

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
        $this->assertDatabaseCount('shipment_order_price_breakdowns', 0);
    }

    public function test_missing_active_pricing_policy_prevents_submission(): void
    {
        PricingPolicy::query()->delete();

        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(503);

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
        $this->assertDatabaseCount('shipment_order_price_breakdowns', 0);
    }

    public function test_invalid_disabled_service_prevents_submission(): void
    {
        $disabled = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'Disabled Service',
            'price' => 50.00,
            'enabled' => false,
        ]);

        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'selected_service_ids' => [$disabled->id],
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
    }

    public function test_pricing_failure_prevents_shipment_order_submission(): void
    {
        [$client, $order] = $this->makeSubmittablePair([
            'cargo_type' => 'invalid_type',
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
        $this->assertDatabaseCount('shipment_order_price_breakdowns', 0);
    }

    public function test_snapshot_persistence_failure_rolls_back_submission(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $pricingMock = Mockery::mock(ShipmentOrderPricingService::class);
        $pricingMock->shouldReceive('calculateAndPersistSnapshot')
            ->once()
            ->andThrow(new ShipmentOrderPricingException('Snapshot persistence failed', 500));
        $this->app->instance(ShipmentOrderPricingService::class, $pricingMock);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(500);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->status->name);
        $this->assertSame(0, $order->submission_revision);
        $this->assertDatabaseCount('shipment_order_price_breakdowns', 0);
    }

    public function test_existing_authorization_unchanged_for_submit(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeSubmittableDraft($company);
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertForbidden();
    }

    public function test_existing_status_transition_behavior_unchanged(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'submitted')
            ->assertJsonPath('data.submission_revision', 1);
    }

    public function test_price_snapshot_not_overwritten_by_ordinary_update(): void
    {
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $originalSnapshot = $order->fresh()->priceBreakdown;
        $originalTotal = $originalSnapshot->final_total;
        $originalCalculatedAt = $originalSnapshot->calculated_at;

        $statusChangesRequested = $this->statusChangesRequested;
        $order->update(['status_id' => $statusChangesRequested->id]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_weight' => 999,
            ])
            ->assertOk();

        $snapshot = $order->fresh()->priceBreakdown;
        $this->assertSame((string) $originalTotal, (string) $snapshot->final_total);
        $this->assertSame(
            $originalCalculatedAt->toDateTimeString(),
            $snapshot->calculated_at->toDateTimeString()
        );
    }

    public function test_resubmit_recalculates_and_updates_existing_snapshot(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $firstSnapshot = $order->fresh()->priceBreakdown;
        $firstTotal = (float) $firstSnapshot->final_total;

        $order->update([
            'status_id' => $this->statusChangesRequested->id,
            'cargo_weight' => 25,
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('data.submission_revision', 2);

        $updatedSnapshot = $order->fresh()->priceBreakdown;
        $this->assertSame($firstSnapshot->id, $updatedSnapshot->id);
        $this->assertNotEquals($firstTotal, (float) $updatedSnapshot->final_total);
        $this->assertEqualsWithDelta(
            25.0,
            (float) $updatedSnapshot->breakdown_json['input']['cargoWeight'],
            0.001
        );
    }

    public function test_fresh_calculation_at_submission_not_estimate_reuse(): void
    {
        $this->mockTruckRouteDistance(100.0);
        [$client, $order] = $this->makeSubmittablePair();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/pricing/calculate', [
                'pickup_latitude' => 52.2297,
                'pickup_longitude' => 21.0122,
                'delivery_latitude' => 50.0647,
                'delivery_longitude' => 19.9450,
                'cargo_type' => 'general',
                'trailer_type' => 'dry_van',
                'cargo_weight' => 15,
                'shipment_priority' => 'standard',
            ])
            ->assertOk();

        $this->mockTruckRouteDistance(500.0);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->assertEqualsWithDelta(
            500.0,
            (float) $order->fresh()->priceBreakdown->breakdown_json['input']['distance'],
            0.001
        );
    }

    /**
     * @param  array<string, mixed>  $orderOverrides
     * @return array{0: User, 1: ShipmentOrder}
     */
    private function makeSubmittablePair(array $orderOverrides = []): array
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, $orderOverrides);

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
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $this->pricingReadyOrderAttributes(), $overrides));
    }
}
