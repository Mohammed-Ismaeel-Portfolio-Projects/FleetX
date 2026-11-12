<?php

namespace Tests\Feature\Pricing;

use App\Http\Resources\ShipmentOrder\OpsShipmentOrderPriceSnapshotResource;
use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Models\ShipmentOrderPriceBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OpsShipmentOrderPriceSnapshotResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function sampleBreakdownJson(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'USD',
            'pricingPolicyId' => 1,
            'pricingPolicyName' => 'Standard 2026',
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
            'serviceItems' => [],
            'appliedRules' => ['base_rate:general/dry_van(exact)', 'discount:corporate'],
            'warnings' => [],
            'baseRate' => 1.45,
            'distanceTierRate' => 0.80,
            'baseRateResolutionType' => 'exact',
            'taxRate' => 8.0,
            'minimumFare' => null,
            'discountType' => 'percentage',
            'discountRate' => 5.0,
        ], $overrides);
    }

    private function makeBreakdownModel(array $jsonOverrides = []): ShipmentOrderPriceBreakdown
    {
        return new ShipmentOrderPriceBreakdown([
            'pricing_policy_id' => 1,
            'breakdown_json' => $this->sampleBreakdownJson($jsonOverrides),
            'final_total' => 1449.23,
            'currency' => 'USD',
            'calculated_at' => now()->setDate(2026, 8, 19)->setTime(18, 0, 0),
        ]);
    }

    public function test_resource_returns_ops_structure(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertArrayHasKey('pricing_policy', $result);
        $this->assertArrayHasKey('customer_type', $result);
        $this->assertArrayHasKey('distance', $result);
        $this->assertArrayHasKey('applied_rules', $result);
        $this->assertArrayHasKey('pricing_rule_resolution', $result);
    }

    public function test_pricing_policy_metadata_from_json(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame(1, $result['pricing_policy']['id']);
        $this->assertSame('Standard 2026', $result['pricing_policy']['name']);
        $this->assertSame('active_at_submission', $result['pricing_policy']['source']);
    }

    public function test_pricing_policy_name_falls_back_to_relation(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Fallback Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
        ]);

        $model = $this->makeBreakdownModel([
            'pricingPolicyId' => $policy->id,
            'pricingPolicyName' => null,
        ]);
        $model->pricing_policy_id = $policy->id;
        $model->setRelation('pricingPolicy', $policy);

        $result = (new OpsShipmentOrderPriceSnapshotResource($model))->toArray(Request::create('/'));

        $this->assertSame('Fallback Policy', $result['pricing_policy']['name']);
    }

    public function test_customer_type_from_persisted_input(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame('corporate', $result['customer_type']['value']);
        $this->assertSame('company_at_submission', $result['customer_type']['source']);
    }

    public function test_distance_source_is_route_at_submission(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame(500.0, $result['distance']['distance_km']);
        $this->assertSame('route_at_submission', $result['distance']['distance_source']);
    }

    public function test_applied_rules_exposed(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertContains('base_rate:general/dry_van(exact)', $result['applied_rules']);
    }

    public function test_pricing_rule_resolution_exposed(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame(1.45, $result['pricing_rule_resolution']['base_rate']);
        $this->assertSame(0.80, $result['pricing_rule_resolution']['distance_tier_rate']);
        $this->assertSame('exact', $result['pricing_rule_resolution']['base_rate_resolution_type']);
        $this->assertSame(8.0, $result['pricing_rule_resolution']['tax_rate']);
    }

    public function test_raw_input_object_not_exposed(): void
    {
        $result = (new OpsShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('input', $result);
        $this->assertArrayNotHasKey('breakdown_json', $result);
    }

    public function test_malformed_json_returns_degraded_fallback_with_detail_status(): void
    {
        $model = new ShipmentOrderPriceBreakdown([
            'breakdown_json' => null,
            'final_total' => 500.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $result = (new OpsShipmentOrderPriceSnapshotResource($model))->toArray(Request::create('/'));

        $this->assertSame('degraded', $result['detail_status']);
        $this->assertSame(500.0, $result['total']);
        $this->assertSame(['Price breakdown detail unavailable'], $result['warnings']);
    }
}
