<?php

namespace Tests\Unit\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingPolicy;
use App\Models\PricingVarianceRule;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\ShipmentOrderPricingService;
use App\Services\Pricing\ShipmentPriceVarianceEvaluationResult;
use App\Services\Pricing\ShipmentPriceVarianceEvaluator;
use App\Services\Pricing\ShipmentPriceVarianceFactorResult;
use App\Services\Pricing\ShipmentPriceVarianceInputs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ShipmentPriceVarianceEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentPriceVarianceEvaluator $evaluator;

    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new ShipmentPriceVarianceEvaluator();
        $this->policy = PricingPolicy::create([
            'name' => 'Evaluator Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
        ]);
    }

    public function test_absolute_delta_below_threshold_is_immaterial(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 50)];

        $result = $this->evaluateDistance(500, 540, $rules);
        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);

        $this->assertFalse($factor->material);
        $this->assertFalse($factor->billable);
        $this->assertSame('evaluated', $factor->evaluationStatus);
    }

    public function test_absolute_delta_exactly_at_threshold_is_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 50)];

        $result = $this->evaluateDistance(500, 550, $rules);
        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);

        $this->assertTrue($factor->material);
        $this->assertSame('DISTANCE_EXCEEDED', $factor->reasonCode);
    }

    public function test_absolute_delta_above_threshold_is_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 50)];

        $result = $this->evaluateDistance(500, 575, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertTrue($factor->material);
        $this->assertSame(75.0, $factor->deltaValue);
    }

    public function test_percentage_delta_below_threshold_is_immaterial(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::PERCENTAGE, 10)];

        $result = $this->evaluateDistance(500, 540, $rules);

        $this->assertFalse($this->factor($result, VarianceFactorCode::DISTANCE)->material);
    }

    public function test_percentage_delta_exactly_at_threshold_is_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::PERCENTAGE, 10)];

        $result = $this->evaluateDistance(500, 550, $rules);

        $this->assertTrue($this->factor($result, VarianceFactorCode::DISTANCE)->material);
    }

    public function test_percentage_delta_above_threshold_is_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::PERCENTAGE, 10)];

        $result = $this->evaluateDistance(500, 560, $rules);

        $this->assertTrue($this->factor($result, VarianceFactorCode::DISTANCE)->material);
    }

    public function test_increase_allowed_when_applies_to_increase_true(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10, [
            'applies_to_increase' => true,
        ])];

        $result = $this->evaluateDistance(500, 520, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('increase', $factor->direction);
        $this->assertTrue($factor->material);
    }

    public function test_increase_disabled_when_applies_to_increase_false(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10, [
            'applies_to_increase' => false,
            'applies_to_decrease' => true,
        ])];

        $result = $this->evaluateDistance(500, 520, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('direction_not_applicable', $factor->evaluationStatus);
        $this->assertFalse($factor->material);
    }

    public function test_decrease_allowed_when_applies_to_decrease_true(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10, [
            'applies_to_increase' => false,
            'applies_to_decrease' => true,
        ])];

        $result = $this->evaluateDistance(500, 480, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('decrease', $factor->direction);
        $this->assertTrue($factor->material);
    }

    public function test_decrease_disabled_when_applies_to_decrease_false(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10, [
            'applies_to_decrease' => false,
        ])];

        $result = $this->evaluateDistance(500, 480, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('direction_not_applicable', $factor->evaluationStatus);
        $this->assertFalse($factor->material);
    }

    public function test_zero_delta_is_never_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 1)];

        $result = $this->evaluateDistance(500, 500, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('none', $factor->direction);
        $this->assertFalse($factor->material);
    }

    public function test_percentage_with_zero_baseline_is_not_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::PERCENTAGE, 5)];

        $result = $this->evaluateDistance(0, 100, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('baseline_zero', $factor->evaluationStatus);
        $this->assertFalse($factor->material);
    }

    public function test_missing_rule_returns_rule_missing_status(): void
    {
        $result = $this->evaluateDistance(500, 575, []);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('rule_missing', $factor->evaluationStatus);
        $this->assertFalse($factor->material);
        $this->assertNull($factor->thresholdValue);
    }

    public function test_disabled_rule_is_treated_as_missing(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10, [
            'enabled' => false,
        ])];

        $result = $this->evaluateDistance(500, 575, $rules);

        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);
        $this->assertSame('rule_missing', $factor->evaluationStatus);
        $this->assertFalse($factor->material);
    }

    public function test_multiple_factors_evaluated_independently(): void
    {
        $rules = [
            $this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 50),
            $this->rule(VarianceFactorCode::WEIGHT, VarianceThresholdType::ABSOLUTE, 2),
        ];

        $baseline = new ShipmentPriceVarianceInputs(distanceKm: 500, cargoWeight: 15);
        $actual = new ShipmentPriceVarianceInputs(distanceKm: 575, cargoWeight: 15.5);

        $result = $this->evaluator->evaluate($baseline, $actual, $rules);

        $this->assertTrue($this->factor($result, VarianceFactorCode::DISTANCE)->material);
        $this->assertFalse($this->factor($result, VarianceFactorCode::WEIGHT)->material);
    }

    public function test_message_templates_are_interpolated_when_material(): void
    {
        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10, [
            'customer_message_template' => 'Distance changed from {{baseline}} to {{actual}}.',
            'internal_message_template' => 'Delta {{delta}} km.',
        ])];

        $result = $this->evaluateDistance(500, 520, $rules);
        $factor = $this->factor($result, VarianceFactorCode::DISTANCE);

        $this->assertSame('Distance changed from 500 to 520.', $factor->customerMessage);
        $this->assertSame('Delta 20 km.', $factor->internalMessage);
    }

    public function test_evaluator_does_not_invoke_pricing_engine(): void
    {
        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engineMock);

        $serviceMock = Mockery::mock(ShipmentOrderPricingService::class);
        $serviceMock->shouldNotReceive('calculateAndPersistSnapshot');
        $this->app->instance(ShipmentOrderPricingService::class, $serviceMock);

        $rules = [$this->rule(VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 10)];
        $this->evaluateDistance(500, 520, $rules);

        $before = ShipmentOrderPriceBreakdown::query()->count();
        $this->assertSame($before, ShipmentOrderPriceBreakdown::query()->count());
    }

    private function factor(
        ShipmentPriceVarianceEvaluationResult $result,
        VarianceFactorCode $factorCode,
    ): ShipmentPriceVarianceFactorResult {
        foreach ($result->factors as $factor) {
            if ($factor->factorCode === $factorCode) {
                return $factor;
            }
        }

        $this->fail('Factor '.$factorCode->value.' not found in evaluation result.');
    }

    /**
     * @param  array<int, PricingVarianceRule>  $rules
     */
    private function evaluateDistance(float $baseline, float $actual, array $rules): ShipmentPriceVarianceEvaluationResult
    {
        return $this->evaluator->evaluate(
            new ShipmentPriceVarianceInputs(distanceKm: $baseline),
            new ShipmentPriceVarianceInputs(distanceKm: $actual),
            $rules,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rule(
        VarianceFactorCode $factor,
        VarianceThresholdType $type,
        float $threshold,
        array $overrides = [],
    ): PricingVarianceRule {
        return PricingVarianceRule::create(array_merge([
            'pricing_policy_id' => $this->policy->id,
            'factor_code' => $factor,
            'threshold_type' => $type,
            'threshold_value' => $threshold,
            'applies_to_increase' => true,
            'applies_to_decrease' => false,
            'enabled' => true,
        ], $overrides));
    }
}
