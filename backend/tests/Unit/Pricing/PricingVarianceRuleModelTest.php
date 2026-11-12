<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingPolicy;
use App\Models\PricingVarianceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingVarianceRuleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_variance_rule_belongs_to_pricing_policy(): void
    {
        $policy = $this->makePolicy();
        $rule = $this->makeRule($policy, VarianceFactorCode::DISTANCE);

        $this->assertTrue($rule->pricingPolicy->is($policy));
    }

    public function test_pricing_policy_has_many_variance_rules(): void
    {
        $policy = $this->makePolicy();
        $this->makeRule($policy, VarianceFactorCode::DISTANCE);
        $this->makeRule($policy, VarianceFactorCode::WEIGHT, [
            'threshold_value' => 2.0,
        ]);

        $policy->load('varianceRules');

        $this->assertCount(2, $policy->varianceRules);
    }

    public function test_unique_policy_factor_constraint(): void
    {
        $policy = $this->makePolicy();
        $this->makeRule($policy, VarianceFactorCode::DISTANCE);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeRule($policy, VarianceFactorCode::DISTANCE, [
            'threshold_value' => 99,
        ]);
    }

    private function makePolicy(): PricingPolicy
    {
        return PricingPolicy::create([
            'name' => 'Variance Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRule(PricingPolicy $policy, VarianceFactorCode $factor, array $overrides = []): PricingVarianceRule
    {
        return PricingVarianceRule::create(array_merge([
            'pricing_policy_id' => $policy->id,
            'factor_code' => $factor,
            'threshold_type' => VarianceThresholdType::ABSOLUTE,
            'threshold_value' => 50,
            'applies_to_increase' => true,
            'applies_to_decrease' => false,
            'enabled' => true,
        ], $overrides));
    }
}
