<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Models\PricingWaitingConfig;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\WaitingChargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingChargeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private WaitingChargeCalculator $calculator;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WaitingChargeCalculator();
        $this->policy = PricingPolicy::create([
            'name' => 'Waiting Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
    }

    // ─── Disabled ────────────────────────────────────────────────────

    public function test_disabled_config_returns_zero(): void
    {
        $config = $this->createConfig(30, 30, 15, 200, false);

        $result = $this->calculator->calculate($config, 90, 90, 30, 30);

        $this->assertEquals(0.0, $result->finalCharge);
        $this->assertFalse($result->enabled);
    }

    // ─── Loading Within Free Time ────────────────────────────────────

    public function test_loading_within_free_time(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 30, 0);

        $this->assertEquals(0.0, $result->loadingCharge);
        $this->assertEquals(0, $result->loadingExcessMinutes);
    }

    public function test_unloading_within_free_time(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 0, 30);

        $this->assertEquals(0.0, $result->unloadingCharge);
    }

    // ─── Loading Increments ──────────────────────────────────────────

    public function test_one_minute_excess_loading(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 31, 0);

        $this->assertEquals(1, $result->loadingExcessMinutes);
        $this->assertEquals(15.0, $result->loadingCharge);
    }

    public function test_exactly_one_increment_loading(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 45, 0);

        $this->assertEquals(15, $result->loadingExcessMinutes);
        $this->assertEquals(15.0, $result->loadingCharge);
    }

    public function test_one_minute_into_second_increment(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 46, 0);

        $this->assertEquals(16, $result->loadingExcessMinutes);
        $this->assertEquals(30.0, $result->loadingCharge);
    }

    public function test_90_minute_loading(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 90, 0);

        // excess = 60, ceil(60/15)=4, 4×15=60
        $this->assertEquals(60, $result->loadingExcessMinutes);
        $this->assertEquals(60.0, $result->loadingCharge);
    }

    // ─── Combined Loading + Unloading ────────────────────────────────

    public function test_both_loading_and_unloading_excess(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 60, 60);

        // Each has 30 min excess: ceil(30/15)=2, 2×15=30
        $this->assertEquals(30.0, $result->loadingCharge);
        $this->assertEquals(30.0, $result->unloadingCharge);
        $this->assertEquals(60.0, $result->finalCharge);
    }

    // ─── Traffic Delay ───────────────────────────────────────────────

    public function test_traffic_delay(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 0, 0, 10);

        // 10 × (15/15) = 10
        $this->assertEquals(10.0, $result->trafficCharge);
        $this->assertEquals(10.0, $result->finalCharge);
    }

    public function test_customer_delay(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 0, 0, 0, 7);

        $this->assertEquals(7.0, $result->customerCharge);
    }

    public function test_traffic_plus_customer(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 0, 0, 10, 7);

        $this->assertEquals(10.0, $result->trafficCharge);
        $this->assertEquals(7.0, $result->customerCharge);
        $this->assertEquals(17.0, $result->finalCharge);
    }

    public function test_traffic_not_ceil_rounded(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 0, 0, 1);

        // 1 × (15/15) = $1, NOT $15
        $this->assertEquals(1.0, $result->trafficCharge);
    }

    // ─── Maximum Cap ─────────────────────────────────────────────────

    public function test_cap_applied_when_total_exceeds_max(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        // loading excess=120→8 increments→$120, unloading same→$120, total=$240 > $200
        $result = $this->calculator->calculate($config, 150, 150);

        $this->assertTrue($result->capped);
        $this->assertEquals(200.0, $result->finalCharge);
        $this->assertEquals(240.0, $result->totalBeforeCap);
    }

    public function test_exact_cap_boundary_not_capped(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        // Need total exactly = 200. loading excess=90→6 increments→$90, unloading same→$90
        // + traffic 20→$20 = total $200
        $result = $this->calculator->calculate($config, 120, 120, 20);

        $this->assertFalse($result->capped);
        $this->assertEquals(200.0, $result->finalCharge);
    }

    // ─── Full Combined ───────────────────────────────────────────────

    public function test_all_components_combined(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);

        $result = $this->calculator->calculate($config, 45, 60, 10, 7);

        // loading: excess=15, ceil(15/15)=1, $15
        // unloading: excess=30, ceil(30/15)=2, $30
        // traffic: 10×$1 = $10
        // customer: 7×$1 = $7
        // total = $62
        $this->assertEquals(15.0, $result->loadingCharge);
        $this->assertEquals(30.0, $result->unloadingCharge);
        $this->assertEquals(10.0, $result->trafficCharge);
        $this->assertEquals(7.0, $result->customerCharge);
        $this->assertEquals(62.0, $result->totalBeforeCap);
        $this->assertEquals(62.0, $result->finalCharge);
        $this->assertFalse($result->capped);
    }

    // ─── Input Validation ────────────────────────────────────────────

    public function test_negative_loading_rejected(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($config, -1, 0);
    }

    public function test_negative_unloading_rejected(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($config, 0, -1);
    }

    public function test_negative_traffic_rejected(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($config, 0, 0, -1);
    }

    public function test_negative_customer_rejected(): void
    {
        $config = $this->createConfig(30, 30, 15, 200);
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($config, 0, 0, 0, -1);
    }

    // ─── Config Validation ───────────────────────────────────────────

    public function test_negative_free_minutes_rejected(): void
    {
        $config = $this->createConfig(-1, 30, 15, 200);
        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($config, 10, 0);
    }

    public function test_negative_rate_rejected(): void
    {
        $config = $this->createConfig(30, 30, -1, 200);
        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($config, 10, 0);
    }

    public function test_negative_max_charge_rejected(): void
    {
        $config = $this->createConfig(30, 30, 15, -1);
        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($config, 10, 0);
    }

    // ─── Zero Rate ───────────────────────────────────────────────────

    public function test_zero_rate_returns_zero_charges(): void
    {
        $config = $this->createConfig(30, 30, 0, 200);

        $result = $this->calculator->calculate($config, 90, 90, 30, 30);

        $this->assertEquals(0.0, $result->finalCharge);
    }

    // ─── Decimal Rate / Precision ────────────────────────────────────

    public function test_decimal_rate_precision(): void
    {
        $config = $this->createConfig(30, 30, 10.50, 500);

        $result = $this->calculator->calculate($config, 0, 0, 7);

        // 7 × (10.50/15) = 7 × 0.7000 = 4.9000
        $this->assertEqualsWithDelta(4.9, $result->trafficCharge, 0.0001);
    }

    public function test_no_premature_rounding(): void
    {
        $config = $this->createConfig(30, 30, 10, 500);

        $result = $this->calculator->calculate($config, 0, 0, 7);

        // 7 × (10/15) = 7 × 0.6666 = 4.6662 (at scale 4)
        $this->assertEqualsWithDelta(4.6662, $result->trafficCharge, 0.0001);
    }

    // ─── No Hardcoded Defaults ───────────────────────────────────────

    public function test_custom_config_values_used(): void
    {
        $config = $this->createConfig(10, 20, 25, 500);

        $result = $this->calculator->calculate($config, 25, 35);

        // loading excess=15→1 increment→$25
        // unloading excess=15→1 increment→$25
        $this->assertEquals(25.0, $result->loadingCharge);
        $this->assertEquals(25.0, $result->unloadingCharge);
        $this->assertEquals(50.0, $result->finalCharge);
    }

    // ─── Policy Isolation ────────────────────────────────────────────

    public function test_policy_isolation(): void
    {
        $configA = $this->createConfig(30, 30, 15, 200);

        $policyB = PricingPolicy::create([
            'name' => 'Policy B',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
        $configB = PricingWaitingConfig::create([
            'pricing_policy_id' => $policyB->id,
            'loading_free_minutes' => 0,
            'unloading_free_minutes' => 0,
            'rate_per_15min' => 100,
            'max_charge' => 9999,
            'enabled' => true,
        ]);

        $resultA = $this->calculator->calculate($configA, 45, 0);
        $resultB = $this->calculator->calculate($configB, 45, 0);

        $this->assertEquals(15.0, $resultA->loadingCharge); // (45-30)→1 inc→$15
        $this->assertEquals(300.0, $resultB->loadingCharge); // (45-0)→3 inc→$300
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createConfig(
        int|float $loadingFree,
        int|float $unloadingFree,
        float $rate,
        float $maxCharge,
        bool $enabled = true,
    ): PricingWaitingConfig {
        return PricingWaitingConfig::create([
            'pricing_policy_id' => $this->policy->id,
            'loading_free_minutes' => $loadingFree,
            'unloading_free_minutes' => $unloadingFree,
            'rate_per_15min' => $rate,
            'max_charge' => $maxCharge,
            'enabled' => $enabled,
        ]);
    }
}
