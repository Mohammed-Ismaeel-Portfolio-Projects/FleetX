<?php

namespace App\Services\Pricing;

use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;

class TaxCalculator
{
    /**
     * Calculate tax on the after-discount amount.
     *
     * @throws InvalidPricingInputException
     * @throws InvalidPricingConfigException
     */
    public function calculate(PricingPolicy $policy, string $afterDiscount): TaxCalculationResult
    {
        $this->validateInput($afterDiscount);
        $this->validateConfig($policy);

        $taxRate = (string) $policy->tax_rate;
        $taxAmount = bcdiv(bcmul($afterDiscount, $taxRate, 4), '100', 4);
        $totalWithTax = bcadd($afterDiscount, $taxAmount, 4);

        return new TaxCalculationResult(
            afterDiscount: (float) $afterDiscount,
            taxRate: (float) $taxRate,
            taxAmount: (float) $taxAmount,
            totalWithTax: (float) $totalWithTax,
        );
    }

    private function validateInput(string $afterDiscount): void
    {
        if (bccomp($afterDiscount, '0', 4) < 0) {
            throw new InvalidPricingInputException('After-discount amount must not be negative.');
        }
    }

    private function validateConfig(PricingPolicy $policy): void
    {
        if ($policy->tax_rate === null) {
            throw new InvalidPricingConfigException('Tax rate is not configured on policy.');
        }

        $taxRate = (string) $policy->tax_rate;

        if (bccomp($taxRate, '0', 4) < 0) {
            throw new InvalidPricingConfigException('Tax rate must be >= 0.');
        }

        if (bccomp($taxRate, '100', 4) > 0) {
            throw new InvalidPricingConfigException('Tax rate must be <= 100.');
        }

        if ($policy->tax_inclusive) {
            throw new InvalidPricingConfigException('Tax-inclusive pricing is not supported in FLeetX v1.');
        }
    }
}
