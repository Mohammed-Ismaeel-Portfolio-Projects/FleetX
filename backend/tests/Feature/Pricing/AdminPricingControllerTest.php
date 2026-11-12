<?php

namespace Tests\Feature\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\Company;
use App\Models\PricingBaseRate;
use App\Models\PricingDiscount;
use App\Models\PricingDistanceTier;
use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Models\PricingServiceCatalog;
use App\Models\PricingWaitingConfig;
use App\Models\PricingWeightConfig;
use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

class AdminPricingControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $adminRole;
    private Role $dispatcherRole;
    private Role $clientRole;
    private Role $driverRole;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->policy = $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    // ─── Authorization ───────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/v1/pricing/calculate', $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_without_pricing_calculate_returns_403(): void
    {
        $driver = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($driver, 'api')
            ->postJson('/api/v1/pricing/calculate', $this->validPayload())
            ->assertForbidden();
    }

    public function test_dispatcher_with_pricing_calculate_succeeds(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pricing_policy.source', 'active_resolved');
    }

    public function test_admin_role_alone_does_not_bypass_pricing_admin_calculate(): void
    {
        Gate::define('pricing.admin.calculate', fn () => false);

        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_type' => CustomerType::VIP->value,
            ]))
            ->assertForbidden();
    }

    // ─── Customer Type ───────────────────────────────────────────────

    public function test_customer_type_resolved_from_authenticated_users_company(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::CORPORATE->value]);
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', $this->validPayload())
            ->assertOk();

        $this->assertSame('corporate', $response->json('data.customer_type.value'));
        $this->assertSame('company', $response->json('data.customer_type.source'));
        $this->assertGreaterThan(0, $response->json('data.discount.amount'));
    }

    public function test_customer_company_id_resolves_customer_type_when_user_has_no_company(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::VIP->value]);
        $dispatcher = $this->makeDispatcher();

        $response = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk();

        $this->assertSame('vip', $response->json('data.customer_type.value'));
        $this->assertSame('company', $response->json('data.customer_type.source'));
    }

    public function test_missing_customer_type_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => null]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Company customer type is not configured. Please contact support.');
    }

    public function test_customer_type_override_works_with_pricing_admin_calculate(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'customer_type' => CustomerType::VIP->value,
            ]))
            ->assertOk();

        $this->assertSame('vip', $response->json('data.customer_type.value'));
        $this->assertSame('override', $response->json('data.customer_type.source'));
    }

    public function test_unauthorized_customer_type_override_returns_403(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'customer_type' => CustomerType::VIP->value,
            ]))
            ->assertForbidden();
    }

    // ─── Policy ──────────────────────────────────────────────────────

    public function test_active_policy_resolved_through_pricing_policy_resolver(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pricing_policy.id', $this->policy->id)
            ->assertJsonPath('data.pricing_policy.source', 'active_resolved');
    }

    public function test_valid_pricing_policy_id_override_works_with_permission(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $overridePolicy = $this->createSecondaryActivePolicy();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'pricing_policy_id' => $overridePolicy->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pricing_policy.id', $overridePolicy->id)
            ->assertJsonPath('data.pricing_policy.source', 'policy_override');
    }

    public function test_unauthorized_policy_override_returns_403(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'pricing_policy_id' => $this->policy->id,
            ]))
            ->assertForbidden();
    }

    public function test_inactive_policy_override_returns_422(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $draftPolicy = PricingPolicy::create([
            'name' => 'Draft Policy',
            'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'pricing_policy_id' => $draftPolicy->id,
            ]))
            ->assertStatus(422);
    }

    public function test_future_policy_override_returns_422(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $futurePolicy = PricingPolicy::create([
            'name' => 'Future Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
            'effective_from' => now()->addDay(),
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'pricing_policy_id' => $futurePolicy->id,
            ]))
            ->assertStatus(422);
    }

    public function test_expired_policy_override_returns_422(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $expiredPolicy = PricingPolicy::create([
            'name' => 'Expired Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
            'effective_until' => now()->subDay(),
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'pricing_policy_id' => $expiredPolicy->id,
            ]))
            ->assertStatus(422);
    }

    // ─── Distance ────────────────────────────────────────────────────

    public function test_route_distance_is_used_normally(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.distance.distance_km', 500)
            ->assertJsonPath('data.distance.distance_source', 'route')
            ->assertJsonPath('data.distance.route_distance_km', 500);
    }

    public function test_distance_km_override_requires_pricing_admin_calculate(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'distance_km' => 250,
            ]))
            ->assertOk()
            ->assertJsonPath('data.distance.distance_source', 'manual_override');
    }

    public function test_unauthorized_distance_override_returns_403(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'distance_km' => 250,
            ]))
            ->assertForbidden();
    }

    public function test_successful_manual_override_includes_correct_metadata(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'distance_km' => 250,
            ]))
            ->assertOk();

        $this->assertSame('manual_override', $response->json('data.distance.distance_source'));
        $this->assertEqualsWithDelta(250, (float) $response->json('data.distance.distance_km'), 0.001);
        $this->assertEqualsWithDelta(250, (float) $response->json('data.distance.manual_distance_km'), 0.001);
    }

    public function test_route_and_manual_distance_both_returned_when_route_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'distance_km' => 250,
            ]))
            ->assertOk();

        $this->assertEqualsWithDelta(500, (float) $response->json('data.distance.route_distance_km'), 0.001);
        $this->assertEqualsWithDelta(250, (float) $response->json('data.distance.manual_distance_km'), 0.001);
    }

    public function test_routing_failure_without_manual_override_returns_503(): void
    {
        $this->mockRoutingFailure();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertStatus(503);
    }

    public function test_routing_failure_with_authorized_manual_override_succeeds(): void
    {
        $this->mockRoutingFailure();
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'distance_km' => 180,
            ]))
            ->assertOk()
            ->assertJsonPath('data.distance.distance_km', 180);
    }

    public function test_manual_override_metadata_identifies_routing_failure(): void
    {
        $this->mockRoutingFailure();
        $admin = $this->makeAdmin();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'distance_km' => 180,
            ]))
            ->assertOk()
            ->assertJsonPath('data.distance.distance_source', 'manual_override_due_to_routing_failure')
            ->assertJsonPath('data.distance.manual_distance_km', 180);
    }

    // ─── Services ──────────────────────────────────────────────────────

    public function test_valid_service_ids_resolve_through_catalog(): void
    {
        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'Liftgate',
            'price' => 75.00,
            'enabled' => true,
        ]);

        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $response = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'selected_service_ids' => [$service->id],
            ]))
            ->assertOk();

        $this->assertNotEmpty($response->json('data.service_items'));
        $this->assertEqualsWithDelta(75.0, (float) $response->json('data.service_items.0.price'), 0.01);
    }

    public function test_unknown_service_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'selected_service_ids' => [99999],
            ]))
            ->assertStatus(422);
    }

    public function test_disabled_service_returns_422(): void
    {
        $disabled = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'Disabled',
            'price' => 50.00,
            'enabled' => false,
        ]);

        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'selected_service_ids' => [$disabled->id],
            ]))
            ->assertStatus(422);
    }

    public function test_request_cannot_inject_service_prices(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                'service_price' => 0.01,
            ]))
            ->assertStatus(422);
    }

    public function test_tax_rate_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('tax_rate', 0.01);
    }

    public function test_discount_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('discount', 100);
    }

    public function test_discount_amount_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('discount_amount', 100);
    }

    public function test_minimum_fare_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('minimum_fare', 1);
    }

    public function test_priority_multiplier_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('priority_multiplier', 0.5);
    }

    public function test_service_price_field_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('service_price', 0.01);
    }

    public function test_base_rate_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('base_rate', 0.01);
    }

    public function test_distance_tier_rate_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('distance_tier_rate', 0.01);
    }

    public function test_final_total_is_rejected(): void
    {
        $this->assertForbiddenPricingFieldRejected('final_total', 1);
    }

    private function assertForbiddenPricingFieldRejected(string $field, mixed $value): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
                $field => $value,
            ]))
            ->assertStatus(422);
    }

    // ─── Pricing ─────────────────────────────────────────────────────

    public function test_pricing_engine_is_the_calculation_source(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();
        $realEngine = $this->app->make(PricingEngine::class);

        $mockEngine = Mockery::mock(PricingEngine::class);
        $mockEngine->shouldReceive('calculate')->once()->andReturnUsing(
            fn ($input, $policy) => $realEngine->calculate($input, $policy)
        );
        $this->app->instance(PricingEngine::class, $mockEngine);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk();
    }

    public function test_response_contains_correct_totals_and_charges(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $response = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk();

        $this->assertSame('USD', $response->json('data.currency'));
        $this->assertGreaterThan(0, $response->json('data.total'));
        $this->assertGreaterThan(0, $response->json('data.charges.freight'));
        $this->assertIsArray($response->json('data.applied_rules'));
        $this->assertArrayHasKey('base_rate', $response->json('data.pricing_rule_resolution'));
    }

    // ─── Persistence ─────────────────────────────────────────────────

    public function test_no_shipment_order_created(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();
        $before = ShipmentOrder::query()->count();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk();

        $this->assertSame($before, ShipmentOrder::query()->count());
    }

    public function test_no_shipment_order_price_breakdown_created(): void
    {
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();
        $before = ShipmentOrderPriceBreakdown::query()->count();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertOk();

        $this->assertSame($before, ShipmentOrderPriceBreakdown::query()->count());
    }

    public function test_no_active_pricing_policy_returns_503(): void
    {
        PricingPolicy::query()->delete();
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/pricing/calculate', array_merge($this->validPayload(), [
                'customer_company_id' => $company->id,
            ]))
            ->assertStatus(503);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role_id' => $this->adminRole->id,
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

    private function mockRoutingFailure(): void
    {
        $mock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $mock->shouldReceive('calculate')
            ->andThrow(new RoutingProviderUnavailableException('GraphHopper unavailable'));
        $this->app->instance(TruckRouteDistanceCalculator::class, $mock);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'pickup_latitude' => 33.8869,
            'pickup_longitude' => 35.5131,
            'delivery_latitude' => 36.2021,
            'delivery_longitude' => 37.1343,
            'cargo_type' => CargoType::GENERAL->value,
            'trailer_type' => TrailerType::DRY_VAN->value,
            'cargo_weight' => 15.0,
            'shipment_priority' => 'standard',
        ];
    }

    private function createSecondaryActivePolicy(): PricingPolicy
    {
        $policy = PricingPolicy::create([
            'name' => 'Secondary Active Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0000,
            'tax_inclusive' => false,
            'priority' => 999,
        ]);

        PricingBaseRate::create([
            'pricing_policy_id' => $policy->id,
            'cargo_type' => CargoType::GENERAL,
            'trailer_type' => TrailerType::DRY_VAN,
            'rate_per_km' => 2.00,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => null,
            'rate_per_km' => 1.00,
        ]);
        PricingWeightConfig::create([
            'pricing_policy_id' => $policy->id,
            'threshold_tons' => 20,
            'charge_per_ton' => 50,
        ]);
        PricingWaitingConfig::create([
            'pricing_policy_id' => $policy->id,
            'loading_free_minutes' => 30,
            'unloading_free_minutes' => 30,
            'rate_per_15min' => 15,
            'max_charge' => 200,
            'enabled' => true,
        ]);
        PricingFee::create([
            'pricing_policy_id' => $policy->id,
            'code' => 'border',
            'name' => 'Border Crossing',
            'fee_type' => 'flat',
            'amount' => 150,
            'enabled' => true,
        ]);
        PricingFee::create([
            'pricing_policy_id' => $policy->id,
            'code' => 'insurance',
            'name' => 'Insurance',
            'fee_type' => 'percentage',
            'amount' => 2,
            'enabled' => true,
        ]);
        PricingFee::create([
            'pricing_policy_id' => $policy->id,
            'code' => 'hazmat',
            'name' => 'Hazmat',
            'fee_type' => 'percentage',
            'amount' => 25,
            'enabled' => true,
        ]);
        PricingFee::create([
            'pricing_policy_id' => $policy->id,
            'code' => 'heavy_reefer',
            'name' => 'Heavy Reefer',
            'fee_type' => 'percentage',
            'amount' => 6,
            'enabled' => true,
        ]);
        foreach (['standard' => 1.0, 'express' => 1.1, 'urgent' => 1.2, 'emergency' => 1.3] as $level => $mult) {
            PricingPriorityMultiplier::create([
                'pricing_policy_id' => $policy->id,
                'priority_level' => $level,
                'multiplier' => $mult,
            ]);
        }
        PricingDiscount::create([
            'pricing_policy_id' => $policy->id,
            'customer_type' => 'corporate',
            'discount_type' => 'percentage',
            'amount' => 5,
            'enabled' => true,
        ]);

        return $policy;
    }
}
