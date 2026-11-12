<?php

namespace App\Services\Pricing;

use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\MissingPriorityConfigException;

class PrioritySurchargeCalculator
{
    /**
     * Calculate priority surcharge.
     *
     * @param PricingPolicy $policy The active pricing policy
     * @param string $priorityLevel The shipment priority (standard, express, urgent, emergency)
     * @param string $freightCharge The freight charge (bcmath string)
     *
     * @throws MissingPriorityConfigException
     * @throws InvalidPricingConfigException
     * @throws InvalidPricingInputException
     */
    public function calculate(
        PricingPolicy $policy,
        string $priorityLevel,
        string $freightCharge,
    ): PrioritySurchargeResult {
        if (bccomp($freightCharge, '0', 4) < 0) {
            throw new InvalidPricingInputException("Freight charge must not be negative.");
        }

        $config = $policy->priorityMultipliers()
            ->where('priority_level', $priorityLevel)
            ->first();

        if (!$config) {
            throw new MissingPriorityConfigException(
                "Priority multiplier for '{$priorityLevel}' is missing in policy #{$policy->id}."
            );
        }

        $multiplier = (string) $config->multiplier;

        if (bccomp($multiplier, '1', 4) < 0) {
            throw new InvalidPricingConfigException(
                "Priority multiplier for '{$priorityLevel}' must be >= 1.0 (got {$multiplier})."
            );
        }

        // surcharge = freightCharge × (multiplier - 1)
        $factor = bcsub($multiplier, '1', 4);
        $surcharge = bcmul($freightCharge, $factor, 4);

        return new PrioritySurchargeResult(
            priorityLevel: $priorityLevel,
            multiplier: (float) $multiplier,
            surcharge: (float) $surcharge,
        );
    }
}
