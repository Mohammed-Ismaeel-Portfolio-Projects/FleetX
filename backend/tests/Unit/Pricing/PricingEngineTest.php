<?php

namespace Tests\Unit\Pricing;

use App\Enums\CargoType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\PricingBaseRate;
use App\Models\PricingDiscount;
use App\Models\PricingDistanceTier;
use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Models\PricingWaitingConfig;
use App\Models\PricingWeightConfig;
use App\Services\Pricing\DiscountCalculator;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\MissingBaseRateException;
use App\Services\Pricing\Exceptions\MissingDistanceTierException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\FeeCalculator;
use App\Services\Pricing\FreightRateResolver;
use App\Services\Pricing\MinimumFareEnforcer;
use App\Services\Pricing\PricingBreakdown;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\PrioritySurchargeCalculator;
use App\Services\Pricing\TaxCalculator;
use App\Services\Pricing\WaitingChargeCalculator;
use App\Services\Pricing\WeightSurchargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private PricingEngine $engine;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new PricingEngine(
            new FreightRateResolver(),
            new WeightSurchargeCalculator(),
            new WaitingChargeCalculator(),
            new FeeCalculator(),
            new PrioritySurchargeCalculator(),
            new DiscountCalculator(),
            new MinimumFareEnforcer(),
            new TaxCalculator(),
            new PricingPolicyResolver(),
        );

        $this->policy = $this->createFullPolicy();
    }

    // ─── Specification Numerical Cases ───────────────────────────────

    public function test_case_1_standard_shipment_baseline(): void
    {
        $input = new PricingInput(
            distance: 500,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 15,
            shipmentPriority: 'standard',
            customerType: 'corporate',
            insuranceRequired: true,
            selectedServices: [
                ['id' => '1', 'name' => 'Loading Crew', 'price' => 250],
                ['id' => '2', 'name' => 'GPS Seal', 'price' => 15],
            ],
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 500 × (1.45 + 0.80) = 1125
        $this->assertEqualsWithDelta(1125.0, $result->freightCharge, 0.01);
        $this->assertEquals(0.0, $result->weightSurcharge);
        $this->assertEqualsWithDelta(265.0, $result->serviceCharges, 0.01);
        // insurance = 1125 × 2 / 100 = 22.5
        $this->assertEqualsWithDelta(22.5, $result->insuranceFee, 0.01);
        // subtotal = 1125 + 0 + 0 + 265 + 0 + 22.5 + 0 + 0 + 0 = 1412.5
        $this->assertEqualsWithDelta(1412.5, $result->subtotal, 0.01);
        // discount = 1412.5 × 5% = 70.625
        $this->assertEqualsWithDelta(70.625, $result->discount, 0.01);
        // afterDiscount = 1341.875
        $this->assertEqualsWithDelta(1341.875, $result->afterDiscount, 0.01);
        // tax = 1341.875 × 8% = 107.35
        $this->assertEqualsWithDelta(107.35, $result->tax, 0.01);
        // final = 1341.875 + 107.35 = 1449.225 → round = 1449.23
        $this->assertEqualsWithDelta(1449.23, $result->finalTotal, 0.01);
    }

    public function test_case_2_refrigerated_long_distance(): void
    {
        $input = new PricingInput(
            distance: 800,
            cargoType: CargoType::REFRIGERATED,
            trailerType: TrailerType::REEFER,
            cargoWeight: 18,
            shipmentPriority: 'standard',
            customerType: 'standard',
            insuranceRequired: true,
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 800 × (1.95 + 0.65) = 2080
        $this->assertEqualsWithDelta(2080.0, $result->freightCharge, 0.01);
        // insurance = 2080 × 2% = 41.6
        $this->assertEqualsWithDelta(41.6, $result->insuranceFee, 0.01);
        // subtotal = 2080 + 41.6 = 2121.6
        $this->assertEqualsWithDelta(2121.6, $result->subtotal, 0.01);
        // no discount, tax = 2121.6 × 8% = 169.728
        $this->assertEqualsWithDelta(169.728, $result->tax, 0.01);
    }

    public function test_case_3_heavy_cargo(): void
    {
        $input = new PricingInput(
            distance: 200,
            cargoType: CargoType::HEAVY,
            trailerType: TrailerType::LOWBOY,
            cargoWeight: 30,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 200 × (2.85 + 0.95) = 760
        $this->assertEqualsWithDelta(760.0, $result->freightCharge, 0.01);
        // weight = (30-20) × 50 = 500
        $this->assertEqualsWithDelta(500.0, $result->weightSurcharge, 0.01);
        // subtotal = 1260, tax = 100.8
        $this->assertEqualsWithDelta(1260.0, $result->subtotal, 0.01);
    }

    public function test_case_4_short_distance_tier_1(): void
    {
        $input = new PricingInput(
            distance: 50,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 50 × (1.45 + 1.20) = 132.5
        $this->assertEqualsWithDelta(132.5, $result->freightCharge, 0.01);
    }

    public function test_case_5_excess_weight(): void
    {
        $input = new PricingInput(
            distance: 400,
            cargoType: CargoType::HEAVY,
            trailerType: TrailerType::FLATBED,
            cargoWeight: 45,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        // weight = (45-20) × 50 = 1250
        $this->assertEqualsWithDelta(1250.0, $result->weightSurcharge, 0.01);
    }

    public function test_case_6_waiting_time(): void
    {
        $input = new PricingInput(
            distance: 300,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
            loadingWaitingTime: 90,
            unloadingWaitingTime: 60,
        );

        $result = $this->engine->calculate($input, $this->policy);

        // loading excess=60→4 inc→$60, unloading excess=30→2 inc→$30 = $90
        $this->assertEqualsWithDelta(90.0, $result->waitingCharge, 0.01);
    }

    public function test_case_7_border_crossing(): void
    {
        $input = new PricingInput(
            distance: 600,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 15,
            shipmentPriority: 'standard',
            customerType: 'standard',
            borderCrossing: true,
        );

        $result = $this->engine->calculate($input, $this->policy);

        $this->assertEqualsWithDelta(150.0, $result->borderFee, 0.01);
    }

    public function test_case_8_hazmat(): void
    {
        $input = new PricingInput(
            distance: 350,
            cargoType: CargoType::HAZARDOUS,
            trailerType: TrailerType::TANKER,
            cargoWeight: 12,
            shipmentPriority: 'standard',
            customerType: 'standard',
            hazmat: true,
            insuranceRequired: true,
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 350 × (2.50 + 0.80) = 1155
        $this->assertEqualsWithDelta(1155.0, $result->freightCharge, 0.01);
        // insurance = 1155 × 2% = 23.1
        $this->assertEqualsWithDelta(23.1, $result->insuranceFee, 0.01);
        // hazmat = 1155 × 25% = 288.75
        $this->assertEqualsWithDelta(288.75, $result->hazmatSurcharge, 0.01);
    }

    public function test_case_9_corporate_discount(): void
    {
        $input = new PricingInput(
            distance: 1000,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 25,
            shipmentPriority: 'standard',
            customerType: 'corporate',
            borderCrossing: true,
            insuranceRequired: true,
            selectedServices: [
                ['id' => '1', 'name' => 'Loading', 'price' => 250],
                ['id' => '2', 'name' => 'Unloading', 'price' => 250],
            ],
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 1000 × (1.45 + 0.65) = 2100
        $this->assertEqualsWithDelta(2100.0, $result->freightCharge, 0.01);
        // weight = (25-20) × 50 = 250
        $this->assertEqualsWithDelta(250.0, $result->weightSurcharge, 0.01);
        // border = 150
        $this->assertEqualsWithDelta(150.0, $result->borderFee, 0.01);
        // insurance = 2100 × 2% = 42
        $this->assertEqualsWithDelta(42.0, $result->insuranceFee, 0.01);
        // services = 500
        // subtotal = 2100 + 250 + 0 + 500 + 150 + 42 + 0 + 0 + 0 = 3042
        $this->assertEqualsWithDelta(3042.0, $result->subtotal, 0.01);
        // discount = 3042 × 5% = 152.1
        $this->assertEqualsWithDelta(152.1, $result->discount, 0.01);
    }

    public function test_case_10_vip_emergency_priority(): void
    {
        $input = new PricingInput(
            distance: 250,
            cargoType: CargoType::REFRIGERATED,
            trailerType: TrailerType::REEFER,
            cargoWeight: 22,
            shipmentPriority: 'emergency',
            customerType: 'vip',
            borderCrossing: true,
            hazmat: true,
            insuranceRequired: true,
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 250 × (1.95 + 0.95) = 725
        $this->assertEqualsWithDelta(725.0, $result->freightCharge, 0.01);
        // weight = (22-20) × 50 = 100
        $this->assertEqualsWithDelta(100.0, $result->weightSurcharge, 0.01);
        // border = 150
        // insurance = 725 × 2% = 14.5
        $this->assertEqualsWithDelta(14.5, $result->insuranceFee, 0.01);
        // hazmat = 725 × 25% = 181.25
        $this->assertEqualsWithDelta(181.25, $result->hazmatSurcharge, 0.01);
        // priority = 725 × (1.3 - 1) = 217.5
        $this->assertEqualsWithDelta(217.5, $result->prioritySurcharge, 0.01);
        // discount = subtotal × 10%
        $this->assertEquals('vip', $result->input->customerType);
    }

    // ─── Boundary Tests ──────────────────────────────────────────────

    public function test_zero_distance(): void
    {
        $input = new PricingInput(
            distance: 0,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        $this->assertEquals(0.0, $result->freightCharge);
    }

    public function test_100km_boundary(): void
    {
        $input = $this->baseInput(distance: 100);
        $result = $this->engine->calculate($input, $this->policy);

        // tier 0-100 → 1.20
        $this->assertEqualsWithDelta(100 * (1.45 + 1.20), $result->freightCharge, 0.01);
    }

    public function test_101km_boundary(): void
    {
        $input = $this->baseInput(distance: 101);
        $result = $this->engine->calculate($input, $this->policy);

        // tier 101-300 → 0.95
        $this->assertEqualsWithDelta(101 * (1.45 + 0.95), $result->freightCharge, 0.01);
    }

    // ─── Error Cases ─────────────────────────────────────────────────

    public function test_missing_active_policy(): void
    {
        // Deactivate
        $this->policy->update(['status' => PricingPolicyStatus::DRAFT]);

        $input = $this->baseInput();

        $this->expectException(NoPricingPolicyException::class);
        $this->engine->calculate($input);
    }

    public function test_missing_base_rate(): void
    {
        $input = new PricingInput(
            distance: 100,
            cargoType: CargoType::FRAGILE, // no rate configured
            trailerType: TrailerType::TANKER,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $this->expectException(MissingBaseRateException::class);
        $this->engine->calculate($input, $this->policy);
    }

    public function test_cargo_only_fallback_rate(): void
    {
        // Add cargo-only fallback for fragile
        PricingBaseRate::create([
            'pricing_policy_id' => $this->policy->id,
            'cargo_type' => CargoType::FRAGILE,
            'trailer_type' => null,
            'rate_per_km' => 1.75,
        ]);

        $input = new PricingInput(
            distance: 100,
            cargoType: CargoType::FRAGILE,
            trailerType: TrailerType::FLATBED,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        $this->assertEquals('cargo_fallback', $result->baseRateResolutionType);
        $this->assertEqualsWithDelta(100 * (1.75 + 1.20), $result->freightCharge, 0.01);
    }

    public function test_missing_distance_tier(): void
    {
        // Delete tiers and add only partial
        $this->policy->distanceTiers()->delete();
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 50,
            'rate_per_km' => 1.20,
        ]);

        $input = $this->baseInput(distance: 200);

        $this->expectException(MissingDistanceTierException::class);
        $this->engine->calculate($input, $this->policy);
    }

    public function test_minimum_fare_applied(): void
    {
        $this->policy->update(['minimum_fare' => 500]);

        $input = $this->baseInput(distance: 50); // freight=132.5, subtotal≈132.5

        $result = $this->engine->calculate($input, $this->policy);

        $this->assertTrue($result->afterMinimumFare >= 500.0);
        $this->assertContains('minimum_fare', $result->appliedRules);
    }

    public function test_discount_max_cap(): void
    {
        PricingDiscount::where('pricing_policy_id', $this->policy->id)
            ->where('customer_type', 'vip')
            ->update(['max_discount' => 10]);

        $input = new PricingInput(
            distance: 500,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'vip',
        );

        $result = $this->engine->calculate($input, $this->policy);

        // 10% of ~1125 subtotal would be ~112.5, but capped at 10
        $this->assertEqualsWithDelta(10.0, $result->discount, 0.01);
    }

    public function test_priority_multiplier(): void
    {
        $input = new PricingInput(
            distance: 500,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'express',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 1125, priority = 1125 × 0.1 = 112.5
        $this->assertEqualsWithDelta(112.5, $result->prioritySurcharge, 0.01);
    }

    public function test_heavy_reefer_condition(): void
    {
        $input = new PricingInput(
            distance: 200,
            cargoType: CargoType::HEAVY,
            trailerType: TrailerType::REEFER,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        // Need heavy+reefer rate
        PricingBaseRate::create([
            'pricing_policy_id' => $this->policy->id,
            'cargo_type' => CargoType::HEAVY,
            'trailer_type' => TrailerType::REEFER,
            'rate_per_km' => 2.85,
        ]);

        $result = $this->engine->calculate($input, $this->policy);

        // freight = 200 × (2.85 + 0.95) = 760
        // heavy_reefer = 760 × 6% = 45.6
        $this->assertEqualsWithDelta(45.6, $result->heavyReeferSurcharge, 0.01);
    }

    public function test_insurance_applies_to_full_freight(): void
    {
        $input = new PricingInput(
            distance: 500,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
            insuranceRequired: true,
        );

        $result = $this->engine->calculate($input, $this->policy);

        // freight=1125, insurance=1125×2%=22.5
        $this->assertEqualsWithDelta(22.5, $result->insuranceFee, 0.01);
    }

    public function test_discount_before_minimum_fare(): void
    {
        $this->policy->update(['minimum_fare' => 1500]);

        $input = new PricingInput(
            distance: 500,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'vip', // 10% discount
        );

        $result = $this->engine->calculate($input, $this->policy);

        // subtotal=1125, discount=112.5, afterDiscount=1012.5, min=1500 → adjusted=1500
        $this->assertEqualsWithDelta(1125.0, $result->subtotal, 0.01);
        $this->assertEqualsWithDelta(112.5, $result->discount, 0.01);
        $this->assertEqualsWithDelta(1500.0, $result->afterMinimumFare, 0.01);
    }

    public function test_minimum_fare_before_tax(): void
    {
        $this->policy->update(['minimum_fare' => 200]);

        $input = $this->baseInput(distance: 50); // freight≈132.5 < 200

        $result = $this->engine->calculate($input, $this->policy);

        // afterMinimumFare = 200, tax = 200×8% = 16, final = 216
        $this->assertEqualsWithDelta(200.0, $result->afterMinimumFare, 0.01);
        $this->assertEqualsWithDelta(16.0, $result->tax, 0.01);
        $this->assertEqualsWithDelta(216.0, $result->finalTotal, 0.01);
    }

    public function test_final_round_half_up(): void
    {
        // Create a scenario producing a value with >2 decimals
        // freight = 333 × (1.45 + 0.95) = 333 × 2.40 = 799.2
        // tax = 799.2 × 8% = 63.936
        // final = 799.2 + 63.936 = 863.136 → round = 863.14
        $input = new PricingInput(
            distance: 333,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $result = $this->engine->calculate($input, $this->policy);

        // Verify final is exactly 2 decimals
        $this->assertEquals(round($result->finalTotal, 2), $result->finalTotal);
    }

    // ─── Input Validation ────────────────────────────────────────────

    public function test_negative_distance_rejected(): void
    {
        $input = new PricingInput(
            distance: -10,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $this->expectException(InvalidPricingInputException::class);
        $this->engine->calculate($input, $this->policy);
    }

    public function test_negative_weight_rejected(): void
    {
        $input = new PricingInput(
            distance: 100,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: -5,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );

        $this->expectException(InvalidPricingInputException::class);
        $this->engine->calculate($input, $this->policy);
    }

    // ─── Metadata ────────────────────────────────────────────────────

    public function test_breakdown_contains_policy_metadata(): void
    {
        $input = $this->baseInput();
        $result = $this->engine->calculate($input, $this->policy);

        $this->assertEquals($this->policy->id, $result->pricingPolicyId);
        $this->assertEquals($this->policy->name, $result->pricingPolicyName);
        $this->assertEquals('USD', $result->currency);
        $this->assertEquals(8.0, $result->taxRate);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function baseInput(float $distance = 500): PricingInput
    {
        return new PricingInput(
            distance: $distance,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: 'standard',
        );
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

        // Base rates
        $rates = [
            [CargoType::GENERAL, TrailerType::DRY_VAN, 1.4500],
            [CargoType::REFRIGERATED, TrailerType::REEFER, 1.9500],
            [CargoType::HEAVY, TrailerType::LOWBOY, 2.8500],
            [CargoType::HEAVY, TrailerType::FLATBED, 2.8500],
            [CargoType::HAZARDOUS, TrailerType::TANKER, 2.5000],
        ];
        foreach ($rates as [$cargo, $trailer, $rate]) {
            PricingBaseRate::create([
                'pricing_policy_id' => $policy->id,
                'cargo_type' => $cargo,
                'trailer_type' => $trailer,
                'rate_per_km' => $rate,
            ]);
        }

        // Distance tiers
        $tiers = [
            [0, 100, 1.2000],
            [101, 300, 0.9500],
            [301, 700, 0.8000],
            [701, null, 0.6500],
        ];
        foreach ($tiers as [$min, $max, $rate]) {
            PricingDistanceTier::create([
                'pricing_policy_id' => $policy->id,
                'min_distance_km' => $min,
                'max_distance_km' => $max,
                'rate_per_km' => $rate,
            ]);
        }

        // Weight config
        PricingWeightConfig::create([
            'pricing_policy_id' => $policy->id,
            'threshold_tons' => 20,
            'charge_per_ton' => 50,
        ]);

        // Waiting config
        PricingWaitingConfig::create([
            'pricing_policy_id' => $policy->id,
            'loading_free_minutes' => 30,
            'unloading_free_minutes' => 30,
            'rate_per_15min' => 15,
            'max_charge' => 200,
            'enabled' => true,
        ]);

        // Fees
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'border', 'name' => 'Border Crossing', 'fee_type' => 'flat', 'amount' => 150, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'insurance', 'name' => 'Cargo Insurance', 'fee_type' => 'percentage', 'amount' => 2, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'hazmat', 'name' => 'Hazmat Surcharge', 'fee_type' => 'percentage', 'amount' => 25, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'heavy_reefer', 'name' => 'Heavy Reefer', 'fee_type' => 'percentage', 'amount' => 6, 'enabled' => true]);

        // Priority multipliers
        $multipliers = ['standard' => 1.0, 'express' => 1.1, 'urgent' => 1.2, 'emergency' => 1.3];
        foreach ($multipliers as $level => $mult) {
            PricingPriorityMultiplier::create([
                'pricing_policy_id' => $policy->id,
                'priority_level' => $level,
                'multiplier' => $mult,
            ]);
        }

        // Discounts
        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'corporate', 'discount_type' => 'percentage', 'amount' => 5, 'enabled' => true]);
        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'vip', 'discount_type' => 'percentage', 'amount' => 10, 'enabled' => true]);
        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'long_term', 'discount_type' => 'percentage', 'amount' => 8, 'enabled' => true]);

        return $policy;
    }
}
