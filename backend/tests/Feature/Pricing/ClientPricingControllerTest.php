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
use App\Models\User;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ClientPricingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;
    private Role $adminRole;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->policy = $this->createFullPolicy();

        $this->mockDistanceCalculator(500.0);
    }

    // ─── Authorization ───────────────────────────────────────────────

    public function test_unauthenticated_request_rejected(): void
    {
        $this->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_non_client_role_forbidden(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertForbidden();
    }

    public function test_authorized_client_succeeds(): void
    {
        $user = $this->makeClient(CustomerType::STANDARD);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.distance_km', 500);
    }

    // ─── Customer Type ───────────────────────────────────────────────

    public function test_customer_type_from_company(): void
    {
        $user = $this->makeClient(CustomerType::CORPORATE);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertOk();

        $this->assertGreaterThan(0, $response->json('data.discount.amount'));
    }

    public function test_client_cannot_spoof_customer_type(): void
    {
        $user = $this->makeClient(CustomerType::STANDARD);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'customer_type' => 'vip',
            ]))
            ->assertStatus(422);
    }

    public function test_company_without_customer_type_fails(): void
    {
        $company = Company::factory()->create(['customer_type' => null]);
        $user = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── Distance ────────────────────────────────────────────────────

    public function test_distance_calculated_server_side(): void
    {
        $this->mockDistanceCalculator(250.0);
        $user = $this->makeClient(CustomerType::STANDARD);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertOk();

        $this->assertEqualsWithDelta(250.0, $response->json('data.distance_km'), 0.01);
    }

    public function test_client_cannot_inject_distance(): void
    {
        $this->mockDistanceCalculator(250.0);
        $user = $this->makeClient(CustomerType::STANDARD);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'distance' => 1,
            ]))
            ->assertStatus(422);
    }

    public function test_routing_failure_not_silently_zero(): void
    {
        $mock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $mock->shouldReceive('calculate')->andThrow(
            new RoutingProviderUnavailableException('GraphHopper unavailable')
        );
        $this->app->instance(TruckRouteDistanceCalculator::class, $mock);

        $user = $this->makeClient(CustomerType::STANDARD);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertStatus(503);
    }

    // ─── Services ────────────────────────────────────────────────────

    public function test_service_ids_resolved_from_catalog(): void
    {
        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'GPS Seal',
            'price' => 15.00,
            'enabled' => true,
        ]);

        $user = $this->makeClient(CustomerType::STANDARD);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'selected_service_ids' => [$service->id],
            ]))
            ->assertOk();

        $this->assertEquals(15.0, $response->json('data.charges.services'));
    }

    public function test_client_cannot_manipulate_service_prices(): void
    {
        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'Loading Crew',
            'price' => 250.00,
            'enabled' => true,
        ]);

        $user = $this->makeClient(CustomerType::STANDARD);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'selected_service_ids' => [$service->id],
                'service_price' => 0.01,
            ]))
            ->assertStatus(422);
    }

    public function test_unknown_service_rejected(): void
    {
        $user = $this->makeClient(CustomerType::STANDARD);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'selected_service_ids' => [99999],
            ]))
            ->assertStatus(422);
    }

    public function test_disabled_service_rejected(): void
    {
        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $this->policy->id,
            'name' => 'Disabled Service',
            'price' => 100.00,
            'enabled' => false,
        ]);

        $user = $this->makeClient(CustomerType::STANDARD);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'selected_service_ids' => [$service->id],
            ]))
            ->assertStatus(422);
    }

    // ─── Policy ──────────────────────────────────────────────────────

    public function test_client_cannot_select_pricing_policy(): void
    {
        $user = $this->makeClient(CustomerType::STANDARD);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'pricing_policy_id' => 999,
            ]))
            ->assertStatus(422);
    }

    // ─── Pricing ─────────────────────────────────────────────────────

    public function test_successful_response_contains_pricing_result(): void
    {
        $user = $this->makeClient(CustomerType::STANDARD);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', $this->validPayload())
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'currency',
                    'distance_km',
                    'charges' => ['freight', 'weight_surcharge', 'waiting_charge', 'services', 'border', 'insurance', 'hazmat', 'heavy_reefer', 'priority'],
                    'subtotal',
                    'discount' => ['amount', 'type', 'rate'],
                    'tax' => ['amount', 'rate'],
                    'total',
                    'service_items',
                    'warnings',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.total'));
    }

    // ─── Security boundary proofs ────────────────────────────────────

    public function test_customer_type_injection_does_not_affect_calculation(): void
    {
        // Company is standard — client sends vip (which is prohibited and will 422)
        // But even if somehow passed, the server uses company's type
        $user = $this->makeClient(CustomerType::STANDARD);

        // customerType prohibited → 422. Server never uses client value.
        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'customerType' => 'vip',
            ]))
            ->assertStatus(422);
    }

    public function test_distance_injection_does_not_affect_calculation(): void
    {
        $this->mockDistanceCalculator(250.0);
        $user = $this->makeClient(CustomerType::STANDARD);

        // distance is prohibited → 422
        $this->actingAs($user, 'api')
            ->postJson('/api/v1/client/pricing/calculate', array_merge($this->validPayload(), [
                'distance' => 1,
            ]))
            ->assertStatus(422);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function makeClient(CustomerType $customerType): User
    {
        $company = Company::factory()->create(['customer_type' => $customerType->value]);
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function mockDistanceCalculator(float $km): void
    {
        $mock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $mock->shouldReceive('calculate')->andReturn($km);
        $this->app->instance(TruckRouteDistanceCalculator::class, $mock);
    }

    private function validPayload(): array
    {
        return [
            'pickup_latitude' => 33.8869,
            'pickup_longitude' => 35.5131,
            'delivery_latitude' => 36.2021,
            'delivery_longitude' => 37.1343,
            'cargo_type' => 'general',
            'trailer_type' => 'dry_van',
            'cargo_weight' => 15.0,
            'shipment_priority' => 'standard',
        ];
    }

    private function createFullPolicy(): PricingPolicy
    {
        $policy = PricingPolicy::create([
            'name' => 'Standard Freight 2026',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0000,
            'tax_inclusive' => false,
            'minimum_fare' => null,
        ]);

        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::GENERAL, 'trailer_type' => TrailerType::DRY_VAN, 'rate_per_km' => 1.45]);
        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::REFRIGERATED, 'trailer_type' => TrailerType::REEFER, 'rate_per_km' => 1.95]);
        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::HEAVY, 'trailer_type' => TrailerType::LOWBOY, 'rate_per_km' => 2.85]);
        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::HAZARDOUS, 'trailer_type' => TrailerType::TANKER, 'rate_per_km' => 2.50]);

        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 0, 'max_distance_km' => 100, 'rate_per_km' => 1.20]);
        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 101, 'max_distance_km' => 300, 'rate_per_km' => 0.95]);
        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 301, 'max_distance_km' => 700, 'rate_per_km' => 0.80]);
        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 701, 'max_distance_km' => null, 'rate_per_km' => 0.65]);

        PricingWeightConfig::create(['pricing_policy_id' => $policy->id, 'threshold_tons' => 20, 'charge_per_ton' => 50]);
        PricingWaitingConfig::create(['pricing_policy_id' => $policy->id, 'loading_free_minutes' => 30, 'unloading_free_minutes' => 30, 'rate_per_15min' => 15, 'max_charge' => 200, 'enabled' => true]);

        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'border', 'name' => 'Border Crossing', 'fee_type' => 'flat', 'amount' => 150, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'insurance', 'name' => 'Insurance', 'fee_type' => 'percentage', 'amount' => 2, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'hazmat', 'name' => 'Hazmat', 'fee_type' => 'percentage', 'amount' => 25, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'heavy_reefer', 'name' => 'Heavy Reefer', 'fee_type' => 'percentage', 'amount' => 6, 'enabled' => true]);

        foreach (['standard' => 1.0, 'express' => 1.1, 'urgent' => 1.2, 'emergency' => 1.3] as $level => $mult) {
            PricingPriorityMultiplier::create(['pricing_policy_id' => $policy->id, 'priority_level' => $level, 'multiplier' => $mult]);
        }

        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'corporate', 'discount_type' => 'percentage', 'amount' => 5, 'enabled' => true]);
        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'vip', 'discount_type' => 'percentage', 'amount' => 10, 'enabled' => true]);

        return $policy;
    }
}
