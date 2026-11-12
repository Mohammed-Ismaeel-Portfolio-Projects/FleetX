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
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\PricingPolicyTemporalClassifier;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

class AdminPricingPolicyTemporalGovernanceTest extends TestCase
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

    public function test_admin_can_create_and_activate_future_effective_policy(): void
    {
        $admin = $this->makeAdmin();
        $futureFrom = now()->addDays(7)->startOfDay();

        $payload = $this->validDraftPolicyPayload('Standard Freight 2027');
        $payload['effective_from'] = $futureFrom->format('Y-m-d H:i:s');

        $policyId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/activate')
            ->assertOk();

        $classifier = app(PricingPolicyTemporalClassifier::class);
        $policy = PricingPolicy::findOrFail($policyId);
        $this->assertSame('future', $classifier->classify($policy)->value);
    }

    public function test_future_policy_does_not_affect_pricing_before_effective_from(): void
    {
        $current = $this->createFullPricingPolicy();
        $admin = $this->makeAdmin();
        $this->mockTruckRouteDistance(500.0);
        $company = Company::factory()->create(['customer_type' => CustomerType::STANDARD->value]);

        $payload = $this->validDraftPolicyPayload('Future High Rate');
        $payload['effective_from'] = now()->addMonth()->format('Y-m-d H:i:s');
        $payload['base_rates'] = array_map(
            fn (array $rate) => array_merge($rate, ['rate_per_km' => 99.99]),
            $payload['base_rates'],
        );

        $futureId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$futureId.'/activate')
            ->assertOk();

        $resolved = app(PricingPolicyResolver::class)->resolveActive();
        $this->assertSame($current->id, $resolved->id);

        $calcPayload = [
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

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', $calcPayload)
            ->assertOk()
            ->assertJsonPath('data.pricing_policy.id', $current->id);
    }

    public function test_future_policy_becomes_resolvable_when_effective(): void
    {
        Carbon::setTestNow(now()->startOfDay());

        $this->createFullPricingPolicy();
        $admin = $this->makeAdmin();
        $effectiveFrom = now()->addDay();

        $payload = $this->validDraftPolicyPayload('Tomorrow Policy');
        $payload['priority'] = 999;
        $payload['effective_from'] = $effectiveFrom->format('Y-m-d H:i:s');

        $futureId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$futureId.'/activate')
            ->assertOk();

        $resolver = app(PricingPolicyResolver::class);
        $this->assertNotSame($futureId, $resolver->resolveActive()->id);

        Carbon::setTestNow($effectiveFrom->copy()->addMinute());
        $this->assertSame($futureId, $resolver->resolveActive()->id);

        Carbon::setTestNow();
    }

    public function test_expired_policy_is_not_selected(): void
    {
        $this->createFullPricingPolicy();

        PricingPolicy::create([
            'name' => 'Expired Window',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 999,
            'currency' => 'USD',
            'tax_rate' => 8,
            'effective_until' => now()->subMinute(),
        ]);

        $classifier = app(PricingPolicyTemporalClassifier::class);
        $expired = PricingPolicy::where('name', 'Expired Window')->firstOrFail();
        $this->assertSame('expired', $classifier->classify($expired)->value);
        $this->assertNotSame($expired->id, app(PricingPolicyResolver::class)->resolveActive()->id);
    }

    public function test_archived_policy_is_never_selected(): void
    {
        $policy = $this->createFullPricingPolicy();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policy->id.'/archive')
            ->assertOk();

        $this->expectException(\App\Services\Pricing\Exceptions\NoPricingPolicyException::class);
        app(PricingPolicyResolver::class)->resolveActive();
    }

    public function test_resolver_is_deterministic_for_same_priority(): void
    {
        PricingPolicy::query()->delete();

        $first = PricingPolicy::create([
            'name' => 'Same Priority A',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 10,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);
        $this->seedMinimalRates($first);

        $second = PricingPolicy::create([
            'name' => 'Same Priority B',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 10,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);
        $this->seedMinimalRates($second);

        $resolved = app(PricingPolicyResolver::class)->resolveActive();
        $this->assertSame($second->id, $resolved->id);
    }

    public function test_timeline_endpoint_returns_current_upcoming_and_history(): void
    {
        $current = $this->createFullPricingPolicy();
        $admin = $this->makeAdmin();

        $archived = PricingPolicy::create([
            'name' => 'Old Policy',
            'status' => PricingPolicyStatus::ARCHIVED,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);

        $payload = $this->validDraftPolicyPayload('Future Policy');
        $payload['effective_from'] = now()->addWeek()->format('Y-m-d H:i:s');
        $futureId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$futureId.'/activate')
            ->assertOk();

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/pricing/policies/timeline')
            ->assertOk();

        $this->assertSame($current->id, $response->json('data.current.id'));
        $this->assertSame('current', $response->json('data.current.temporal_state'));
        $this->assertContains($futureId, collect($response->json('data.upcoming'))->pluck('id')->all());
        $this->assertContains($archived->id, collect($response->json('data.history'))->pluck('id')->all());
    }

    public function test_dispatcher_cannot_access_timeline(): void
    {
        $this->createFullPricingPolicy();

        $this->actingAs($this->makeDispatcher(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/timeline')
            ->assertForbidden();
    }

    public function test_client_cannot_access_timeline(): void
    {
        $this->createFullPricingPolicy();

        $this->actingAs($this->makeClient(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/timeline')
            ->assertForbidden();
    }

    public function test_driver_cannot_access_timeline(): void
    {
        $this->createFullPricingPolicy();

        $this->actingAs($this->makeDriver(), 'api')
            ->getJson('/api/v1/admin/pricing/policies/timeline')
            ->assertForbidden();
    }

    public function test_historical_snapshot_unchanged_after_future_policy_activation(): void
    {
        $policy = $this->createFullPricingPolicy();
        $order = ShipmentOrder::factory()->create();
        $snapshot = ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['total' => 840.00],
            'final_total' => 840.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $admin = $this->makeAdmin();
        $payload = $this->validDraftPolicyPayload('Future Replacement');
        $payload['effective_from'] = now()->addMonth()->format('Y-m-d H:i:s');
        $payload['priority'] = 100;

        $futureId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$futureId.'/activate')
            ->assertOk();

        $fresh = $snapshot->fresh();
        $this->assertSame('840.00', (string) $fresh->final_total);
        $this->assertSame($policy->id, $fresh->pricing_policy_id);
    }

    public function test_policy_governance_does_not_create_operational_records(): void
    {
        $engine = Mockery::mock(PricingEngine::class);
        $engine->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engine);

        $orders = ShipmentOrder::query()->count();
        $invoices = MonthlyInvoice::query()->count();

        $admin = $this->makeAdmin();
        $payload = $this->validDraftPolicyPayload('Temporal Test');
        $payload['effective_from'] = now()->addDays(3)->format('Y-m-d H:i:s');

        $policyId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/activate')
            ->assertOk();

        $this->assertSame($orders, ShipmentOrder::query()->count());
        $this->assertSame($invoices, MonthlyInvoice::query()->count());
    }

    private function seedMinimalRates(PricingPolicy $policy): void
    {
        foreach (CargoType::cases() as $cargoType) {
            $policy->baseRates()->create([
                'cargo_type' => $cargoType,
                'trailer_type' => null,
                'rate_per_km' => 1.0,
            ]);
        }

        $policy->distanceTiers()->create([
            'min_distance_km' => 0,
            'max_distance_km' => null,
            'rate_per_km' => 1.0,
        ]);
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
