<?php

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;

class PricingEngine
{
    private const VALID_PRIORITIES = ['standard', 'express', 'urgent', 'emergency'];

    public function __construct(
        private readonly FreightRateResolver $freightRateResolver,
        private readonly WeightSurchargeCalculator $weightSurchargeCalculator,
        private readonly WaitingChargeCalculator $waitingChargeCalculator,
        private readonly FeeCalculator $feeCalculator,
        private readonly PrioritySurchargeCalculator $prioritySurchargeCalculator,
        private readonly DiscountCalculator $discountCalculator,
        private readonly MinimumFareEnforcer $minimumFareEnforcer,
        private readonly TaxCalculator $taxCalculator,
        private readonly PricingPolicyResolver $policyResolver,
    ) {}

    /**
     * Calculate complete shipment price.
     *
     * @throws NoPricingPolicyException
     * @throws InvalidPricingInputException
     */
    public function calculate(PricingInput $input, ?PricingPolicy $policy = null): PricingBreakdown
    {
        $this->validateInput($input);

        $policy = $policy ?? $this->policyResolver->resolveActive();
        $appliedRules = [];
        $warnings = [];

        // 1. Freight rate resolution
        $rateResolution = $this->freightRateResolver->resolve(
            $policy,
            $input->cargoType,
            $input->trailerType,
            $input->distance,
        );
        $appliedRules[] = "base_rate:{$input->cargoType->value}/{$input->trailerType->value}({$rateResolution->baseRateResolutionType})";

        // 2. Calculate freight charge: distance × (baseRate + tierRate)
        $freightCharge = bcmul(
            (string) $input->distance,
            bcadd((string) $rateResolution->baseRate, (string) $rateResolution->distanceTierRate, 4),
            4
        );

        // 3. Weight surcharge
        $weightSurcharge = '0';
        $weightConfig = $policy->weightConfig;
        if ($weightConfig && $input->cargoWeight > 0) {
            $weightResult = $this->weightSurchargeCalculator->calculate($input->cargoWeight, $weightConfig);
            $weightSurcharge = (string) $weightResult->surcharge;
            if ((float) $weightSurcharge > 0) {
                $appliedRules[] = 'weight_surcharge';
            }
        }

        // 4. Waiting charge
        $waitingCharge = '0';
        $waitingConfig = $policy->waitingConfig;
        if ($waitingConfig) {
            $waitingResult = $this->waitingChargeCalculator->calculate(
                $waitingConfig,
                $input->loadingWaitingTime,
                $input->unloadingWaitingTime,
                $input->trafficDelay,
                $input->customerDelay,
            );
            $waitingCharge = (string) $waitingResult->finalCharge;
            if ((float) $waitingCharge > 0) {
                $appliedRules[] = 'waiting_charge';
            }
        }

        // 5. Service charges
        $serviceCharges = '0';
        $serviceItems = [];
        foreach ($input->selectedServices as $service) {
            $price = (string) $service['price'];
            $serviceCharges = bcadd($serviceCharges, $price, 4);
            $serviceItems[] = ['name' => $service['name'], 'price' => (float) $price];
        }

        // 6. Border fee
        $borderFee = '0';
        if ($input->borderCrossing) {
            $borderResult = $this->feeCalculator->calculate($policy, 'border', true, $freightCharge);
            $borderFee = (string) $borderResult->amount;
            $appliedRules[] = 'border_fee';
        }

        // 7. Insurance fee (applies to full freightCharge)
        $insuranceFee = '0';
        if ($input->insuranceRequired) {
            $insuranceResult = $this->feeCalculator->calculate($policy, 'insurance', true, $freightCharge);
            $insuranceFee = (string) $insuranceResult->amount;
            $appliedRules[] = 'insurance_fee';
        }

        // 8. Hazmat surcharge (applies to full freightCharge)
        $hazmatSurcharge = '0';
        if ($input->hazmat) {
            $hazmatResult = $this->feeCalculator->calculate($policy, 'hazmat', true, $freightCharge);
            $hazmatSurcharge = (string) $hazmatResult->amount;
            $appliedRules[] = 'hazmat_surcharge';
        }

        // 9. Heavy-reefer surcharge (applies to full freightCharge)
        $heavyReeferSurcharge = '0';
        $isHeavyReefer = $input->cargoType === CargoType::HEAVY && $input->trailerType === TrailerType::REEFER;
        if ($isHeavyReefer) {
            $heavyReeferResult = $this->feeCalculator->calculate($policy, 'heavy_reefer', true, $freightCharge);
            $heavyReeferSurcharge = (string) $heavyReeferResult->amount;
            $appliedRules[] = 'heavy_reefer_surcharge';
        }

        // 10. Priority surcharge (applies to full freightCharge)
        $prioritySurcharge = '0';
        $priorityResult = $this->prioritySurchargeCalculator->calculate($policy, $input->shipmentPriority, $freightCharge);
        $prioritySurcharge = (string) $priorityResult->surcharge;
        if ((float) $prioritySurcharge > 0) {
            $appliedRules[] = "priority:{$input->shipmentPriority}";
        }

        // 11. Subtotal
        $subtotal = $freightCharge;
        $subtotal = bcadd($subtotal, $weightSurcharge, 4);
        $subtotal = bcadd($subtotal, $waitingCharge, 4);
        $subtotal = bcadd($subtotal, $serviceCharges, 4);
        $subtotal = bcadd($subtotal, $borderFee, 4);
        $subtotal = bcadd($subtotal, $insuranceFee, 4);
        $subtotal = bcadd($subtotal, $hazmatSurcharge, 4);
        $subtotal = bcadd($subtotal, $heavyReeferSurcharge, 4);
        $subtotal = bcadd($subtotal, $prioritySurcharge, 4);

        // 12. Discount
        $discountResult = $this->discountCalculator->calculate($policy, $input->customerType, $subtotal);
        $discount = (string) $discountResult->appliedDiscount;
        $afterDiscount = (string) $discountResult->afterDiscount;
        if ((float) $discount > 0) {
            $appliedRules[] = "discount:{$input->customerType}";
        }

        // 13. Minimum fare
        $minimumFare = $policy->minimum_fare !== null ? (string) $policy->minimum_fare : null;
        $fareResult = $this->minimumFareEnforcer->enforce($afterDiscount, $minimumFare);
        $afterMinimumFare = (string) $fareResult->adjustedAmount;
        $minimumFareAdjustment = bcsub($afterMinimumFare, $afterDiscount, 4);
        if ($fareResult->applied) {
            $appliedRules[] = 'minimum_fare';
        }

        // 14. Tax
        $taxResult = $this->taxCalculator->calculate($policy, $afterMinimumFare);
        $tax = (string) $taxResult->taxAmount;

        // 15. Final total with ROUND_HALF_UP to 2 decimals
        $finalRaw = bcadd($afterMinimumFare, $tax, 4);
        $finalTotal = $this->roundHalfUp($finalRaw, 2);

        return new PricingBreakdown(
            currency: $policy->currency ?? 'USD',
            pricingPolicyId: $policy->id,
            pricingPolicyName: $policy->name,
            input: $input,
            freightCharge: (float) $freightCharge,
            weightSurcharge: (float) $weightSurcharge,
            waitingCharge: (float) $waitingCharge,
            serviceCharges: (float) $serviceCharges,
            borderFee: (float) $borderFee,
            insuranceFee: (float) $insuranceFee,
            hazmatSurcharge: (float) $hazmatSurcharge,
            heavyReeferSurcharge: (float) $heavyReeferSurcharge,
            prioritySurcharge: (float) $prioritySurcharge,
            subtotal: (float) $subtotal,
            discount: (float) $discount,
            afterDiscount: (float) $afterDiscount,
            minimumFareAdjustment: (float) $minimumFareAdjustment,
            afterMinimumFare: (float) $afterMinimumFare,
            tax: (float) $tax,
            finalTotal: $finalTotal,
            serviceItems: $serviceItems,
            appliedRules: $appliedRules,
            warnings: $warnings,
            baseRate: $rateResolution->baseRate,
            distanceTierRate: $rateResolution->distanceTierRate,
            baseRateResolutionType: $rateResolution->baseRateResolutionType,
            taxRate: (float) $policy->tax_rate,
            minimumFare: $policy->minimum_fare !== null ? (float) $policy->minimum_fare : null,
            discountType: $discountResult->discountType,
            discountRate: $discountResult->configuredAmount,
        );
    }

    private function validateInput(PricingInput $input): void
    {
        if ($input->distance < 0) {
            throw new InvalidPricingInputException('Distance must not be negative.');
        }
        if ($input->cargoWeight < 0) {
            throw new InvalidPricingInputException('Cargo weight must not be negative.');
        }
        if ($input->loadingWaitingTime < 0 || $input->unloadingWaitingTime < 0 || $input->trafficDelay < 0 || $input->customerDelay < 0) {
            throw new InvalidPricingInputException('Waiting times must not be negative.');
        }
        if (!in_array($input->shipmentPriority, self::VALID_PRIORITIES, true)) {
            throw new InvalidPricingInputException("Invalid priority: '{$input->shipmentPriority}'.");
        }
        if (CustomerType::tryFrom($input->customerType) === null) {
            throw new InvalidPricingInputException("Invalid customer type: '{$input->customerType}'.");
        }
    }

    private function roundHalfUp(string $value, int $decimals): float
    {
        // PHP_ROUND_HALF_UP with bcmath
        $factor = bcpow('10', (string) $decimals);
        $shifted = bcmul($value, $factor, 4);
        // Add 0.00005 for half-up then truncate
        $rounded = bcdiv(bcadd($shifted, '0.5', 4), '1', 0);
        return (float) bcdiv($rounded, $factor, $decimals);
    }
}
