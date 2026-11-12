<?php

namespace Tests\Unit\Pricing;

use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\MinimumFareEnforcer;
use Tests\TestCase;

class MinimumFareEnforcerTest extends TestCase
{
    private MinimumFareEnforcer $enforcer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enforcer = new MinimumFareEnforcer();
    }

    public function test_null_minimum_fare_unchanged(): void
    {
        $result = $this->enforcer->enforce('80', null);

        $this->assertEquals(80.0, $result->adjustedAmount);
        $this->assertNull($result->minimumFare);
        $this->assertFalse($result->applied);
    }

    public function test_amount_below_minimum_applies(): void
    {
        $result = $this->enforcer->enforce('80', '100');

        $this->assertEquals(80.0, $result->originalAmount);
        $this->assertEquals(100.0, $result->adjustedAmount);
        $this->assertTrue($result->applied);
    }

    public function test_amount_equal_minimum_unchanged(): void
    {
        $result = $this->enforcer->enforce('100', '100');

        $this->assertEquals(100.0, $result->adjustedAmount);
        $this->assertFalse($result->applied);
    }

    public function test_amount_above_minimum_unchanged(): void
    {
        $result = $this->enforcer->enforce('200', '100');

        $this->assertEquals(200.0, $result->adjustedAmount);
        $this->assertFalse($result->applied);
    }

    public function test_minimum_fare_zero(): void
    {
        $result = $this->enforcer->enforce('0', '0');

        $this->assertEquals(0.0, $result->adjustedAmount);
        $this->assertFalse($result->applied);
    }

    public function test_negative_amount_rejected(): void
    {
        $this->expectException(InvalidPricingInputException::class);
        $this->enforcer->enforce('-10', '100');
    }

    public function test_negative_minimum_fare_rejected(): void
    {
        $this->expectException(InvalidPricingConfigException::class);
        $this->enforcer->enforce('80', '-50');
    }

    public function test_decimal_minimum_fare(): void
    {
        $result = $this->enforcer->enforce('49.99', '50.50');

        $this->assertEquals(50.50, $result->adjustedAmount);
        $this->assertTrue($result->applied);
    }

    public function test_bcmath_precision(): void
    {
        $result = $this->enforcer->enforce('99.9999', '100.0000');

        $this->assertEquals(100.0, $result->adjustedAmount);
        $this->assertTrue($result->applied);
    }

    // ─── Integration Order Test ──────────────────────────────────────

    public function test_discount_then_minimum_then_tax_order(): void
    {
        // Simulate: subtotal=100, discount=10%, afterDiscount=90, min=150, tax=8%
        $afterDiscount = bcsub('100', bcdiv(bcmul('100', '10', 4), '100', 4), 4); // 90
        $this->assertEquals('90.0000', $afterDiscount);

        $fareResult = $this->enforcer->enforce($afterDiscount, '150');
        $this->assertTrue($fareResult->applied);
        $this->assertEquals(150.0, $fareResult->adjustedAmount);

        // Tax on adjusted amount
        $taxAmount = bcdiv(bcmul((string) $fareResult->adjustedAmount, '8', 4), '100', 4);
        $this->assertEquals('12.0000', $taxAmount);

        $final = bcadd((string) $fareResult->adjustedAmount, $taxAmount, 4);
        $this->assertEquals('162.0000', $final);
    }
}
