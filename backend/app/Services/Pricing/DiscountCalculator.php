<?php

namespace App\Services\Pricing;

use App\Enums\CustomerType;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;

class DiscountCalculator
{
    private const VALID_DISCOUNT_TYPES = ['percentage', 'flat'];

    /**
     * Calculate discount for a given subtotal and customer type.
     *
     * @throws InvalidPricingInputException
     * @throws InvalidPricingConfigException
     */
    public function calculate(
        PricingPolicy $policy,
        string $customerType,
        string $subtotal,
    ): DiscountCalculationResult {
        $this->validateInput($customerType, $subtotal);

        $discount = $policy->discounts()
            ->where('customer_type', $customerType)
            ->first();

        if (!$discount || !$discount->enabled) {
            return new DiscountCalculationResult(
                customerType: $customerType,
                discountType: 'none',
                configuredAmount: 0.0,
                calculatedDiscount: 0.0,
                appliedDiscount: 0.0,
                afterDiscount: (float) $subtotal,
                capped: false,
                configured: $discount !== null,
            );
        }

        $this->validateConfig($discount);

        $amount = (string) $discount->amount;
        $discountType = $discount->discount_type;

        // Calculate raw discount
        $calculated = match ($discountType) {
            'percentage' => bcdiv(bcmul($subtotal, $amount, 4), '100', 4),
            'flat' => $amount,
            default => throw new InvalidPricingConfigException("Invalid discount type: {$discountType}"),
        };

        // Apply max_discount cap
        $capped = false;
        if ($discount->max_discount !== null) {
            $maxDiscount = (string) $discount->max_discount;
            if (bccomp($calculated, $maxDiscount, 4) > 0) {
                $calculated = $maxDiscount;
                $capped = true;
            }
        }

        // Cap discount to subtotal (discount cannot exceed subtotal)
        if (bccomp($calculated, $subtotal, 4) > 0) {
            $calculated = $subtotal;
            $capped = true;
        }

        // afterDiscount = max(0, subtotal - discount)
        $afterDiscount = bcsub($subtotal, $calculated, 4);
        if (bccomp($afterDiscount, '0', 4) < 0) {
            $afterDiscount = '0';
        }

        return new DiscountCalculationResult(
            customerType: $customerType,
            discountType: $discountType,
            configuredAmount: (float) $discount->amount,
            calculatedDiscount: (float) $calculated,
            appliedDiscount: (float) $calculated,
            afterDiscount: (float) $afterDiscount,
            capped: $capped,
            configured: true,
        );
    }

    private function validateInput(string $customerType, string $subtotal): void
    {
        if (CustomerType::tryFrom($customerType) === null) {
            throw new InvalidPricingInputException("Invalid customer type: '{$customerType}'.");
        }
        if (bccomp($subtotal, '0', 4) < 0) {
            throw new InvalidPricingInputException('Subtotal must not be negative.');
        }
    }

    private function validateConfig(object $discount): void
    {
        if (!in_array($discount->discount_type, self::VALID_DISCOUNT_TYPES, true)) {
            throw new InvalidPricingConfigException("Invalid discount type: '{$discount->discount_type}'.");
        }
        if (bccomp((string) $discount->amount, '0', 4) < 0) {
            throw new InvalidPricingConfigException('Discount amount must be >= 0.');
        }
        if ($discount->discount_type === 'percentage' && bccomp((string) $discount->amount, '100', 4) > 0) {
            throw new InvalidPricingConfigException('Discount percentage must be <= 100.');
        }
        if ($discount->max_discount !== null && bccomp((string) $discount->max_discount, '0', 4) < 0) {
            throw new InvalidPricingConfigException('Max discount must be >= 0.');
        }
    }
}
