<?php

namespace App\Services\Pricing;

use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\MissingFeeConfigException;

class FeeCalculator
{
    /**
     * Calculate a conditional fee.
     *
     * @param PricingPolicy $policy The active pricing policy
     * @param string $feeCode The fee identifier (border, insurance, hazmat, heavy_reefer)
     * @param bool $triggered Whether the condition is met
     * @param string $freightCharge The freight charge for percentage-based fees (bcmath string)
     *
     * @throws MissingFeeConfigException
     * @throws InvalidPricingConfigException
     * @throws InvalidPricingInputException
     */
    public function calculate(
        PricingPolicy $policy,
        string $feeCode,
        bool $triggered,
        string $freightCharge = '0',
    ): FeeCalculationResult {
        if (!$triggered) {
            return FeeCalculationResult::zero($feeCode);
        }

        if (bccomp($freightCharge, '0', 4) < 0) {
            throw new InvalidPricingInputException("Freight charge must not be negative.");
        }

        $fee = $policy->fees()
            ->where('code', $feeCode)
            ->first();

        if (!$fee) {
            throw new MissingFeeConfigException(
                "Fee configuration '{$feeCode}' is missing in policy #{$policy->id}."
            );
        }

        if (!$fee->enabled) {
            return FeeCalculationResult::zero($feeCode);
        }

        $this->validateFee($fee);

        $calculated = $this->computeAmount($fee, $freightCharge);

        // Apply max cap
        $capped = false;
        if ($fee->max_amount !== null) {
            $maxAmount = (string) $fee->max_amount;
            if (bccomp($calculated, $maxAmount, 4) > 0) {
                $calculated = $maxAmount;
                $capped = true;
            }
        }

        return new FeeCalculationResult(
            feeCode: $feeCode,
            feeType: $fee->fee_type,
            amount: (float) $calculated,
            triggered: true,
            capped: $capped,
        );
    }

    private function computeAmount(PricingFee $fee, string $freightCharge): string
    {
        $amount = (string) $fee->amount;

        return match ($fee->fee_type) {
            'flat' => $amount,
            'percentage' => bcdiv(bcmul($freightCharge, $amount, 4), '100', 4),
            default => throw new InvalidPricingConfigException("Unknown fee type: {$fee->fee_type}"),
        };
    }

    private function validateFee(PricingFee $fee): void
    {
        if (bccomp((string) $fee->amount, '0', 4) < 0) {
            throw new InvalidPricingConfigException("Fee '{$fee->code}' amount must be >= 0.");
        }

        if ($fee->fee_type === 'percentage' && bccomp((string) $fee->amount, '100', 4) > 0) {
            throw new InvalidPricingConfigException("Fee '{$fee->code}' percentage must be <= 100.");
        }

        if ($fee->max_amount !== null && bccomp((string) $fee->max_amount, '0', 4) < 0) {
            throw new InvalidPricingConfigException("Fee '{$fee->code}' max_amount must be >= 0.");
        }
    }
}
