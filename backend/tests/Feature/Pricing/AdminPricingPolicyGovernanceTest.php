<?php

namespace Tests\Feature\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Models\PricingPolicy;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

class AdminPricingPolicyGovernanceTest extends TestCase
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

    public function test_admin_can_create_valid_draft_policy(): void
    {
        $admin = $this->makeAdmin();
        $payload = $this->validDraftPolicyPayload();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.status', PricingPolicyStatus::DRAFT->value);

        $this->assertNotEmpty($response->json('data.base_rates'));
        $this->assertDatabaseHas('pricing_policies', [
            'name' => $payload['name'],
            'status' => PricingPolicyStatus::DRAFT->value,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_update_draft_policy(): void
    {
        $admin = $this->makeAdmin();
        $create = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload())
            ->assertCreated();

        $policyId = $create->json('data.id');
        $payload = $this->validDraftPolicyPayload('Updated Draft Policy');
        $payload['priority'] = 20;

        $this->actingAs($admin, 'api')
            ->putJson('/api/v1/admin/pricing/policies/'.$policyId, $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Draft Policy')
            ->assertJsonPath('data.priority', 20);
    }

    public function test_admin_cannot_update_active_policy(): void
    {
        $policy = $this->createFullPricingPolicy();
        $payload = $this->validDraftPolicyPayload('Should Fail');

        $this->actingAs($this->makeAdmin(), 'api')
            ->putJson('/api/v1/admin/pricing/policies/'.$policy->id, $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_activate_valid_draft_policy(): void
    {
        $admin = $this->makeAdmin();
        $policyId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload())
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', PricingPolicyStatus::ACTIVE->value);
    }

    public function test_invalid_policy_cannot_be_activated(): void
    {
        $admin = $this->makeAdmin();
        $payload = $this->validDraftPolicyPayload();
        $payload['base_rates'] = [
            ['cargo_type' => 'general', 'trailer_type' => 'dry_van', 'rate_per_km' => 1.45],
        ];
        $payload['distance_tiers'] = [
            ['min_distance_km' => 10, 'max_distance_km' => 100, 'rate_per_km' => 1.20],
        ];

        $policyId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/activate')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_archive_draft_and_active_policies(): void
    {
        $admin = $this->makeAdmin();
        $draftId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload('Archive Draft'))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$draftId.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', PricingPolicyStatus::ARCHIVED->value);

        $active = $this->createFullPricingPolicy();
        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$active->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', PricingPolicyStatus::ARCHIVED->value);
    }

    public function test_invalid_lifecycle_transition_returns_error(): void
    {
        $admin = $this->makeAdmin();
        $policyId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload('Lifecycle Test'))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/archive')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/activate')
            ->assertStatus(422);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/archive')
            ->assertStatus(422);
    }

    public function test_dispatcher_cannot_mutate_policies(): void
    {
        $policy = $this->createFullPricingPolicy();
        $payload = $this->validDraftPolicyPayload();
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')->postJson('/api/v1/admin/pricing/policies', $payload)->assertForbidden();
        $this->actingAs($dispatcher, 'api')->putJson('/api/v1/admin/pricing/policies/'.$policy->id, $payload)->assertForbidden();
        $this->actingAs($dispatcher, 'api')->postJson('/api/v1/admin/pricing/policies/'.$policy->id.'/activate')->assertForbidden();
        $this->actingAs($dispatcher, 'api')->postJson('/api/v1/admin/pricing/policies/'.$policy->id.'/archive')->assertForbidden();
    }

    public function test_client_and_driver_cannot_access_policy_governance_mutations(): void
    {
        $policy = $this->createFullPricingPolicy();
        $payload = $this->validDraftPolicyPayload();
        $client = $this->makeClient();
        $driver = $this->makeDriver();

        foreach ([$client, $driver] as $user) {
            $this->actingAs($user, 'api')->postJson('/api/v1/admin/pricing/policies', $payload)->assertForbidden();
            $this->actingAs($user, 'api')->putJson('/api/v1/admin/pricing/policies/'.$policy->id, $payload)->assertForbidden();
            $this->actingAs($user, 'api')->postJson('/api/v1/admin/pricing/policies/'.$policy->id.'/activate')->assertForbidden();
            $this->actingAs($user, 'api')->postJson('/api/v1/admin/pricing/policies/'.$policy->id.'/archive')->assertForbidden();
        }
    }

    public function test_existing_calculate_api_still_works_after_governance_changes(): void
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

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload('Extra Draft'))
            ->assertCreated();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/pricing/calculate', $payload)
            ->assertOk();
    }

    public function test_active_policy_resolver_priority_and_effective_dates_remain_correct(): void
    {
        $admin = $this->makeAdmin();
        $this->createFullPricingPolicy();

        $highPriorityPayload = $this->validDraftPolicyPayload('High Priority Draft');
        $highPriorityPayload['priority'] = 100;
        $highId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $highPriorityPayload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$highId.'/activate')
            ->assertOk();

        $resolved = app(PricingPolicyResolver::class)->resolveActive();
        $this->assertSame($highId, $resolved->id);

        $futurePayload = $this->validDraftPolicyPayload('Future Policy');
        $futurePayload['priority'] = 200;
        $futurePayload['effective_from'] = now()->addDay()->format('Y-m-d H:i:s');
        $futureId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $futurePayload)
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$futureId.'/activate')
            ->assertOk();

        $resolvedAfterFuture = app(PricingPolicyResolver::class)->resolveActive();
        $this->assertSame($highId, $resolvedAfterFuture->id);
    }

    public function test_existing_shipment_order_snapshots_remain_unchanged_after_policy_update(): void
    {
        $policy = $this->createFullPricingPolicy();
        $order = ShipmentOrder::factory()->create();
        $snapshot = ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['total' => 1000.00, 'freight' => 800.00],
            'final_total' => 1000.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $draft = PricingPolicy::create([
            'name' => 'Draft To Mutate',
            'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->putJson('/api/v1/admin/pricing/policies/'.$draft->id, $this->validDraftPolicyPayload('Draft To Mutate'))
            ->assertOk();

        $fresh = $snapshot->fresh();
        $this->assertSame('1000.00', (string) $fresh->final_total);
        $this->assertSame($policy->id, $fresh->pricing_policy_id);
        $this->assertEquals(1000.00, $fresh->breakdown_json['total']);
    }

    public function test_policy_mutation_does_not_create_operational_records(): void
    {
        $engine = Mockery::mock(PricingEngine::class);
        $engine->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engine);

        $shipments = Shipment::query()->count();
        $assignments = Assignment::query()->count();
        $orders = ShipmentOrder::query()->count();
        $invoices = MonthlyInvoice::query()->count();

        $admin = $this->makeAdmin();
        $create = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload())
            ->assertCreated();

        $policyId = $create->json('data.id');

        $this->actingAs($admin, 'api')
            ->putJson('/api/v1/admin/pricing/policies/'.$policyId, $this->validDraftPolicyPayload('Mutated Draft'))
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies/'.$policyId.'/activate')
            ->assertOk();

        $this->assertSame($shipments, Shipment::query()->count());
        $this->assertSame($assignments, Assignment::query()->count());
        $this->assertSame($orders, ShipmentOrder::query()->count());
        $this->assertSame($invoices, MonthlyInvoice::query()->count());
    }

    public function test_invalid_nested_variance_rule_is_rejected_on_create(): void
    {
        $payload = $this->validDraftPolicyPayload();
        $payload['variance_rules'] = [[
            'factor_code' => 'distance',
            'threshold_type' => 'absolute',
            'threshold_value' => -5,
        ]];

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/admin/pricing/policies', $payload)
            ->assertStatus(422);
    }

    public function test_transaction_rollback_when_nested_configuration_fails(): void
    {
        $admin = $this->makeAdmin();
        $policyId = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/pricing/policies', $this->validDraftPolicyPayload('Rollback Test'))
            ->assertCreated()
            ->json('data.id');

        $payload = $this->validDraftPolicyPayload('Rollback Test');
        $payload['base_rates'] = [
            ['cargo_type' => 'general', 'trailer_type' => 'dry_van', 'rate_per_km' => 9.99],
        ];
        $payload['distance_tiers'] = [
            ['min_distance_km' => 0, 'max_distance_km' => 100, 'rate_per_km' => 1.20],
        ];

        $this->actingAs($admin, 'api')
            ->putJson('/api/v1/admin/pricing/policies/'.$policyId, $payload)
            ->assertOk();

        $payload['base_rates'] = [
            ['cargo_type' => 'general', 'trailer_type' => 'dry_van', 'rate_per_km' => 1.45],
        ];
        $payload['distance_tiers'] = [
            ['min_distance_km' => 0, 'max_distance_km' => 100, 'rate_per_km' => 1.20],
        ];
        $payload['variance_rules'] = [
            [
                'factor_code' => 'distance',
                'threshold_type' => 'absolute',
                'threshold_value' => 10,
            ],
            [
                'factor_code' => 'distance',
                'threshold_type' => 'absolute',
                'threshold_value' => 20,
            ],
        ];

        $this->actingAs($admin, 'api')
            ->putJson('/api/v1/admin/pricing/policies/'.$policyId, $payload)
            ->assertStatus(422);

        $policy = PricingPolicy::query()->with('baseRates')->findOrFail($policyId);
        $this->assertCount(1, $policy->baseRates);
        $this->assertSame('9.9900', (string) $policy->baseRates->first()->rate_per_km);
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
