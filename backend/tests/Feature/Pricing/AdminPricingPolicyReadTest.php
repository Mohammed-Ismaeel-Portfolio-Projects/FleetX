<?php

namespace Tests\Feature\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Models\PricingPolicy;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

class AdminPricingPolicyReadTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    private Role $driverRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
    }

    public function test_unauthenticated_policy_list_returns_401(): void
    {
        $this->getJson('/api/v1/admin/pricing/policies')->assertUnauthorized();
    }

    public function test_admin_can_list_pricing_policies(): void
    {
        $this->createFullPricingPolicy();
        PricingPolicy::create([
            'name' => 'Draft Policy',
            'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD',
            'tax_rate' => 8,
            'tax_inclusive' => false,
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/v1/admin/pricing/policies')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.base_rates');
    }

    public function test_admin_can_view_pricing_policy_with_rule_relationships(): void
    {
        $policy = $this->createFullPricingPolicy();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/'.$policy->id)
            ->assertOk()
            ->assertJsonPath('data.id', $policy->id)
            ->assertJsonPath('data.name', $policy->name)
            ->assertJsonPath('data.status', PricingPolicyStatus::ACTIVE->value)
            ->assertJsonPath('data.currency', 'USD');

        $this->assertNotEmpty($response->json('data.base_rates'));
        $this->assertNotEmpty($response->json('data.distance_tiers'));
        $this->assertNotNull($response->json('data.weight_config'));
        $this->assertNotNull($response->json('data.waiting_config'));
        $this->assertNotEmpty($response->json('data.fees'));
        $this->assertNotEmpty($response->json('data.priority_multipliers'));
        $this->assertNotEmpty($response->json('data.discounts'));
        $this->assertIsArray($response->json('data.service_catalog'));
        $this->assertIsArray($response->json('data.variance_rules'));
    }

    public function test_admin_can_view_active_pricing_policy(): void
    {
        $policy = $this->createFullPricingPolicy();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/active')
            ->assertOk()
            ->assertJsonPath('data.id', $policy->id)
            ->assertJsonPath('data.status', PricingPolicyStatus::ACTIVE->value);
    }

    public function test_active_policy_matches_pricing_policy_resolver_semantics(): void
    {
        PricingPolicy::create([
            'name' => 'Draft High Priority',
            'status' => PricingPolicyStatus::DRAFT,
            'priority' => 99,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);
        PricingPolicy::create([
            'name' => 'Scheduled Future',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 50,
            'currency' => 'USD',
            'tax_rate' => 8,
            'effective_from' => now()->addDay(),
        ]);
        PricingPolicy::create([
            'name' => 'Expired Window',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 40,
            'currency' => 'USD',
            'tax_rate' => 8,
            'effective_until' => now()->subMinute(),
        ]);
        PricingPolicy::create([
            'name' => 'Low Priority Active',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 1,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);
        $winner = PricingPolicy::create([
            'name' => 'High Priority Active',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 10,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);

        $resolved = app(PricingPolicyResolver::class)->resolveActive();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/active')
            ->assertOk()
            ->assertJsonPath('data.id', $resolved->id)
            ->assertJsonPath('data.id', $winner->id);
    }

    public function test_active_policy_returns_404_when_none_resolves(): void
    {
        PricingPolicy::create([
            'name' => 'Draft Only',
            'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/active')
            ->assertNotFound();
    }

    public function test_dispatcher_cannot_access_policy_governance_api(): void
    {
        $policy = $this->createFullPricingPolicy();
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/admin/pricing/policies')->assertForbidden();
        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/admin/pricing/policies/active')->assertForbidden();
        $this->actingAs($dispatcher, 'api')->getJson('/api/v1/admin/pricing/policies/'.$policy->id)->assertForbidden();
    }

    public function test_client_cannot_access_policy_governance_api(): void
    {
        $policy = $this->createFullPricingPolicy();
        $client = $this->makeClient();

        $this->actingAs($client, 'api')->getJson('/api/v1/admin/pricing/policies')->assertForbidden();
        $this->actingAs($client, 'api')->getJson('/api/v1/admin/pricing/policies/active')->assertForbidden();
        $this->actingAs($client, 'api')->getJson('/api/v1/admin/pricing/policies/'.$policy->id)->assertForbidden();
    }

    public function test_driver_cannot_access_policy_governance_api(): void
    {
        $policy = $this->createFullPricingPolicy();
        $driver = $this->makeDriver();

        $this->actingAs($driver, 'api')->getJson('/api/v1/admin/pricing/policies')->assertForbidden();
        $this->actingAs($driver, 'api')->getJson('/api/v1/admin/pricing/policies/active')->assertForbidden();
        $this->actingAs($driver, 'api')->getJson('/api/v1/admin/pricing/policies/'.$policy->id)->assertForbidden();
    }

    public function test_policy_get_does_not_trigger_pricing_calculation_or_create_operational_records(): void
    {
        $policy = $this->createFullPricingPolicy();

        $engine = Mockery::mock(PricingEngine::class);
        $engine->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engine);

        $shipments = Shipment::query()->count();
        $orders = ShipmentOrder::query()->count();
        $invoices = MonthlyInvoice::query()->count();

        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->getJson('/api/v1/admin/pricing/policies')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/admin/pricing/policies/'.$policy->id)->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/v1/admin/pricing/policies/active')->assertOk();

        $this->assertSame($shipments, Shipment::query()->count());
        $this->assertSame($orders, ShipmentOrder::query()->count());
        $this->assertSame($invoices, MonthlyInvoice::query()->count());
    }

    public function test_existing_pricing_calculate_remains_allowed_for_admin_and_dispatcher(): void
    {
        $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $payload = [
            'pickup_latitude' => 33.8869,
            'pickup_longitude' => 35.5131,
            'delivery_latitude' => 36.2021,
            'delivery_longitude' => 37.1343,
            'cargo_type' => CargoType::GENERAL->value,
            'trailer_type' => TrailerType::DRY_VAN->value,
            'cargo_weight' => 15.0,
            'shipment_priority' => 'standard',
            'customer_company_id' => $company->id,
        ];

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/pricing/calculate', $payload)
            ->assertOk();

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/pricing/calculate', $payload)
            ->assertOk();
    }

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

    private function makeClient(): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => Company::factory()->create()->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeDriver(): User
    {
        return User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);
    }
}
