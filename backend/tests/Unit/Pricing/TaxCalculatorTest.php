<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculator();
    }

    public function test_standard_8_percent_tax(): void
    {
        $policy = $this->createPolicy(8);

        $result = $this->calculator->calculate($policy, '1000');

        $this->assertEquals(80.0, $result->taxAmount);
        $this->assertEquals(1080.0, $result->totalWithTax);
    }

    public function test_zero_percent_tax(): void
    {
        $policy = $this->createPolicy(0);

        $result = $this->calculator->calculate($policy, '1000');

        $this->assertEquals(0.0, $result->taxAmount);
        $this->assertEquals(1000.0, $result->totalWithTax);
    }

    public function test_100_percent_tax(): void
    {
        $policy = $this->createPolicy(100);

        $result = $this->calculator->calculate($policy, '500');

        $this->assertEquals(500.0, $result->taxAmount);
        $this->assertEquals(1000.0, $result->totalWithTax);
    }

    public function test_decimal_after_discount(): void
    {
        $policy = $this->createPolicy(8);

        $result = $this->calculator->calculate($policy, '1234.5600');

        // 1234.56 × 8 / 100 = 98.7648
        $this->assertEqualsWithDelta(98.7648, $result->taxAmount, 0.0001);
    }

    public function test_decimal_tax_rate(): void
    {
        $policy = $this->createPolicy(7.5);

        $result = $this->calculator->calculate($policy, '1000');

        $this->assertEquals(75.0, $result->taxAmount);
    }

    public function test_negative_after_discount_rejected(): void
    {
        $policy = $this->createPolicy(8);

        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($policy, '-100');
    }

    public function test_negative_tax_rate_rejected(): void
    {
        $policy = $this->createPolicy(-5);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($policy, '1000');
    }

    public function test_tax_rate_over_100_rejected(): void
    {
        $policy = $this->createPolicy(150);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($policy, '1000');
    }

    public function test_tax_inclusive_rejected(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Inclusive Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8,
            'tax_inclusive' => true,
        ]);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($policy, '1000');
    }

    public function test_missing_tax_rate_rejected(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'No Tax Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
        ]);
        // Simulate null tax_rate by updating directly
        $policy->tax_rate = null;

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($policy, '1000');
    }

    public function test_bcmath_precision(): void
    {
        $policy = $this->createPolicy(8.3333);

        $result = $this->calculator->calculate($policy, '1000');

        // 1000 × 8.3333 / 100 = 83.333 at scale 4 = 83.3330
        $this->assertEqualsWithDelta(83.333, $result->taxAmount, 0.001);
    }

    public function test_no_premature_rounding(): void
    {
        $policy = $this->createPolicy(7.7777);

        $result = $this->calculator->calculate($policy, '1333');

        // 1333 × 7.7777 / 100 = 103.6767 at scale 4
        $this->assertEqualsWithDelta(103.6767, $result->taxAmount, 0.001);
    }

    public function test_zero_amount(): void
    {
        $policy = $this->createPolicy(8);

        $result = $this->calculator->calculate($policy, '0');

        $this->assertEquals(0.0, $result->taxAmount);
        $this->assertEquals(0.0, $result->totalWithTax);
    }

    private function createPolicy(float $taxRate): PricingPolicy
    {
        return PricingPolicy::create([
            'name' => 'Tax Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => $taxRate,
            'tax_inclusive' => false,
        ]);
    }
}
