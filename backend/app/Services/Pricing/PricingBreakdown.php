<?php

namespace App\Services\Pricing;

class PricingBreakdown
{
    /**
     * @param array<array{name: string, price: float}> $serviceItems
     * @param array<string> $appliedRules
     * @param array<string> $warnings
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $pricingPolicyId,
        public readonly string $pricingPolicyName,
        public readonly PricingInput $input,
        public readonly float $freightCharge,
        public readonly float $weightSurcharge,
        public readonly float $waitingCharge,
        public readonly float $serviceCharges,
        public readonly float $borderFee,
        public readonly float $insuranceFee,
        public readonly float $hazmatSurcharge,
        public readonly float $heavyReeferSurcharge,
        public readonly float $prioritySurcharge,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly float $afterDiscount,
        public readonly float $minimumFareAdjustment,
        public readonly float $afterMinimumFare,
        public readonly float $tax,
        public readonly float $finalTotal,
        public readonly array $serviceItems,
        public readonly array $appliedRules,
        public readonly array $warnings,
        // Resolution metadata
        public readonly float $baseRate,
        public readonly float $distanceTierRate,
        public readonly string $baseRateResolutionType,
        public readonly float $taxRate,
        public readonly ?float $minimumFare,
        public readonly string $discountType,
        public readonly float $discountRate,
    ) {}
}
