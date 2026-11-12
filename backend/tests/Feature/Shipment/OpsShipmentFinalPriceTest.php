<?php

namespace Tests\Feature\Shipment;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\FinalPriceCalculationTrigger;
use App\Enums\FinalPriceChangeDirection;
use App\Enums\VarianceFactorCode;
use App\Models\Role;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\ShipmentFinalPricingService;
use App\Services\Pricing\ShipmentOrderPricingService;
use App\Services\Routing\TruckRouteDistanceCalculator;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

/**
 * Phase 5.5d — ops final shipment price read integration.
 */
class OpsShipmentFinalPriceTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use RefreshDatabase;

    private Role $dispatcherRole;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
    }

    public function test_ops_show_returns_null_final_price_when_not_finalized(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->assertJsonPath('data.final_price', null);
    }

    public function test_ops_show_returns_finalized_price_with_variances(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $this->finalizeShipmentFixture($fixture);
        $dispatcher = $this->makeDispatcher();

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk();

        $this->assertSame('finalized', $response->json('data.final_price.status'));
        $this->assertSame($fixture['policy']->id, $response->json('data.final_price.pricing_policy_id'));
        $this->assertCount(4, $response->json('data.final_price.variances'));
    }

    public function test_ops_show_exposes_internal_variance_messages(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $final = $this->finalizeShipmentFixture($fixture);

        ShipmentPriceVariance::query()
            ->where('shipment_final_price_breakdown_id', $final->id)
            ->where('factor_code', VarianceFactorCode::DISTANCE)
            ->update([
                'internal_message' => 'Ops internal distance note',
                'customer_message' => 'Distance exceeded estimate.',
                'threshold_value' => 50,
                'threshold_type' => 'absolute',
                'threshold_scope' => 'policy',
                'data_source' => 'ops_manual',
            ]);

        $response = $this->actingAs($this->makeDispatcher(), 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk();

        $distanceVariance = collect($response->json('data.final_price.variances'))
            ->firstWhere('factor_code', 'distance');

        $this->assertSame('Ops internal distance note', $distanceVariance['internal_message']);
        $this->assertEqualsWithDelta(50.0, (float) $distanceVariance['threshold_value'], 0.01);
        $this->assertSame('policy', $distanceVariance['threshold_scope']);
    }

    public function test_ops_show_exposes_operational_actuals(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        $response = $this->actingAs($this->makeDispatcher(), 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk();

        $this->assertSame(
            $fixture['actuals']->id,
            $response->json('data.final_price.operational_actuals.id')
        );
        $this->assertSame('ops_manual', $response->json('data.final_price.operational_actuals.capture_source'));
    }

    public function test_ops_list_returns_final_price_summary(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $final = $this->finalizeShipmentFixture($fixture);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonPath('data.data.0.final_price_summary.status', 'finalized')
            ->assertJsonPath('data.data.0.final_price_summary.currency', 'USD');

        $summaryTotal = json_decode(
            $this->actingAs($this->makeDispatcher(), 'api')->getJson('/api/v1/shipments')->getContent(),
            true
        )['data']['data'][0]['final_price_summary']['total'];

        $this->assertEqualsWithDelta((float) $final->final_total, (float) $summaryTotal, 0.01);
    }

    public function test_ops_malformed_breakdown_json_returns_degraded_response(): void
    {
        Log::spy();

        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);

        ShipmentFinalPriceBreakdown::create([
            'shipment_id' => $fixture['shipment']->id,
            'shipment_order_id' => $fixture['order']->id,
            'submission_price_breakdown_id' => $fixture['snapshot']->id,
            'pricing_policy_id' => $fixture['policy']->id,
            'operational_actuals_id' => $fixture['actuals']->id,
            'breakdown_json' => ['bad' => 'data'],
            'submission_total' => 1450.00,
            'final_total' => 1610.00,
            'price_delta' => 160.00,
            'price_delta_percentage' => 11.0345,
            'change_direction' => FinalPriceChangeDirection::INCREASE,
            'material_change' => true,
            'currency' => 'USD',
            'calculated_at' => now(),
            'calculation_trigger' => FinalPriceCalculationTrigger::MANUAL,
            'status' => FinalPriceBreakdownStatus::FINALIZED,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->assertJsonPath('data.final_price.detail_status', 'degraded')
            ->assertJsonPath('data.final_price.warnings.0', 'Final price breakdown detail unavailable');

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_ops_get_never_invokes_pricing_services(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engineMock);

        $serviceMock = Mockery::mock(ShipmentOrderPricingService::class);
        $serviceMock->shouldNotReceive('calculateAndPersistSnapshot');
        $this->app->instance(ShipmentOrderPricingService::class, $serviceMock);

        $distanceMock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $distanceMock->shouldNotReceive('calculate');
        $this->app->instance(TruckRouteDistanceCalculator::class, $distanceMock);

        $resolverMock = Mockery::mock(PricingPolicyResolver::class);
        $resolverMock->shouldNotReceive('resolveActive');
        $this->app->instance(PricingPolicyResolver::class, $resolverMock);

        $finalServiceMock = Mockery::mock(ShipmentFinalPricingService::class);
        $finalServiceMock->shouldNotReceive('finalize');
        $this->app->instance(ShipmentFinalPricingService::class, $finalServiceMock);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk();
    }

    public function test_admin_can_view_final_price_across_companies(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $this->finalizeShipmentFixture($fixture);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->assertJsonPath('data.final_price.status', 'finalized');
    }

    public function test_unauthenticated_ops_show_is_rejected(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);

        $this->getJson('/api/v1/shipments/'.$fixture['shipment']->id)
            ->assertUnauthorized();
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }
}
