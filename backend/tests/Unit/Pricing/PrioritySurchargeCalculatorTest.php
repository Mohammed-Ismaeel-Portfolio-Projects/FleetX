<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\MissingPriorityConfigException;
use App\Services\Pricing\PrioritySurchargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritySurchargeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PrioritySurchargeCalculator $calculator;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PrioritySurchargeCalculator();
        $this->policy = PricingPolicy::create([
            'name' => 'Priority Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
        $this->seedMultipliers();
    }

    // ─── Standard ────────────────────────────────────────────────────

    public function test_standard_returns_zero_surcharge(): void
    {
        $result = $this->calculator->calculate($this->policy, 'standard', '1000');

        $this->assertEquals(1.0, $result->multiplier);
        $this->assertEquals(0.0, $result->surcharge);
        $this->assertEquals('standard', $result->priorityLevel);
    }

    // ─── Express ─────────────────────────────────────────────────────

    public function test_express_surcharge(): void
    {
        $result = $this->calculator->calculate($this->policy, 'express', '1000');

        $this->assertEquals(1.1, $result->multiplier);
        // 1000 × (1.1 - 1) = 100
        $this->assertEquals(100.0, $result->surcharge);
    }

    // ─── Urgent ──────────────────────────────────────────────────────

    public function test_urgent_surcharge(): void
    {
        $result = $this->calculator->calculate($this->policy, 'urgent', '1000');

        $this->assertEquals(1.2, $result->multiplier);
        $this->assertEquals(200.0, $result->surcharge);
    }

    // ─── Emergency ───────────────────────────────────────────────────

    public function test_emergency_surcharge(): void
    {
        $result = $this->calculator->calculate($this->policy, 'emergency', '1000');

        $this->assertEquals(1.3, $result->multiplier);
        $this->assertEquals(300.0, $result->surcharge);
    }

    // ─── Validation ──────────────────────────────────────────────────

    public function test_multiplier_below_one_rejected(): void
    {
        PricingPriorityMultiplier::where('pricing_policy_id', $this->policy->id)
            ->where('priority_level', 'express')
            ->update(['multiplier' => 0.9]);

        $this->expectException(InvalidPricingConfigException::class);
        $this->calculator->calculate($this->policy, 'express', '1000');
    }

    public function test_missing_priority_config_throws(): void
    {
        $this->expectException(MissingPriorityConfigException::class);
        $this->calculator->calculate($this->policy, 'nonexistent', '1000');
    }

    public function test_negative_freight_charge_rejected(): void
    {
        $this->expectException(InvalidPricingInputException::class);
        $this->calculator->calculate($this->policy, 'standard', '-100');
    }

    // ─── Precision ───────────────────────────────────────────────────

    public function test_bcmath_precision(): void
    {
        // Use custom multiplier 1.15
        PricingPriorityMultiplier::where('pricing_policy_id', $this->policy->id)
            ->where('priority_level', 'express')
            ->update(['multiplier' => 1.1500]);

        $result = $this->calculator->calculate($this->policy, 'express', '1333');

        // 1333 × 0.15 = 199.95 at scale 4 = 199.9500
        $this->assertEqualsWithDelta(199.95, $result->surcharge, 0.0001);
    }

    // ─── Policy Isolation ────────────────────────────────────────────

    public function test_policy_isolation(): void
    {
        $policyB = PricingPolicy::create([
            'name' => 'Policy B',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
        PricingPriorityMultiplier::create([
            'pricing_policy_id' => $policyB->id,
            'priority_level' => 'express',
            'multiplier' => 2.0000,
        ]);

        $resultA = $this->calculator->calculate($this->policy, 'express', '1000');
        $resultB = $this->calculator->calculate($policyB, 'express', '1000');

        $this->assertEquals(100.0, $resultA->surcharge); // ×1.1
        $this->assertEquals(1000.0, $resultB->surcharge); // ×2.0
    }

    // ─── Zero Freight ────────────────────────────────────────────────

    public function test_zero_freight_returns_zero_surcharge(): void
    {
        $result = $this->calculator->calculate($this->policy, 'emergency', '0');

        $this->assertEquals(0.0, $result->surcharge);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function seedMultipliers(): void
    {
        $levels = [
            'standard' => 1.0000,
            'express' => 1.1000,
            'urgent' => 1.2000,
            'emergency' => 1.3000,
        ];

        foreach ($levels as $level => $multiplier) {
            PricingPriorityMultiplier::create([
                'pricing_policy_id' => $this->policy->id,
                'priority_level' => $level,
                'multiplier' => $multiplier,
            ]);
        }
    }
}
