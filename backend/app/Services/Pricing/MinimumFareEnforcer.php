<?php

namespace App\Services\Pricing;

use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;

class MinimumFareEnforcer
{
    /**
     * Enforce minimum fare on the after-discount amount.
     *
     * @param string $amount The after-discount amount (bcmath string)
     * @param string|null $minimumFare The configured minimum fare (null = no enforcement)
     *
     * @throws InvalidPricingInputException
     * @throws InvalidPricingConfigException
     */
    public function enforce(string $amount, ?string $minimumFare): MinimumFareResult
    {
        if (bccomp($amount, '0', 4) < 0) {
            throw new InvalidPricingInputException('Amount must not be negative.');
        }

        if ($minimumFare === null) {
            return new MinimumFareResult(
                originalAmount: (float) $amount,
                minimumFare: null,
                adjustedAmount: (float) $amount,
                applied: false,
            );
        }

        if (bccomp($minimumFare, '0', 4) < 0) {
            throw new InvalidPricingConfigException('Minimum fare must be >= 0.');
        }

        $applied = bccomp($amount, $minimumFare, 4) < 0;
        $adjustedAmount = $applied ? $minimumFare : $amount;

        return new MinimumFareResult(
            originalAmount: (float) $amount,
            minimumFare: (float) $minimumFare,
            adjustedAmount: (float) $adjustedAmount,
            applied: $applied,
        );
    }
}
