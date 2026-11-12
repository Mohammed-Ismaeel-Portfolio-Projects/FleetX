<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingPolicy;
use App\Models\PricingVarianceRule;
use App\Services\Pricing\PricingVarianceRuleValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingVarianceRuleValidatorTest extends TestCase
{
    use RefreshDatabase;

    private PricingVarianceRuleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new PricingVarianceRuleValidator();
    }

    public function test_valid_absolute_rule_passes(): void
    {
        $rule = $this->makeRule([
            'threshold_type' => VarianceThresholdType::ABSOLUTE,
            'threshold_value' => 50,
        ]);

        $this->assertSame([], $this->validator->validate($rule));
    }

    public function test_valid_percentage_rule_passes(): void
    {
        $rule = $this->makeRule([
            'threshold_type' => VarianceThresholdType::PERCENTAGE,
            'threshold_value' => 10.5,
        ]);

        $this->assertSame([], $this->validator->validate($rule));
    }

    public function test_negative_threshold_is_rejected(): void
    {
        $rule = $this->makeRule(['threshold_value' => -1]);

        $errors = $this->validator->validate($rule);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('negative', strtolower($errors[0]));
    }

    public function test_supported_factors_include_tier_one_codes(): void
    {
        $supported = array_map(
            static fn (VarianceFactorCode $code) => $code->value,
            PricingVarianceRuleValidator::supportedFactors()
        );

        $this->assertSame(
            ['distance', 'weight', 'waiting_loading', 'waiting_unloading'],
            $supported
        );
    }

    public function test_supported_threshold_types(): void
    {
        $types = array_map(
            static fn (VarianceThresholdType $type) => $type->value,
            PricingVarianceRuleValidator::supportedThresholdTypes()
        );

        $this->assertSame(['absolute', 'percentage'], $types);
    }

    public function test_boolean_fields_must_be_boolean(): void
    {
        $rule = $this->makeRule(['enabled' => 'yes']);

        $errors = $this->validator->validate($rule);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('boolean', strtolower(implode(' ', $errors)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRule(array $overrides = []): PricingVarianceRule
    {
        $policy = PricingPolicy::create([
            'name' => 'Validator Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
        ]);

        return new PricingVarianceRule(array_merge([
            'pricing_policy_id' => $policy->id,
            'factor_code' => VarianceFactorCode::DISTANCE,
            'threshold_type' => VarianceThresholdType::ABSOLUTE,
            'threshold_value' => 50,
            'applies_to_increase' => true,
            'applies_to_decrease' => false,
            'enabled' => true,
        ], $overrides));
    }
}
