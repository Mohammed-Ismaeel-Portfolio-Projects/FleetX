<?php

namespace Tests\Support;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\OperationalActualsCaptureSource;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\Company;
use App\Models\PricingBaseRate;
use App\Models\PricingDiscount;
use App\Models\PricingDistanceTier;
use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Models\PricingVarianceRule;
use App\Models\PricingWaitingConfig;
use App\Models\PricingWeightConfig;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\User;
use App\Services\Pricing\PricingBreakdownSerializer;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\PricingInputFactory;

trait CreatesFinalPricingFixtures
{
    protected function createFullPricingPolicy(): PricingPolicy
    {
        $policy = PricingPolicy::create([
            'name' => 'Final Pricing Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0000,
            'tax_inclusive' => false,
            'minimum_fare' => null,
        ]);

        foreach ([
            [CargoType::GENERAL, TrailerType::DRY_VAN, 1.4500],
            [CargoType::REFRIGERATED, TrailerType::REEFER, 1.9500],
        ] as [$cargo, $trailer, $rate]) {
            PricingBaseRate::create([
                'pricing_policy_id' => $policy->id,
                'cargo_type' => $cargo,
                'trailer_type' => $trailer,
                'rate_per_km' => $rate,
            ]);
        }

        foreach ([
            [0, 100, 1.2000],
            [101, 300, 0.9500],
            [301, 700, 0.8000],
            [701, null, 0.6500],
        ] as [$min, $max, $rate]) {
            PricingDistanceTier::create([
                'pricing_policy_id' => $policy->id,
                'min_distance_km' => $min,
                'max_distance_km' => $max,
                'rate_per_km' => $rate,
            ]);
        }

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

        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'border', 'name' => 'Border', 'fee_type' => 'flat', 'amount' => 150, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'insurance', 'name' => 'Insurance', 'fee_type' => 'percentage', 'amount' => 2, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'hazmat', 'name' => 'Hazmat', 'fee_type' => 'percentage', 'amount' => 25, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'heavy_reefer', 'name' => 'Heavy Reefer', 'fee_type' => 'percentage', 'amount' => 6, 'enabled' => true]);

        foreach (['standard' => 1.0, 'express' => 1.1, 'urgent' => 1.2, 'emergency' => 1.3] as $level => $mult) {
            PricingPriorityMultiplier::create([
                'pricing_policy_id' => $policy->id,
                'priority_level' => $level,
                'multiplier' => $mult,
            ]);
        }

        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'corporate', 'discount_type' => 'percentage', 'amount' => 5, 'enabled' => true]);

        return $policy;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createTierOneVarianceRules(PricingPolicy $policy, array $overrides = []): void
    {
        $defaults = [
            VarianceFactorCode::DISTANCE->value => [
                'threshold_type' => VarianceThresholdType::ABSOLUTE,
                'threshold_value' => 50,
            ],
            VarianceFactorCode::WEIGHT->value => [
                'threshold_type' => VarianceThresholdType::ABSOLUTE,
                'threshold_value' => 2,
            ],
            VarianceFactorCode::WAITING_LOADING->value => [
                'threshold_type' => VarianceThresholdType::ABSOLUTE,
                'threshold_value' => 30,
            ],
            VarianceFactorCode::WAITING_UNLOADING->value => [
                'threshold_type' => VarianceThresholdType::ABSOLUTE,
                'threshold_value' => 30,
            ],
        ];

        foreach ($defaults as $factor => $config) {
            $merged = array_merge([
                'pricing_policy_id' => $policy->id,
                'factor_code' => $factor,
                'applies_to_increase' => true,
                'applies_to_decrease' => false,
                'enabled' => true,
            ], $config, $overrides[$factor] ?? []);

            PricingVarianceRule::create($merged);
        }
    }

    protected function baselinePricingInput(float $distance = 500.0): PricingInput
    {
        return (new PricingInputFactory())->create(
            distance: $distance,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 15,
            shipmentPriority: 'standard',
            customerType: CustomerType::CORPORATE,
            insuranceRequired: true,
        );
    }

    protected function persistSubmissionSnapshot(
        ShipmentOrder $order,
        PricingPolicy $policy,
        ?PricingInput $input = null,
    ): ShipmentOrderPriceBreakdown {
        $input ??= $this->baselinePricingInput();
        $breakdown = app(PricingEngine::class)->calculate($input, $policy);

        return ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => PricingBreakdownSerializer::toArray($breakdown),
            'final_total' => $breakdown->finalTotal,
            'currency' => $breakdown->currency,
            'calculated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $actualOverrides
     * @param  array<string, mixed>  $shipmentOverrides
     */
    protected function createFinalizableShipment(
        PricingPolicy $policy,
        array $actualOverrides = [],
        array $shipmentOverrides = [],
    ): array {
        $company = Company::factory()->create(['customer_type' => CustomerType::CORPORATE]);

        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'cargo_type' => CargoType::GENERAL->value,
            'trailer_type' => TrailerType::DRY_VAN->value,
            'cargo_weight' => 15,
            'priority' => 'standard',
            'border_crossing' => false,
            'hazardous_cargo' => false,
            'insurance_required' => true,
        ]);

        $snapshot = $this->persistSubmissionSnapshot($order, $policy);

        $shipment = Shipment::factory()->completed()->create(array_merge([
            'customer_company_id' => $company->id,
            'shipment_order_id' => $order->id,
            'cargo_weight' => 15,
        ], $shipmentOverrides));

        $actuals = ShipmentOperationalActuals::create(array_merge([
            'shipment_id' => $shipment->id,
            'actual_distance_km' => 500,
            'actual_cargo_weight' => 15,
            'actual_loading_wait_minutes' => 0,
            'actual_unloading_wait_minutes' => 0,
            'capture_source' => OperationalActualsCaptureSource::OPS_MANUAL,
            'captured_at' => now(),
        ], $actualOverrides));

        return compact('company', 'order', 'snapshot', 'shipment', 'actuals', 'policy');
    }

    /**
     * Finalize shipment via service and return persisted breakdown.
     *
     * @param  array{shipment: Shipment, policy: PricingPolicy}  $fixture
     */
    protected function finalizeShipmentFixture(array $fixture, ?User $user = null): ShipmentFinalPriceBreakdown
    {
        if ($fixture['policy']->varianceRules()->count() === 0) {
            $this->createTierOneVarianceRules($fixture['policy']);
        }

        return app(\App\Services\Pricing\ShipmentFinalPricingService::class)->finalize(
            $fixture['shipment'],
            $user,
            \App\Enums\FinalPriceCalculationTrigger::MANUAL,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function sampleFinalBreakdownJson(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'USD',
            'pricingPolicyId' => 1,
            'pricingPolicyName' => 'Final Pricing Policy',
            'input' => [
                'distance' => 575.0,
                'cargoType' => 'general',
                'trailerType' => 'dry_van',
                'cargoWeight' => 15.0,
                'shipmentPriority' => 'standard',
                'customerType' => 'corporate',
                'borderCrossing' => false,
                'hazmat' => false,
                'insuranceRequired' => true,
                'selectedServices' => [],
                'loadingWaitingTime' => 0,
                'unloadingWaitingTime' => 0,
                'trafficDelay' => 0,
                'customerDelay' => 0,
            ],
            'freightCharge' => 1293.75,
            'weightSurcharge' => 0.0,
            'waitingCharge' => 0.0,
            'serviceCharges' => 0.0,
            'borderFee' => 0.0,
            'insuranceFee' => 25.875,
            'hazmatSurcharge' => 0.0,
            'heavyReeferSurcharge' => 0.0,
            'prioritySurcharge' => 0.0,
            'subtotal' => 1319.625,
            'discount' => 65.98125,
            'afterDiscount' => 1253.64375,
            'minimumFareAdjustment' => 0.0,
            'afterMinimumFare' => 1253.64375,
            'tax' => 100.2915,
            'finalTotal' => 1353.94,
            'serviceItems' => [],
            'appliedRules' => ['base_rate:general/dry_van(exact)'],
            'warnings' => [],
            'baseRate' => 1.45,
            'distanceTierRate' => 0.80,
            'baseRateResolutionType' => 'exact',
            'taxRate' => 8.0,
            'discountType' => 'percentage',
            'discountRate' => 5.0,
        ], $overrides);
    }
}
