<?php

declare(strict_types=1);

namespace App\Services\Pricing;

class PricingBreakdownSerializer
{
    /**
     * Serialize a PricingBreakdown into a persistable JSON-safe array.
     *
     * @return array<string, mixed>
     */
    public static function toArray(PricingBreakdown $breakdown): array
    {
        $input = $breakdown->input;

        return [
            'currency' => $breakdown->currency,
            'pricingPolicyId' => $breakdown->pricingPolicyId,
            'pricingPolicyName' => $breakdown->pricingPolicyName,
            'input' => [
                'distance' => $input->distance,
                'cargoType' => $input->cargoType->value,
                'trailerType' => $input->trailerType->value,
                'cargoWeight' => $input->cargoWeight,
                'shipmentPriority' => $input->shipmentPriority,
                'customerType' => $input->customerType,
                'borderCrossing' => $input->borderCrossing,
                'hazmat' => $input->hazmat,
                'insuranceRequired' => $input->insuranceRequired,
                'selectedServices' => $input->selectedServices,
                'loadingWaitingTime' => $input->loadingWaitingTime,
                'unloadingWaitingTime' => $input->unloadingWaitingTime,
                'trafficDelay' => $input->trafficDelay,
                'customerDelay' => $input->customerDelay,
            ],
            'freightCharge' => $breakdown->freightCharge,
            'weightSurcharge' => $breakdown->weightSurcharge,
            'waitingCharge' => $breakdown->waitingCharge,
            'serviceCharges' => $breakdown->serviceCharges,
            'borderFee' => $breakdown->borderFee,
            'insuranceFee' => $breakdown->insuranceFee,
            'hazmatSurcharge' => $breakdown->hazmatSurcharge,
            'heavyReeferSurcharge' => $breakdown->heavyReeferSurcharge,
            'prioritySurcharge' => $breakdown->prioritySurcharge,
            'subtotal' => $breakdown->subtotal,
            'discount' => $breakdown->discount,
            'afterDiscount' => $breakdown->afterDiscount,
            'minimumFareAdjustment' => $breakdown->minimumFareAdjustment,
            'afterMinimumFare' => $breakdown->afterMinimumFare,
            'tax' => $breakdown->tax,
            'finalTotal' => $breakdown->finalTotal,
            'serviceItems' => $breakdown->serviceItems,
            'appliedRules' => $breakdown->appliedRules,
            'warnings' => $breakdown->warnings,
            'baseRate' => $breakdown->baseRate,
            'distanceTierRate' => $breakdown->distanceTierRate,
            'baseRateResolutionType' => $breakdown->baseRateResolutionType,
            'taxRate' => $breakdown->taxRate,
            'minimumFare' => $breakdown->minimumFare,
            'discountType' => $breakdown->discountType,
            'discountRate' => $breakdown->discountRate,
        ];
    }
}
