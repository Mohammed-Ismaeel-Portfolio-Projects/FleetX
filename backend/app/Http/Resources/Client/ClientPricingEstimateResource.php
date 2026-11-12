<?php

namespace App\Http\Resources\Client;

use App\Services\Pricing\PricingBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing pricing estimate response.
 * Exposes only price breakdown information appropriate for the customer.
 * Does NOT expose internal policy configuration, rate resolution metadata, or admin details.
 */
class ClientPricingEstimateResource extends JsonResource
{
    private float $distanceKm;

    public function __construct(PricingBreakdown $breakdown, float $distanceKm)
    {
        parent::__construct($breakdown);
        $this->distanceKm = $distanceKm;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->resource->currency,
            'distance_km' => $this->distanceKm,

            'charges' => [
                'freight' => $this->resource->freightCharge,
                'weight_surcharge' => $this->resource->weightSurcharge,
                'waiting_charge' => $this->resource->waitingCharge,
                'services' => $this->resource->serviceCharges,
                'border' => $this->resource->borderFee,
                'insurance' => $this->resource->insuranceFee,
                'hazmat' => $this->resource->hazmatSurcharge,
                'heavy_reefer' => $this->resource->heavyReeferSurcharge,
                'priority' => $this->resource->prioritySurcharge,
            ],

            'subtotal' => $this->resource->subtotal,

            'discount' => [
                'amount' => $this->resource->discount,
                'type' => $this->resource->discountType,
                'rate' => $this->resource->discountRate,
            ],

            'after_discount' => $this->resource->afterDiscount,
            'minimum_fare_adjustment' => $this->resource->minimumFareAdjustment,
            'after_minimum_fare' => $this->resource->afterMinimumFare,

            'tax' => [
                'amount' => $this->resource->tax,
                'rate' => $this->resource->taxRate,
            ],

            'total' => $this->resource->finalTotal,

            'service_items' => $this->resource->serviceItems,
            'warnings' => $this->resource->warnings,
        ];
    }
}
