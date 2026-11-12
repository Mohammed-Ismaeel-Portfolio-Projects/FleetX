<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingVarianceRule;
use Illuminate\Support\Collection;

/**
 * Evaluates material variance between submission baseline and operational actuals (Phase 5.5b).
 *
 * Does not calculate prices, persist snapshots, or invoke PricingEngine.
 */
class ShipmentPriceVarianceEvaluator
{
    /**
     * @param  iterable<int, PricingVarianceRule>  $rules
     */
    public function evaluate(
        ShipmentPriceVarianceInputs $baseline,
        ShipmentPriceVarianceInputs $actual,
        iterable $rules,
    ): ShipmentPriceVarianceEvaluationResult {
        $rulesByFactor = $this->indexEnabledRules($rules);

        $results = [];

        foreach (VarianceFactorCode::cases() as $factorCode) {
            $results[] = $this->evaluateFactor(
                $factorCode,
                $this->baselineValue($baseline, $factorCode),
                $this->actualValue($actual, $factorCode),
                $rulesByFactor->get($factorCode->value),
            );
        }

        return new ShipmentPriceVarianceEvaluationResult($results);
    }

    /**
     * @param  iterable<int, PricingVarianceRule>  $rules
     * @return Collection<string, PricingVarianceRule>
     */
    private function indexEnabledRules(iterable $rules): Collection
    {
        $indexed = collect($rules)
            ->filter(static fn (PricingVarianceRule $rule) => $rule->enabled)
            ->keyBy(static fn (PricingVarianceRule $rule) => $rule->factor_code->value);

        return $indexed;
    }

    private function evaluateFactor(
        VarianceFactorCode $factorCode,
        ?float $baselineValue,
        ?float $actualValue,
        ?PricingVarianceRule $rule,
    ): ShipmentPriceVarianceFactorResult {
        $deltaUnit = $this->deltaUnit($factorCode);

        if ($baselineValue === null || $actualValue === null) {
            return new ShipmentPriceVarianceFactorResult(
                factorCode: $factorCode,
                baselineValue: $baselineValue,
                actualValue: $actualValue,
                deltaValue: null,
                deltaUnit: $deltaUnit,
                thresholdValue: null,
                thresholdType: null,
                material: false,
                billable: false,
                direction: 'none',
                reasonCode: null,
                evaluationStatus: 'missing_value',
            );
        }

        $delta = (float) bcsub((string) $actualValue, (string) $baselineValue, 4);
        $direction = $this->directionForDelta($delta);

        if ($direction === 'none') {
            return new ShipmentPriceVarianceFactorResult(
                factorCode: $factorCode,
                baselineValue: $baselineValue,
                actualValue: $actualValue,
                deltaValue: 0.0,
                deltaUnit: $deltaUnit,
                thresholdValue: $rule?->threshold_value !== null ? (float) $rule->threshold_value : null,
                thresholdType: $rule?->threshold_type,
                material: false,
                billable: false,
                direction: 'none',
                reasonCode: null,
                evaluationStatus: $rule === null ? 'rule_missing' : 'evaluated',
            );
        }

        if ($rule === null) {
            return new ShipmentPriceVarianceFactorResult(
                factorCode: $factorCode,
                baselineValue: $baselineValue,
                actualValue: $actualValue,
                deltaValue: $delta,
                deltaUnit: $deltaUnit,
                thresholdValue: null,
                thresholdType: null,
                material: false,
                billable: false,
                direction: $direction,
                reasonCode: null,
                evaluationStatus: 'rule_missing',
            );
        }

        if ($direction === 'increase' && ! $rule->applies_to_increase) {
            return $this->nonApplicableDirectionResult($factorCode, $baselineValue, $actualValue, $delta, $deltaUnit, $rule, $direction);
        }

        if ($direction === 'decrease' && ! $rule->applies_to_decrease) {
            return $this->nonApplicableDirectionResult($factorCode, $baselineValue, $actualValue, $delta, $deltaUnit, $rule, $direction);
        }

        $thresholdValue = (float) $rule->threshold_value;
        $thresholdType = $rule->threshold_type;

        if ($thresholdType === VarianceThresholdType::PERCENTAGE && abs($baselineValue) < 1e-9) {
            return new ShipmentPriceVarianceFactorResult(
                factorCode: $factorCode,
                baselineValue: $baselineValue,
                actualValue: $actualValue,
                deltaValue: $delta,
                deltaUnit: $deltaUnit,
                thresholdValue: $thresholdValue,
                thresholdType: $thresholdType,
                material: false,
                billable: false,
                direction: $direction,
                reasonCode: null,
                evaluationStatus: 'baseline_zero',
            );
        }

        $material = $this->isMaterial($delta, $baselineValue, $thresholdType, $thresholdValue);

        return new ShipmentPriceVarianceFactorResult(
            factorCode: $factorCode,
            baselineValue: $baselineValue,
            actualValue: $actualValue,
            deltaValue: $delta,
            deltaUnit: $deltaUnit,
            thresholdValue: $thresholdValue,
            thresholdType: $thresholdType,
            material: $material,
            billable: $material,
            direction: $direction,
            reasonCode: $material ? $this->reasonCodeFor($factorCode) : null,
            evaluationStatus: 'evaluated',
            customerMessage: $material
                ? $this->interpolateTemplate($rule->customer_message_template, $baselineValue, $actualValue, $delta)
                : null,
            internalMessage: $material
                ? $this->interpolateTemplate($rule->internal_message_template, $baselineValue, $actualValue, $delta)
                : null,
        );
    }

    private function nonApplicableDirectionResult(
        VarianceFactorCode $factorCode,
        float $baselineValue,
        float $actualValue,
        float $delta,
        string $deltaUnit,
        PricingVarianceRule $rule,
        string $direction,
    ): ShipmentPriceVarianceFactorResult {
        return new ShipmentPriceVarianceFactorResult(
            factorCode: $factorCode,
            baselineValue: $baselineValue,
            actualValue: $actualValue,
            deltaValue: $delta,
            deltaUnit: $deltaUnit,
            thresholdValue: (float) $rule->threshold_value,
            thresholdType: $rule->threshold_type,
            material: false,
            billable: false,
            direction: $direction,
            reasonCode: null,
            evaluationStatus: 'direction_not_applicable',
        );
    }

    private function isMaterial(
        float $delta,
        float $baselineValue,
        VarianceThresholdType $thresholdType,
        float $thresholdValue,
    ): bool {
        $absDelta = abs($delta);

        if ($thresholdType === VarianceThresholdType::ABSOLUTE) {
            return $absDelta >= $thresholdValue;
        }

        $percentageDelta = abs($baselineValue) > 0
            ? ($absDelta / abs($baselineValue)) * 100
            : 0.0;

        return $percentageDelta >= $thresholdValue;
    }

    private function directionForDelta(float $delta): string
    {
        if (abs($delta) < 1e-9) {
            return 'none';
        }

        return $delta > 0 ? 'increase' : 'decrease';
    }

    private function baselineValue(ShipmentPriceVarianceInputs $inputs, VarianceFactorCode $factor): ?float
    {
        return match ($factor) {
            VarianceFactorCode::DISTANCE => $inputs->distanceKm,
            VarianceFactorCode::WEIGHT => $inputs->cargoWeight,
            VarianceFactorCode::WAITING_LOADING => $inputs->loadingWaitMinutes !== null
                ? (float) $inputs->loadingWaitMinutes
                : null,
            VarianceFactorCode::WAITING_UNLOADING => $inputs->unloadingWaitMinutes !== null
                ? (float) $inputs->unloadingWaitMinutes
                : null,
        };
    }

    private function actualValue(ShipmentPriceVarianceInputs $inputs, VarianceFactorCode $factor): ?float
    {
        return $this->baselineValue($inputs, $factor);
    }

    private function deltaUnit(VarianceFactorCode $factor): string
    {
        return match ($factor) {
            VarianceFactorCode::DISTANCE => 'km',
            VarianceFactorCode::WEIGHT => 'tons',
            VarianceFactorCode::WAITING_LOADING,
            VarianceFactorCode::WAITING_UNLOADING => 'minutes',
        };
    }

    private function reasonCodeFor(VarianceFactorCode $factor): string
    {
        return match ($factor) {
            VarianceFactorCode::DISTANCE => 'DISTANCE_EXCEEDED',
            VarianceFactorCode::WEIGHT => 'WEIGHT_EXCEEDED',
            VarianceFactorCode::WAITING_LOADING => 'WAITING_LOADING_EXCEEDED',
            VarianceFactorCode::WAITING_UNLOADING => 'WAITING_UNLOADING_EXCEEDED',
        };
    }

    private function interpolateTemplate(
        ?string $template,
        float $baseline,
        float $actual,
        float $delta,
    ): ?string {
        if ($template === null || trim($template) === '') {
            return null;
        }

        return str_replace(
            ['{{baseline}}', '{{actual}}', '{{delta}}'],
            [(string) $baseline, (string) $actual, (string) $delta],
            $template,
        );
    }
}
