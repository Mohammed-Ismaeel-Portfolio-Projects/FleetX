<?php

namespace App\Services\Pricing;

use App\Models\PricingWaitingConfig;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;

class WaitingChargeCalculator
{
    /**
     * Calculate waiting time charges.
     *
     * @throws InvalidPricingInputException
     * @throws InvalidPricingConfigException
     */
    public function calculate(
        PricingWaitingConfig $config,
        int $loadingWaitingTime,
        int $unloadingWaitingTime,
        int $trafficDelay = 0,
        int $customerDelay = 0,
    ): WaitingChargeResult {
        $this->validateInput($loadingWaitingTime, $unloadingWaitingTime, $trafficDelay, $customerDelay);
        $this->validateConfig($config);

        if (!$config->enabled) {
            return new WaitingChargeResult(
                loadingWaitingTime: $loadingWaitingTime,
                unloadingWaitingTime: $unloadingWaitingTime,
                trafficDelay: $trafficDelay,
                customerDelay: $customerDelay,
                loadingExcessMinutes: 0,
                unloadingExcessMinutes: 0,
                loadingCharge: 0.0,
                unloadingCharge: 0.0,
                trafficCharge: 0.0,
                customerCharge: 0.0,
                totalBeforeCap: 0.0,
                finalCharge: 0.0,
                capped: false,
                enabled: false,
            );
        }

        $ratePer15 = (string) $config->rate_per_15min;
        $maxCharge = (string) $config->max_charge;

        // Excess minutes (integer arithmetic)
        $loadingExcess = max(0, $loadingWaitingTime - $config->loading_free_minutes);
        $unloadingExcess = max(0, $unloadingWaitingTime - $config->unloading_free_minutes);

        // Loading/unloading: ceiling to 15-min increments (integer-safe)
        $loadingIncrements = $loadingExcess > 0 ? intdiv($loadingExcess + 14, 15) : 0;
        $unloadingIncrements = $unloadingExcess > 0 ? intdiv($unloadingExcess + 14, 15) : 0;

        $loadingCharge = (float) bcmul((string) $loadingIncrements, $ratePer15, 4);
        $unloadingCharge = (float) bcmul((string) $unloadingIncrements, $ratePer15, 4);

        // Traffic/customer: per-minute rate (ratePer15min / 15)
        $ratePerMinute = bcdiv($ratePer15, '15', 4);
        $trafficCharge = (float) bcmul((string) $trafficDelay, $ratePerMinute, 4);
        $customerCharge = (float) bcmul((string) $customerDelay, $ratePerMinute, 4);

        // Total before cap
        $total = (float) bcadd(
            bcadd(bcadd((string) $loadingCharge, (string) $unloadingCharge, 4), (string) $trafficCharge, 4),
            (string) $customerCharge,
            4
        );

        // Apply cap
        $capped = bccomp((string) $total, $maxCharge, 4) > 0;
        $finalCharge = $capped ? (float) $maxCharge : $total;

        return new WaitingChargeResult(
            loadingWaitingTime: $loadingWaitingTime,
            unloadingWaitingTime: $unloadingWaitingTime,
            trafficDelay: $trafficDelay,
            customerDelay: $customerDelay,
            loadingExcessMinutes: $loadingExcess,
            unloadingExcessMinutes: $unloadingExcess,
            loadingCharge: $loadingCharge,
            unloadingCharge: $unloadingCharge,
            trafficCharge: $trafficCharge,
            customerCharge: $customerCharge,
            totalBeforeCap: $total,
            finalCharge: $finalCharge,
            capped: $capped,
            enabled: true,
        );
    }

    private function validateInput(int $loading, int $unloading, int $traffic, int $customer): void
    {
        if ($loading < 0) {
            throw new InvalidPricingInputException('Loading waiting time must not be negative.');
        }
        if ($unloading < 0) {
            throw new InvalidPricingInputException('Unloading waiting time must not be negative.');
        }
        if ($traffic < 0) {
            throw new InvalidPricingInputException('Traffic delay must not be negative.');
        }
        if ($customer < 0) {
            throw new InvalidPricingInputException('Customer delay must not be negative.');
        }
    }

    private function validateConfig(PricingWaitingConfig $config): void
    {
        if ($config->loading_free_minutes < 0) {
            throw new InvalidPricingConfigException('Loading free minutes must not be negative.');
        }
        if ($config->unloading_free_minutes < 0) {
            throw new InvalidPricingConfigException('Unloading free minutes must not be negative.');
        }
        if ((float) $config->rate_per_15min < 0) {
            throw new InvalidPricingConfigException('Rate per 15min must not be negative.');
        }
        if ((float) $config->max_charge < 0) {
            throw new InvalidPricingConfigException('Max charge must not be negative.');
        }
    }
}
