<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Models\PricingWeightConfig;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\WeightSurchargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightSurchargeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private WeightSurchargeCalculator $calculator;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WeightSurchargeCalculator();
        $this->policy = PricingPolicy::create([
            'name' => 'Weight Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
    }

    // ─── Standard Calculations ───────────────────────────────────────

    public function test_weight_below_threshold_returns_zero(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(15, $config);

        $this->assertEquals(0.0, $result->surcharge);
        $this->assertEquals(0.0, $result->excessWeight);
        $this->assertEquals(15.0, $result->cargoWeight);
    }

    public function test_weight_exactly_at_threshold_returns_zero(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(20, $config);

        $this->assertEquals(0.0, $result->surcharge);
        $this->assertEquals(0.0, $result->excessWeight);
    }

    public function test_small_excess_decimal_weight(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(20.5, $config);

        $this->assertEquals(0.5, $result->excessWeight);
        $this->assertEquals(25.0, $result->surcharge);
    }

    public function test_standard_excess(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(25, $config);

        $this->assertEquals(5.0, $result->excessWeight);
        $this->assertEquals(250.0, $result->surcharge);
    }

    public function test_large_excess_no_cap(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(40, $config);

        $this->assertEquals(20.0, $result->excessWeight);
        $this->assertEquals(1000.0, $result->surcharge);
    }

    public function test_very_large_excess_no_cap(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(100, $config);

        $this->assertEquals(80.0, $result->excessWeight);
        $this->assertEquals(4000.0, $result->surcharge);
    }

    public function test_different_configuration(): void
    {
        $config = $this->createConfig(10, 75);

        $result = $this->calculator->calculate(25, $config);

        $this->assertEquals(15.0, $result->excessWeight);
        $this->assertEquals(1125.0, $result->surcharge);
    }

    public function test_decimal_rate(): void
    {
        $config = $this->createConfig(20, 50.50);

        $result = $this->calculator->calculate(25, $config);

        $this->assertEquals(5.0, $result->excessWeight);
        $this->assertEquals(252.50, $result->surcharge);
    }

    public function test_zero_weight_returns_zero(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(0, $config);

        $this->assertEquals(0.0, $result->surcharge);
    }

    public function test_zero_charge_per_ton_returns_zero_surcharge(): void
    {
        $config = $this->createConfig(20, 0);

        $result = $this->calculator->calculate(30, $config);

        $this->assertEquals(10.0, $result->excessWeight);
        $this->assertEquals(0.0, $result->surcharge);
    }

    // ─── Decimal Precision ───────────────────────────────────────────

    public function test_decimal_precision_not_prematurely_rounded(): void
    {
        $config = $this->createConfig(20, 33.33);

        $result = $this->calculator->calculate(23.7, $config);

        // excess = 3.7, surcharge = 3.7 × 33.33 = 123.321
        $this->assertEquals(3.7, $result->excessWeight);
        // bcmul with scale 4: 3.7000 × 33.33 = 123.3210
        $this->assertEqualsWithDelta(123.321, $result->surcharge, 0.001);
    }

    // ─── Validation: Invalid Input ───────────────────────────────────

    public function test_negative_weight_throws(): void
    {
        $config = $this->createConfig(20, 50);

        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate(-1, $config);
    }

    // ─── Validation: Invalid Config ──────────────────────────────────

    public function test_zero_threshold_throws(): void
    {
        $config = $this->createConfig(0, 50);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate(25, $config);
    }

    public function test_negative_threshold_throws(): void
    {
        $config = $this->createConfig(-5, 50);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate(25, $config);
    }

    public function test_negative_charge_per_ton_throws(): void
    {
        $config = $this->createConfig(20, -10);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate(25, $config);
    }

    // ─── Policy Isolation ────────────────────────────────────────────

    public function test_policy_isolation(): void
    {
        $configA = $this->createConfig(20, 50);

        $policyB = PricingPolicy::create([
            'name' => 'Policy B',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
        $configB = PricingWeightConfig::create([
            'pricing_policy_id' => $policyB->id,
            'threshold_tons' => 10,
            'charge_per_ton' => 100,
        ]);

        $resultA = $this->calculator->calculate(25, $configA);
        $resultB = $this->calculator->calculate(25, $configB);

        $this->assertEquals(250.0, $resultA->surcharge); // (25-20)×50
        $this->assertEquals(1500.0, $resultB->surcharge); // (25-10)×100
    }

    // ─── Result Properties ───────────────────────────────────────────

    public function test_result_exposes_all_properties(): void
    {
        $config = $this->createConfig(20, 50);

        $result = $this->calculator->calculate(25, $config);

        $this->assertEquals(25.0, $result->cargoWeight);
        $this->assertEquals(20.0, $result->thresholdTons);
        $this->assertEquals(5.0, $result->excessWeight);
        $this->assertEquals(50.0, $result->chargePerTon);
        $this->assertEquals(250.0, $result->surcharge);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createConfig(float $threshold, float $chargePerTon): PricingWeightConfig
    {
        return PricingWeightConfig::create([
            'pricing_policy_id' => $this->policy->id,
            'threshold_tons' => $threshold,
            'charge_per_ton' => $chargePerTon,
        ]);
    }
}
