<?php

namespace App\Http\Resources\Admin;

use App\Services\Pricing\PricingBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/ops pricing estimate with operational metadata and override indicators.
 */
class AdminPricingEstimateResource extends JsonResource
{
    /**
     * @param  array{
     *     distance_km: float,
     *     distance_source: string,
     *     route_distance_km: float|null,
     *     manual_distance_km: float|null,
     *     policy_source: string,
     *     customer_type_value: string,
     *     customer_type_source: string,
     * }  $metadata
     */
    public function __construct(
        PricingBreakdown $breakdown,
        private readonly array $metadata,
    ) {
        parent::__construct($breakdown);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $breakdown = $this->resource;

        $distance = [
            'distance_km' => $this->metadata['distance_km'],
            'distance_source' => $this->metadata['distance_source'],
        ];

        if ($this->metadata['route_distance_km'] !== null) {
            $distance['route_distance_km'] = $this->metadata['route_distance_km'];
        }

        if ($this->metadata['manual_distance_km'] !== null) {
            $distance['manual_distance_km'] = $this->metadata['manual_distance_km'];
        }

        return [
            'currency' => $breakdown->currency,

            'pricing_policy' => [
                'id' => $breakdown->pricingPolicyId,
                'name' => $breakdown->pricingPolicyName,
                'source' => $this->metadata['policy_source'],
            ],

            'customer_type' => [
                'value' => $this->metadata['customer_type_value'],
                'source' => $this->metadata['customer_type_source'],
            ],

            'distance' => $distance,

            'charges' => [
                'freight' => $breakdown->freightCharge,
                'weight_surcharge' => $breakdown->weightSurcharge,
                'waiting_charge' => $breakdown->waitingCharge,
                'services' => $breakdown->serviceCharges,
                'border' => $breakdown->borderFee,
                'insurance' => $breakdown->insuranceFee,
                'hazmat' => $breakdown->hazmatSurcharge,
                'heavy_reefer' => $breakdown->heavyReeferSurcharge,
                'priority' => $breakdown->prioritySurcharge,
            ],

            'subtotal' => $breakdown->subtotal,

            'discount' => [
                'amount' => $breakdown->discount,
                'type' => $breakdown->discountType,
                'rate' => $breakdown->discountRate,
            ],

            'after_discount' => $breakdown->afterDiscount,
            'minimum_fare_adjustment' => $breakdown->minimumFareAdjustment,
            'after_minimum_fare' => $breakdown->afterMinimumFare,

            'tax' => [
                'amount' => $breakdown->tax,
                'rate' => $breakdown->taxRate,
            ],

            'total' => $breakdown->finalTotal,

            'service_items' => $breakdown->serviceItems,
            'warnings' => $breakdown->warnings,

            'applied_rules' => $breakdown->appliedRules,

            'pricing_rule_resolution' => [
                'base_rate' => $breakdown->baseRate,
                'distance_tier_rate' => $breakdown->distanceTierRate,
                'base_rate_resolution_type' => $breakdown->baseRateResolutionType,
                'tax_rate' => $breakdown->taxRate,
            ],
        ];
    }
}
