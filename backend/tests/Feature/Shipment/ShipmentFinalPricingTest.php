<?php

namespace Tests\Feature\Shipment;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\OperationalActualsCaptureSource;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\Company;
use App\Models\PricingVarianceRule;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use App\Models\User;
use App\Services\Pricing\Exceptions\ShipmentFinalPricingException;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\ShipmentFinalPricingService;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

/**
 * Phase 5.5c — Final shipment pricing and persistence.
 */
class ShipmentFinalPricingTest extends TestCase
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

    public function test_unauthenticated_finalize_is_rejected(): void
    {
        $policy = $this->createFullPricingPolicy();
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $this->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertUnauthorized();
    }

    public function test_client_cannot_finalize_price(): void
    {
        $policy = $this->createFullPricingPolicy();
        ['shipment' => $shipment, 'company' => $company] = $this->createFinalizableShipment($policy);

        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertForbidden();
    }

    public function test_dispatcher_can_finalize_price(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'finalized');
    }

    public function test_not_completed_shipment_returns_409(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'order' => $order, 'actuals' => $actuals] = $this->createFinalizableShipment($policy);

        $waitingStatus = \App\Models\Status::where('name', 'Waiting Assignment')->where('type', 'shipment')->firstOrFail();
        $shipment->update(['status_id' => $waitingStatus->id]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'SHIPMENT_NOT_COMPLETED');

        $this->assertDatabaseCount('shipment_final_price_breakdowns', 0);
    }

    public function test_cancelled_shipment_returns_409(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $cancelledStatus = \App\Models\Status::where('name', ShipmentStatus::CANCELLED)->where('type', 'shipment')->firstOrFail();
        $shipment->update(['status_id' => $cancelledStatus->id]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'SHIPMENT_CANCELLED');
    }

    public function test_completed_shipment_with_all_preconditions_proceeds(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();
    }

    public function test_missing_submission_snapshot_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'order' => $order] = $this->createFinalizableShipment($policy);

        ShipmentOrderPriceBreakdown::query()->where('shipment_order_id', $order->id)->delete();

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_SUBMISSION_SNAPSHOT');
    }

    public function test_missing_operational_actuals_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        ShipmentOperationalActuals::query()->where('shipment_id', $shipment->id)->delete();

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_OPERATIONAL_ACTUALS');
    }

    public function test_missing_actual_distance_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'actuals' => $actuals] = $this->createFinalizableShipment($policy);

        $actuals->update(['actual_distance_km' => null]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_ACTUAL_DISTANCE');
    }

    public function test_missing_actual_weight_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'actuals' => $actuals] = $this->createFinalizableShipment($policy);

        $actuals->update(['actual_cargo_weight' => null]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_ACTUAL_WEIGHT');
    }

    public function test_zero_waiting_minutes_is_valid_not_null(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'actuals' => $actuals] = $this->createFinalizableShipment($policy);

        $actuals->update([
            'actual_loading_wait_minutes' => 0,
            'actual_unloading_wait_minutes' => 0,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();
    }

    public function test_null_waiting_minutes_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'actuals' => $actuals] = $this->createFinalizableShipment($policy);

        $actuals->update(['actual_loading_wait_minutes' => null]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_ACTUAL_LOADING_WAIT');
    }

    public function test_no_material_change_keeps_submission_total(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'snapshot' => $snapshot] = $this->createFinalizableShipment($policy);

        $response = $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $this->assertEqualsWithDelta(
            (float) $snapshot->final_total,
            (float) $response->json('data.final_total'),
            0.01
        );
        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.price_delta'), 0.01);
        $this->assertSame('none', $response->json('data.change_direction'));
        $this->assertFalse($response->json('data.material_change'));
    }

    public function test_material_increase_persists_candidate_final_price(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'snapshot' => $snapshot] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 575,
        ]);

        $candidate = app(PricingEngine::class)->calculate(
            $this->baselinePricingInput(575),
            $policy,
        );

        $response = $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $this->assertTrue($response->json('data.material_change'));
        $this->assertSame('increase', $response->json('data.change_direction'));
        $this->assertGreaterThan((float) $snapshot->final_total, (float) $response->json('data.final_total'));
        $this->assertEqualsWithDelta($candidate->finalTotal, (float) $response->json('data.final_total'), 0.01);
    }

    public function test_material_increase_persists_reason_code(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 575,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $distanceVariance = ShipmentPriceVariance::query()
            ->whereHas('finalPriceBreakdown', fn ($q) => $q->where('shipment_id', $shipment->id))
            ->where('factor_code', VarianceFactorCode::DISTANCE)
            ->first();

        $this->assertNotNull($distanceVariance);
        $this->assertTrue($distanceVariance->material);
        $this->assertSame('DISTANCE_EXCEEDED', $distanceVariance->reason_code);
        $this->assertNotSame(0.0, (float) $distanceVariance->price_impact);
    }

    public function test_below_threshold_variance_is_immaterial(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $distanceVariance = ShipmentPriceVariance::query()
            ->whereHas('finalPriceBreakdown', fn ($q) => $q->where('shipment_id', $shipment->id))
            ->where('factor_code', VarianceFactorCode::DISTANCE)
            ->first();

        $this->assertFalse($distanceVariance->material);
    }

    public function test_immaterial_operational_delta_with_price_change_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 520,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'UNEXPLAINED_PRICE_DELTA');
    }

    public function test_missing_variance_rule_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 575,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_VARIANCE_RULE');

        $this->assertDatabaseCount('shipment_final_price_breakdowns', 0);
    }

    public function test_unexplained_price_delta_is_blocked(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 520,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'UNEXPLAINED_PRICE_DELTA');

        $this->assertDatabaseCount('shipment_final_price_breakdowns', 0);
    }

    public function test_material_decrease_when_applies_to_decrease_enabled(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy, [
            VarianceFactorCode::DISTANCE->value => [
                'threshold_type' => VarianceThresholdType::ABSOLUTE,
                'threshold_value' => 10,
                'applies_to_increase' => true,
                'applies_to_decrease' => true,
            ],
        ]);
        ['shipment' => $shipment, 'snapshot' => $snapshot] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 450,
        ]);

        $response = $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $this->assertTrue($response->json('data.material_change'));
        $this->assertSame('decrease', $response->json('data.change_direction'));
        $this->assertLessThan((float) $snapshot->final_total, (float) $response->json('data.final_total'));
    }

    public function test_decrease_not_applicable_blocks_unexplained_lower_price(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 450,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'UNEXPLAINED_PRICE_DELTA');
    }

    public function test_multiple_variance_rows_are_persisted(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 575,
            'actual_cargo_weight' => 22,
            'actual_loading_wait_minutes' => 90,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $final = ShipmentFinalPriceBreakdown::query()->where('shipment_id', $shipment->id)->first();
        $this->assertCount(4, $final->variances);
    }

    public function test_variance_rows_have_deterministic_sort_order(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $orders = ShipmentPriceVariance::query()
            ->whereHas('finalPriceBreakdown', fn ($q) => $q->where('shipment_id', $shipment->id))
            ->orderBy('sort_order')
            ->pluck('sort_order')
            ->all();

        $this->assertSame([0, 1, 2, 3], $orders);
    }

    public function test_idempotent_second_finalize_returns_existing_snapshot(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $dispatcher = $this->makeDispatcher();

        $first = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $second = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('shipment_final_price_breakdowns', 1);
        $this->assertDatabaseCount('shipment_price_variances', 4);
    }

    public function test_submission_snapshot_is_never_modified(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'snapshot' => $snapshot] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 575,
        ]);

        $originalTotal = (string) $snapshot->final_total;
        $originalJson = $snapshot->breakdown_json;

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $snapshot->refresh();
        $this->assertSame($originalTotal, (string) $snapshot->final_total);
        $this->assertSame($originalJson, $snapshot->breakdown_json);
    }

    public function test_final_snapshot_references_frozen_policy_and_actuals(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'snapshot' => $snapshot, 'actuals' => $actuals] = $this->createFinalizableShipment($policy);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $final = ShipmentFinalPriceBreakdown::query()->where('shipment_id', $shipment->id)->firstOrFail();

        $this->assertSame($policy->id, $final->pricing_policy_id);
        $this->assertSame($snapshot->id, $final->submission_price_breakdown_id);
        $this->assertSame($actuals->id, $final->operational_actuals_id);
        $this->assertSame(FinalPriceBreakdownStatus::FINALIZED, $final->status);
    }

    public function test_pricing_configuration_error_does_not_partially_persist(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldReceive('calculate')
            ->andThrow(new \App\Services\Pricing\Exceptions\InvalidPricingConfigException('broken config'));
        $this->app->instance(PricingEngine::class, $engineMock);

        try {
            app(ShipmentFinalPricingService::class)->finalize(
                $shipment,
                $this->makeDispatcher(),
                \App\Enums\FinalPriceCalculationTrigger::MANUAL,
            );
            $this->fail('Expected ShipmentFinalPricingException was not thrown.');
        } catch (ShipmentFinalPricingException $e) {
            $this->assertSame('PRICING_CONFIGURATION_ERROR', $e->errorCode);
        } finally {
            $this->assertDatabaseCount('shipment_final_price_breakdowns', 0);
            $this->assertDatabaseCount('shipment_price_variances', 0);
        }
    }

    public function test_service_finalize_throws_with_error_code(): void
    {
        $policy = $this->createFullPricingPolicy();
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 575,
        ]);

        $this->expectException(ShipmentFinalPricingException::class);
        $this->expectExceptionMessage('Variance rule is missing');

        app(ShipmentFinalPricingService::class)->finalize(
            $shipment,
            null,
            \App\Enums\FinalPriceCalculationTrigger::MANUAL,
        );
    }

    public function test_endpoint_ignores_client_supplied_pricing_fields(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'snapshot' => $snapshot] = $this->createFinalizableShipment($policy);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price', [
                'final_total' => 1.00,
                'price_delta' => -999,
                'actual_distance_km' => 9999,
            ])
            ->assertCreated();

        $final = ShipmentFinalPriceBreakdown::query()->where('shipment_id', $shipment->id)->firstOrFail();
        $this->assertEqualsWithDelta((float) $snapshot->final_total, (float) $final->final_total, 0.01);
    }

    public function test_resubmit_uses_latest_submission_snapshot(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment, 'order' => $order, 'snapshot' => $firstSnapshot] = $this->createFinalizableShipment($policy);

        $replacementBreakdown = app(PricingEngine::class)->calculate(
            $this->baselinePricingInput(480),
            $policy,
        );

        $firstSnapshot->update([
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => \App\Services\Pricing\PricingBreakdownSerializer::toArray($replacementBreakdown),
            'final_total' => $replacementBreakdown->finalTotal,
            'currency' => $replacementBreakdown->currency,
            'calculated_at' => now(),
        ]);

        ShipmentOperationalActuals::query()
            ->where('shipment_id', $shipment->id)
            ->update(['actual_distance_km' => 480]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $final = ShipmentFinalPriceBreakdown::query()->where('shipment_id', $shipment->id)->firstOrFail();
        $this->assertSame($firstSnapshot->id, $final->submission_price_breakdown_id);
        $this->assertEqualsWithDelta((float) $firstSnapshot->fresh()->final_total, (float) $final->submission_total, 0.01);
        $this->assertEqualsWithDelta(480.0, (float) $firstSnapshot->fresh()->breakdown_json['input']['distance'], 0.01);
    }

    public function test_missing_shipment_order_fails_closed(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $shipment->update(['shipment_order_id' => null]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_SHIPMENT_ORDER');
    }

    public function test_completion_hook_skips_when_preconditions_missing(): void
    {
        $policy = $this->createFullPricingPolicy();
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        ShipmentOperationalActuals::query()->where('shipment_id', $shipment->id)->delete();

        app(ShipmentFinalPricingService::class)->tryFinalizeOnCompletion(
            $shipment,
            \App\Enums\FinalPriceCalculationTrigger::COMPLETION_HOOK,
        );

        $this->assertDatabaseCount('shipment_final_price_breakdowns', 0);
    }

    public function test_finalized_snapshot_is_not_updated_on_repeat_service_call(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy);

        $service = app(ShipmentFinalPricingService::class);
        $first = $service->finalize($shipment, null, \App\Enums\FinalPriceCalculationTrigger::MANUAL);
        $originalCalculatedAt = $first->calculated_at?->toIso8601String();

        $second = $service->finalize($shipment->fresh(), null, \App\Enums\FinalPriceCalculationTrigger::MANUAL);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($originalCalculatedAt, $second->calculated_at?->toIso8601String());
    }

    public function test_material_increase_at_exact_threshold(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 550,
        ]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated()
            ->assertJsonPath('data.material_change', true);

        $distanceVariance = ShipmentPriceVariance::query()
            ->whereHas('finalPriceBreakdown', fn ($q) => $q->where('shipment_id', $shipment->id))
            ->where('factor_code', VarianceFactorCode::DISTANCE)
            ->first();

        $this->assertTrue($distanceVariance->material);
    }

    public function test_shipment_final_price_breakdown_relationship(): void
    {
        $policy = $this->createFullPricingPolicy();
        $this->createTierOneVarianceRules($policy);
        ['shipment' => $shipment] = $this->createFinalizableShipment($policy, [
            'actual_distance_km' => 500,
        ]);

        PricingVarianceRule::query()->update(['threshold_value' => 1000]);

        $this->actingAs($this->makeDispatcher(), 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/finalize-price')
            ->assertCreated();

        $shipment->load('finalPriceBreakdown.variances');
        $this->assertNotNull($shipment->finalPriceBreakdown);
        $this->assertCount(4, $shipment->finalPriceBreakdown->variances);
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }
}
