<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingVarianceRule;

class PricingVarianceRuleValidator
{
    /**
     * @return string[]
     */
    public function validate(PricingVarianceRule $rule): array
    {
        $errors = [];

        if ($rule->factor_code === null) {
            $errors[] = 'Factor code is required.';
        } elseif (! in_array($rule->factor_code, self::supportedFactors(), true)) {
            $errors[] = 'Factor code is not supported.';
        }

        if ($rule->threshold_type === null) {
            $errors[] = 'Threshold type is required.';
        } elseif (! in_array($rule->threshold_type, self::supportedThresholdTypes(), true)) {
            $errors[] = 'Threshold type is not supported.';
        }

        if ($rule->threshold_value === null || ! is_numeric($rule->threshold_value)) {
            $errors[] = 'Threshold value must be numeric.';
        } elseif ((float) $rule->threshold_value < 0) {
            $errors[] = 'Threshold value must not be negative.';
        } elseif (! is_finite((float) $rule->threshold_value)) {
            $errors[] = 'Threshold value must be finite.';
        }

        if (! $this->isBooleanAttribute($rule, 'applies_to_increase')) {
            $errors[] = 'Applies to increase must be a boolean.';
        }

        if (! $this->isBooleanAttribute($rule, 'applies_to_decrease')) {
            $errors[] = 'Applies to decrease must be a boolean.';
        }

        if (! $this->isBooleanAttribute($rule, 'enabled')) {
            $errors[] = 'Enabled must be a boolean.';
        }

        return $errors;
    }

    private function isBooleanAttribute(PricingVarianceRule $rule, string $attribute): bool
    {
        if (! array_key_exists($attribute, $rule->getAttributes())) {
            return true;
        }

        return is_bool($rule->getAttributes()[$attribute]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertValid(PricingVarianceRule $rule): void
    {
        $errors = $this->validate($rule);

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    /**
     * @return list<VarianceFactorCode>
     */
    public static function supportedFactors(): array
    {
        return VarianceFactorCode::cases();
    }

    /**
     * @return list<VarianceThresholdType>
     */
    public static function supportedThresholdTypes(): array
    {
        return VarianceThresholdType::cases();
    }
}
