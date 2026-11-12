<?php

namespace App\Services\Pricing;

class DiscountCalculationResult
{
    public function __construct(
        public readonly string $customerType,
        public readonly string $discountType,
        public readonly float $configuredAmount,
        public readonly float $calculatedDiscount,
        public readonly float $appliedDiscount,
        public readonly float $afterDiscount,
        public readonly bool $capped,
        public readonly bool $configured,
    ) {}
}
