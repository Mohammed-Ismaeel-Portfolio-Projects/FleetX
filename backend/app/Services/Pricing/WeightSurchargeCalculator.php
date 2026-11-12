<?php

namespace App\Services\Pricing;

use App\Models\PricingWeightConfig;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;

class WeightSurchargeCalculator
{
    /**
     * Calculate the weight surcharge for a given cargo weight and configuration.
     *
     * @throws InvalidPricingInputException
     * @throws InvalidPricingConfigException
     */
    public function calculate(float $cargoWeight, PricingWeightConfig $config): WeightSurchargeResult
    {
        $this->validateInput($cargoWeight);
        $this->validateConfig($config);

        $threshold = (float) $config->threshold_tons;
        $chargePerTon = (float) $config->charge_per_ton;

        $excessWeight = max(0, bcmul('1', bcsub((string) $cargoWeight, (string) $threshold, 4), 4));
        $excessWeight = (float) $excessWeight;

        $surcharge = (float) bcmul((string) $excessWeight, (string) $chargePerTon, 4);

        return new WeightSurchargeResult(
            cargoWeight: $cargoWeight,
            thresholdTons: $threshold,
            excessWeight: $excessWeight,
            chargePerTon: $chargePerTon,
            surcharge: $surcharge,
        );
    }

    private function validateInput(float $cargoWeight): void
    {
        if ($cargoWeight < 0) {
            throw new InvalidPricingInputException('Cargo weight must not be negative.');
        }
    }

    private function validateConfig(PricingWeightConfig $config): void
    {
        if ((float) $config->threshold_tons <= 0) {
            throw new InvalidPricingConfigException('Weight threshold must be greater than 0.');
        }
        if ((float) $config->charge_per_ton < 0) {
            throw new InvalidPricingConfigException('Charge per ton must not be negative.');
        }
    }
}
