<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingDiscount;
use App\Models\PricingPolicy;
use App\Services\Pricing\DiscountCalculator;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private DiscountCalculator $calculator;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DiscountCalculator();
        $this->policy = PricingPolicy::create([
            'name' => 'Discount Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
    }

    // ─── Basic Customer Types ────────────────────────────────────────

    public function test_standard_zero_discount(): void
    {
        $this->createDiscount('standard', 'percentage', 0);

        $result = $this->calculator->calculate($this->policy, 'standard', '1000');

        $this->assertEquals(0.0, $result->appliedDiscount);
        $this->assertEquals(1000.0, $result->afterDiscount);
    }

    public function test_corporate_5_percent(): void
    {
        $this->createDiscount('corporate', 'percentage', 5);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1000');

        $this->assertEquals(50.0, $result->appliedDiscount);
        $this->assertEquals(950.0, $result->afterDiscount);
    }

    public function test_vip_10_percent(): void
    {
        $this->createDiscount('vip', 'percentage', 10);

        $result = $this->calculator->calculate($this->policy, 'vip', '1000');

        $this->assertEquals(100.0, $result->appliedDiscount);
        $this->assertEquals(900.0, $result->afterDiscount);
    }

    public function test_long_term_8_percent(): void
    {
        $this->createDiscount('long_term', 'percentage', 8);

        $result = $this->calculator->calculate($this->policy, 'long_term', '1000');

        $this->assertEquals(80.0, $result->appliedDiscount);
        $this->assertEquals(920.0, $result->afterDiscount);
    }

    // ─── Percentage Calculations ─────────────────────────────────────

    public function test_percentage_on_decimal_subtotal(): void
    {
        $this->createDiscount('corporate', 'percentage', 5);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1234.5600');

        // 1234.56 × 5 / 100 = 61.728 at scale 4 = 61.7280
        $this->assertEqualsWithDelta(61.728, $result->appliedDiscount, 0.0001);
    }

    public function test_zero_percent_returns_zero(): void
    {
        $this->createDiscount('corporate', 'percentage', 0);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1000');

        $this->assertEquals(0.0, $result->appliedDiscount);
    }

    public function test_100_percent_returns_full_subtotal(): void
    {
        $this->createDiscount('vip', 'percentage', 100);

        $result = $this->calculator->calculate($this->policy, 'vip', '500');

        $this->assertEquals(500.0, $result->appliedDiscount);
        $this->assertEquals(0.0, $result->afterDiscount);
    }

    // ─── Max Discount Cap ────────────────────────────────────────────

    public function test_max_discount_caps_percentage(): void
    {
        $this->createDiscount('vip', 'percentage', 10, maxDiscount: 30);

        $result = $this->calculator->calculate($this->policy, 'vip', '1000');

        // 10% of 1000 = 100, capped at 30
        $this->assertEquals(30.0, $result->appliedDiscount);
        $this->assertTrue($result->capped);
        $this->assertEquals(970.0, $result->afterDiscount);
    }

    public function test_max_discount_does_not_affect_lower(): void
    {
        $this->createDiscount('corporate', 'percentage', 5, maxDiscount: 100);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1000');

        // 5% of 1000 = 50, below cap of 100
        $this->assertEquals(50.0, $result->appliedDiscount);
        $this->assertFalse($result->capped);
    }

    public function test_max_discount_zero_means_no_discount(): void
    {
        $this->createDiscount('vip', 'percentage', 10, maxDiscount: 0);

        $result = $this->calculator->calculate($this->policy, 'vip', '1000');

        $this->assertEquals(0.0, $result->appliedDiscount);
        $this->assertTrue($result->capped);
    }

    // ─── Subtotal Safety ─────────────────────────────────────────────

    public function test_discount_capped_to_subtotal(): void
    {
        $this->createDiscount('vip', 'flat', 500);

        $result = $this->calculator->calculate($this->policy, 'vip', '200');

        // flat 500 > subtotal 200 → capped to 200
        $this->assertEquals(200.0, $result->appliedDiscount);
        $this->assertTrue($result->capped);
        $this->assertEquals(0.0, $result->afterDiscount);
    }

    public function test_after_discount_never_negative(): void
    {
        $this->createDiscount('vip', 'percentage', 100);

        $result = $this->calculator->calculate($this->policy, 'vip', '100');

        $this->assertGreaterThanOrEqual(0.0, $result->afterDiscount);
    }

    // ─── Flat Discount ───────────────────────────────────────────────

    public function test_flat_discount(): void
    {
        $this->createDiscount('corporate', 'flat', 75);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1000');

        $this->assertEquals(75.0, $result->appliedDiscount);
        $this->assertEquals('flat', $result->discountType);
        $this->assertEquals(925.0, $result->afterDiscount);
    }

    public function test_flat_discount_exceeds_subtotal(): void
    {
        $this->createDiscount('corporate', 'flat', 500);

        $result = $this->calculator->calculate($this->policy, 'corporate', '100');

        $this->assertEquals(100.0, $result->appliedDiscount);
        $this->assertEquals(0.0, $result->afterDiscount);
    }

    public function test_flat_discount_with_max_discount(): void
    {
        $this->createDiscount('corporate', 'flat', 200, maxDiscount: 50);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1000');

        $this->assertEquals(50.0, $result->appliedDiscount);
        $this->assertTrue($result->capped);
    }

    // ─── No Stacking ─────────────────────────────────────────────────

    public function test_each_customer_type_resolves_independently(): void
    {
        $this->createDiscount('corporate', 'percentage', 5);
        $this->createDiscount('vip', 'percentage', 10);

        $corp = $this->calculator->calculate($this->policy, 'corporate', '1000');
        $vip = $this->calculator->calculate($this->policy, 'vip', '1000');

        $this->assertEquals(50.0, $corp->appliedDiscount);
        $this->assertEquals(100.0, $vip->appliedDiscount);
    }

    // ─── Missing Configuration ───────────────────────────────────────

    public function test_missing_discount_returns_zero_not_configured(): void
    {
        // No discount configured for 'standard'
        $result = $this->calculator->calculate($this->policy, 'standard', '1000');

        $this->assertFalse($result->configured);
        $this->assertEquals(0.0, $result->appliedDiscount);
        $this->assertEquals(1000.0, $result->afterDiscount);
    }

    public function test_disabled_discount_returns_zero(): void
    {
        $this->createDiscount('corporate', 'percentage', 5, enabled: false);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1000');

        $this->assertEquals(0.0, $result->appliedDiscount);
    }

    // ─── Validation ──────────────────────────────────────────────────

    public function test_negative_subtotal_rejected(): void
    {
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($this->policy, 'corporate', '-100');
    }

    public function test_invalid_customer_type_rejected(): void
    {
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($this->policy, 'invalid_type', '1000');
    }

    public function test_negative_amount_rejected(): void
    {
        $this->createDiscount('corporate', 'percentage', -5);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'corporate', '1000');
    }

    public function test_percentage_over_100_rejected(): void
    {
        $this->createDiscount('corporate', 'percentage', 150);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'corporate', '1000');
    }

    public function test_invalid_discount_type_rejected(): void
    {
        PricingDiscount::create([
            'pricing_policy_id' => $this->policy->id,
            'customer_type' => 'corporate',
            'discount_type' => 'invalid',
            'amount' => 5,
            'enabled' => true,
        ]);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'corporate', '1000');
    }

    public function test_negative_max_discount_rejected(): void
    {
        $this->createDiscount('corporate', 'percentage', 5, maxDiscount: -10);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'corporate', '1000');
    }

    // ─── Precision ───────────────────────────────────────────────────

    public function test_bcmath_precision(): void
    {
        $this->createDiscount('corporate', 'percentage', 7.5);

        $result = $this->calculator->calculate($this->policy, 'corporate', '1333');

        // 1333 × 7.5 / 100 = 99.975 at scale 4 = 99.9750
        $this->assertEqualsWithDelta(99.975, $result->appliedDiscount, 0.0001);
    }

    public function test_no_premature_rounding(): void
    {
        $this->createDiscount('vip', 'percentage', 3.3333);

        $result = $this->calculator->calculate($this->policy, 'vip', '1000');

        // 1000 × 3.3333 / 100 = 33.333 at scale 4 = 33.3330
        $this->assertEqualsWithDelta(33.333, $result->appliedDiscount, 0.001);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createDiscount(
        string $customerType,
        string $discountType,
        float $amount,
        ?float $maxDiscount = null,
        bool $enabled = true,
    ): void {
        PricingDiscount::create([
            'pricing_policy_id' => $this->policy->id,
            'customer_type' => $customerType,
            'discount_type' => $discountType,
            'amount' => $amount,
            'max_discount' => $maxDiscount,
            'enabled' => $enabled,
        ]);
    }
}
