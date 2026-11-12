<?php

namespace Tests\Feature\Shipment;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\FinalPriceCalculationTrigger;
use App\Enums\FinalPriceChangeDirection;
use App\Enums\VarianceFactorCode;
use App\Models\Company;
use App\Models\Role;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOrderPriceBreakdown;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

/**
 * Phase 5.5d — client final shipment price read integration.
 */
class ClientShipmentFinalPriceTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use RefreshDatabase;

    private Role $clientRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_show_returns_null_final_price_when_not_finalized(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $client = $this->makeClient($fixture['company']);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->assertJsonPath('data.final_price', null);
    }

    public function test_show_returns_finalized_price(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $this->finalizeShipmentFixture($fixture);
        $client = $this->makeClient($fixture['company']);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk();

        $this->assertSame('finalized', $response->json('data.final_price.status'));
        $this->assertEqualsWithDelta(
            (float) $fixture['snapshot']->final_total,
            (float) $response->json('data.final_price.submission_price.total'),
            0.01
        );
        $this->assertGreaterThan(
            (float) $response->json('data.final_price.submission_price.total'),
            (float) $response->json('data.final_price.final_price.total')
        );
    }

    public function test_show_client_projection_is_safe(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $this->finalizeShipmentFixture($fixture);
        $client = $this->makeClient($fixture['company']);

        $content = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->getContent();

        $finalPrice = json_decode($content, true)['data']['final_price'];

        $this->assertArrayNotHasKey('pricing_policy_id', $finalPrice);
        $this->assertArrayNotHasKey('operational_actuals_id', $finalPrice);
        $this->assertArrayNotHasKey('breakdown_json', $finalPrice);
        $this->assertStringNotContainsString('internal_message', $content);
        $this->assertStringNotContainsString('threshold_value', $content);
        $this->assertStringNotContainsString('capture_source', $content);
        $this->assertStringNotContainsString('appliedRules', $content);
    }

    public function test_foreign_company_cannot_read_final_price(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        $otherCompany = Company::factory()->create();
        $foreignClient = $this->makeClient($otherCompany);

        $this->actingAs($foreignClient, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertNotFound();
    }

    public function test_list_returns_null_final_price_summary_without_finalization(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $client = $this->makeClient($fixture['company']);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments')
            ->assertOk()
            ->assertJsonPath('data.data.0.final_price_summary', null);
    }

    public function test_list_returns_final_price_summary_from_columns(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $final = $this->finalizeShipmentFixture($fixture);
        $client = $this->makeClient($fixture['company']);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments')
            ->assertOk()
            ->assertJsonPath('data.data.0.final_price_summary.status', 'finalized')
            ->assertJsonPath('data.data.0.final_price_summary.currency', 'USD')
            ->assertJsonPath('data.data.0.final_price_summary.change_direction', 'increase')
            ->assertJsonPath('data.data.0.final_price_summary.material_change', true);

        $summaryTotal = json_decode(
            $this->actingAs($client, 'api')->getJson('/api/v1/client/shipments')->getContent(),
            true
        )['data']['data'][0]['final_price_summary']['total'];

        $this->assertEqualsWithDelta((float) $final->final_total, (float) $summaryTotal, 0.01);
    }

    public function test_malformed_breakdown_json_returns_degraded_client_response(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->persistDegradedFinalBreakdown($fixture);

        $client = $this->makeClient($fixture['company']);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->assertJsonPath('data.final_price.warnings.0', 'Final price detail unavailable');

        $this->assertEqualsWithDelta(160.0, (float) $response->json('data.final_price.comparison.delta'), 0.01);
    }

    public function test_pricing_engine_never_invoked_on_client_show(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);
        $client = $this->makeClient($fixture['company']);

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
        $finalServiceMock->shouldNotReceive('tryFinalizeOnCompletion');
        $this->app->instance(ShipmentFinalPricingService::class, $finalServiceMock);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk();
    }

    public function test_get_does_not_modify_persisted_snapshots(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, ['actual_distance_km' => 575]);
        $this->finalizeShipmentFixture($fixture);
        $client = $this->makeClient($fixture['company']);

        $submissionBefore = (string) ShipmentOrderPriceBreakdown::query()->first()->final_total;
        $finalBefore = (string) ShipmentFinalPriceBreakdown::query()->first()->final_total;
        $varianceCount = ShipmentPriceVariance::query()->count();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk();

        $this->assertSame($submissionBefore, (string) ShipmentOrderPriceBreakdown::query()->first()->final_total);
        $this->assertSame($finalBefore, (string) ShipmentFinalPriceBreakdown::query()->first()->final_total);
        $this->assertSame($varianceCount, ShipmentPriceVariance::query()->count());
    }

    public function test_repeated_get_returns_identical_persisted_values(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);
        $client = $this->makeClient($fixture['company']);

        $first = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->json('data.final_price');

        $second = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertOk()
            ->json('data.final_price');

        $this->assertSame($first, $second);
    }

    public function test_list_does_not_lazy_load_final_price_breakdown(): void
    {
        Model::preventLazyLoading();

        $policy = $this->createFullPricingPolicy();
        $company = Company::factory()->create(['customer_type' => 'corporate']);
        $client = $this->makeClient($company);

        for ($i = 0; $i < 3; $i++) {
            $fixture = $this->createFinalizableShipment($policy, shipmentOverrides: [
                'customer_company_id' => $company->id,
                'shipment_number' => 'SHP-FP-'.$i,
            ]);
            $this->finalizeShipmentFixture($fixture);
        }

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments')
            ->assertOk();
    }

    public function test_unauthenticated_client_show_is_rejected(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);

        $this->getJson('/api/v1/client/shipments/'.$fixture['shipment']->id)
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function persistDegradedFinalBreakdown(array $fixture): ShipmentFinalPriceBreakdown
    {
        $final = ShipmentFinalPriceBreakdown::create([
            'shipment_id' => $fixture['shipment']->id,
            'shipment_order_id' => $fixture['order']->id,
            'submission_price_breakdown_id' => $fixture['snapshot']->id,
            'pricing_policy_id' => $fixture['policy']->id,
            'operational_actuals_id' => $fixture['actuals']->id,
            'breakdown_json' => ['corrupt' => true],
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

        ShipmentPriceVariance::create([
            'shipment_final_price_breakdown_id' => $final->id,
            'factor_code' => VarianceFactorCode::DISTANCE,
            'material' => true,
            'billable' => true,
            'price_impact' => 160.00,
            'reason_code' => 'DISTANCE_EXCEEDED',
            'customer_message' => 'Distance exceeded submission estimate.',
            'internal_message' => 'Internal ops note',
            'sort_order' => 0,
        ]);

        return $final;
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }
}
