<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\MissingFeeConfigException;
use App\Services\Pricing\FeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private FeeCalculator $calculator;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new FeeCalculator();
        $this->policy = PricingPolicy::create([
            'name' => 'Fee Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
    }

    // ─── Border Fee ──────────────────────────────────────────────────

    public function test_border_not_triggered_returns_zero(): void
    {
        $this->createFee('border', 'Border Crossing', 'flat', 150);

        $result = $this->calculator->calculate($this->policy, 'border', false);

        $this->assertFalse($result->triggered);
        $this->assertEquals(0.0, $result->amount);
    }

    public function test_border_triggered_returns_flat_fee(): void
    {
        $this->createFee('border', 'Border Crossing', 'flat', 150);

        $result = $this->calculator->calculate($this->policy, 'border', true);

        $this->assertTrue($result->triggered);
        $this->assertEquals(150.0, $result->amount);
        $this->assertEquals('flat', $result->feeType);
    }

    public function test_border_missing_config_throws(): void
    {
        $this->expectException(MissingFeeConfigException::class);
        $this->calculator->calculate($this->policy, 'border', true);
    }

    public function test_border_disabled_returns_zero(): void
    {
        $this->createFee('border', 'Border Crossing', 'flat', 150, enabled: false);

        $result = $this->calculator->calculate($this->policy, 'border', true);

        $this->assertEquals(0.0, $result->amount);
    }

    // ─── Insurance Fee ───────────────────────────────────────────────

    public function test_insurance_not_triggered_returns_zero(): void
    {
        $this->createFee('insurance', 'Cargo Insurance', 'percentage', 2);

        $result = $this->calculator->calculate($this->policy, 'insurance', false, '1000');

        $this->assertEquals(0.0, $result->amount);
    }

    public function test_insurance_triggered_returns_percentage(): void
    {
        $this->createFee('insurance', 'Cargo Insurance', 'percentage', 2);

        $result = $this->calculator->calculate($this->policy, 'insurance', true, '1125');

        // 1125 × 2 / 100 = 22.5
        $this->assertEquals(22.5, $result->amount);
        $this->assertEquals('percentage', $result->feeType);
    }

    public function test_insurance_with_max_cap(): void
    {
        $this->createFee('insurance', 'Cargo Insurance', 'percentage', 2, maxAmount: 15);

        $result = $this->calculator->calculate($this->policy, 'insurance', true, '1125');

        // 1125 × 2% = 22.5, capped at 15
        $this->assertEquals(15.0, $result->amount);
        $this->assertTrue($result->capped);
    }

    public function test_insurance_missing_config_throws(): void
    {
        $this->expectException(MissingFeeConfigException::class);
        $this->calculator->calculate($this->policy, 'insurance', true, '1000');
    }

    public function test_insurance_zero_percentage(): void
    {
        $this->createFee('insurance', 'Cargo Insurance', 'percentage', 0);

        $result = $this->calculator->calculate($this->policy, 'insurance', true, '1000');

        $this->assertEquals(0.0, $result->amount);
    }

    // ─── Hazmat Surcharge ────────────────────────────────────────────

    public function test_hazmat_not_triggered_returns_zero(): void
    {
        $this->createFee('hazmat', 'Hazmat', 'percentage', 25);

        $result = $this->calculator->calculate($this->policy, 'hazmat', false, '1000');

        $this->assertEquals(0.0, $result->amount);
    }

    public function test_hazmat_triggered_returns_percentage(): void
    {
        $this->createFee('hazmat', 'Hazmat', 'percentage', 25);

        $result = $this->calculator->calculate($this->policy, 'hazmat', true, '1000');

        // 1000 × 25 / 100 = 250
        $this->assertEquals(250.0, $result->amount);
    }

    public function test_hazmat_with_max_cap(): void
    {
        $this->createFee('hazmat', 'Hazmat', 'percentage', 25, maxAmount: 100);

        $result = $this->calculator->calculate($this->policy, 'hazmat', true, '1000');

        $this->assertEquals(100.0, $result->amount);
        $this->assertTrue($result->capped);
    }

    public function test_hazmat_missing_config_throws(): void
    {
        $this->expectException(MissingFeeConfigException::class);
        $this->calculator->calculate($this->policy, 'hazmat', true, '500');
    }

    // ─── Heavy Reefer ────────────────────────────────────────────────

    public function test_heavy_reefer_triggered(): void
    {
        $this->createFee('heavy_reefer', 'Heavy Reefer', 'percentage', 6);

        $result = $this->calculator->calculate($this->policy, 'heavy_reefer', true, '1000');

        // 1000 × 6 / 100 = 60
        $this->assertEquals(60.0, $result->amount);
    }

    public function test_heavy_reefer_not_triggered(): void
    {
        $this->createFee('heavy_reefer', 'Heavy Reefer', 'percentage', 6);

        $result = $this->calculator->calculate($this->policy, 'heavy_reefer', false, '1000');

        $this->assertEquals(0.0, $result->amount);
    }

    public function test_heavy_reefer_missing_config_throws(): void
    {
        $this->expectException(MissingFeeConfigException::class);
        $this->calculator->calculate($this->policy, 'heavy_reefer', true, '1000');
    }

    // ─── Validation ──────────────────────────────────────────────────

    public function test_negative_freight_charge_rejected(): void
    {
        $this->createFee('insurance', 'Insurance', 'percentage', 2);

        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($this->policy, 'insurance', true, '-100');
    }

    public function test_percentage_over_100_rejected(): void
    {
        $this->createFee('hazmat', 'Hazmat', 'percentage', 150);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'hazmat', true, '1000');
    }

    public function test_negative_amount_rejected(): void
    {
        $this->createFee('border', 'Border', 'flat', -50);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'border', true);
    }

    public function test_negative_max_amount_rejected(): void
    {
        $this->createFee('insurance', 'Insurance', 'percentage', 2, maxAmount: -10);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'insurance', true, '1000');
    }

    // ─── Precision ───────────────────────────────────────────────────

    public function test_bcmath_precision(): void
    {
        $this->createFee('insurance', 'Insurance', 'percentage', 2.5);

        $result = $this->calculator->calculate($this->policy, 'insurance', true, '1333');

        // 1333 × 2.5 / 100 = 33.325 → at scale 4 = 33.3250
        $this->assertEqualsWithDelta(33.325, $result->amount, 0.0001);
    }

    // ─── Policy Isolation ────────────────────────────────────────────

    public function test_fee_policy_isolation(): void
    {
        $this->createFee('border', 'Border', 'flat', 150);

        $policyB = PricingPolicy::create([
            'name' => 'Policy B',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);

        $this->expectException(MissingFeeConfigException::class);
        $this->calculator->calculate($policyB, 'border', true);
    }

    // ─── Cap Not Triggered ───────────────────────────────────────────

    public function test_cap_not_triggered_when_below_max(): void
    {
        $this->createFee('insurance', 'Insurance', 'percentage', 2, maxAmount: 100);

        $result = $this->calculator->calculate($this->policy, 'insurance', true, '1000');

        // 1000 × 2% = 20, below cap of 100
        $this->assertEquals(20.0, $result->amount);
        $this->assertFalse($result->capped);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createFee(
        string $code,
        string $name,
        string $feeType,
        float $amount,
        ?float $maxAmount = null,
        bool $enabled = true,
    ): PricingFee {
        return PricingFee::create([
            'pricing_policy_id' => $this->policy->id,
            'code' => $code,
            'name' => $name,
            'fee_type' => $feeType,
            'amount' => $amount,
            'max_amount' => $maxAmount,
            'enabled' => $enabled,
        ]);
    }
}
