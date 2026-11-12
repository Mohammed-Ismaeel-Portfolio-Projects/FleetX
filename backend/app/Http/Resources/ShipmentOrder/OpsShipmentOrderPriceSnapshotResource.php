<?php

namespace App\Http\Resources\ShipmentOrder;

use App\Models\ShipmentOrderPriceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

/**
 * Ops-facing persisted price snapshot (Phase 5.4c).
 * Projects ShipmentOrderPriceBreakdown only — never recalculates pricing.
 */
class OpsShipmentOrderPriceSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ShipmentOrderPriceBreakdown $breakdown */
        $breakdown = $this->resource;
        $json = $breakdown->breakdown_json;

        if (! is_array($json) || ! $this->isUsableBreakdownJson($json)) {
            return $this->degradedFallback($breakdown);
        }

        $policyId = $json['pricingPolicyId'] ?? $breakdown->pricing_policy_id;
        $policyName = $json['pricingPolicyName'] ?? null;
        if ($policyName === null && $breakdown->relationLoaded('pricingPolicy') && $breakdown->pricingPolicy !== null) {
            $policyName = $breakdown->pricingPolicy->name;
        }

        return [
            'calculated_at' => $breakdown->calculated_at?->format('Y-m-d H:i:s'),
            'pricing_policy' => [
                'id' => $policyId,
                'name' => $policyName,
                'source' => 'active_at_submission',
            ],
            'customer_type' => [
                'value' => $json['input']['customerType'] ?? null,
                'source' => 'company_at_submission',
            ],
            'distance' => [
                'distance_km' => (float) ($json['input']['distance'] ?? 0),
                'distance_source' => 'route_at_submission',
            ],
            'currency' => $breakdown->currency,
            'charges' => $this->mapCharges($json),
            'subtotal' => (float) $json['subtotal'],
            'discount' => [
                'amount' => (float) $json['discount'],
                'type' => $json['discountType'] ?? null,
                'rate' => isset($json['discountRate']) ? (float) $json['discountRate'] : null,
            ],
            'after_discount' => (float) $json['afterDiscount'],
            'minimum_fare_adjustment' => (float) $json['minimumFareAdjustment'],
            'after_minimum_fare' => (float) $json['afterMinimumFare'],
            'tax' => [
                'amount' => (float) $json['tax'],
                'rate' => isset($json['taxRate']) ? (float) $json['taxRate'] : null,
            ],
            'total' => (float) $json['finalTotal'],
            'service_items' => $json['serviceItems'] ?? [],
            'warnings' => $json['warnings'] ?? [],
            'applied_rules' => $json['appliedRules'] ?? [],
            'pricing_rule_resolution' => [
                'base_rate' => isset($json['baseRate']) ? (float) $json['baseRate'] : null,
                'distance_tier_rate' => isset($json['distanceTierRate']) ? (float) $json['distanceTierRate'] : null,
                'base_rate_resolution_type' => $json['baseRateResolutionType'] ?? null,
                'tax_rate' => isset($json['taxRate']) ? (float) $json['taxRate'] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, float>
     */
    private function mapCharges(array $json): array
    {
        return [
            'freight' => (float) ($json['freightCharge'] ?? 0),
            'weight_surcharge' => (float) ($json['weightSurcharge'] ?? 0),
            'waiting_charge' => (float) ($json['waitingCharge'] ?? 0),
            'services' => (float) ($json['serviceCharges'] ?? 0),
            'border' => (float) ($json['borderFee'] ?? 0),
            'insurance' => (float) ($json['insuranceFee'] ?? 0),
            'hazmat' => (float) ($json['hazmatSurcharge'] ?? 0),
            'heavy_reefer' => (float) ($json['heavyReeferSurcharge'] ?? 0),
            'priority' => (float) ($json['prioritySurcharge'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function isUsableBreakdownJson(array $json): bool
    {
        return isset($json['finalTotal'], $json['freightCharge'], $json['subtotal'])
            && isset($json['input']) && is_array($json['input'])
            && array_key_exists('distance', $json['input']);
    }

    /**
     * @return array<string, mixed>
     */
    private function degradedFallback(ShipmentOrderPriceBreakdown $breakdown): array
    {
        Log::warning('Malformed shipment order price breakdown JSON', [
            'shipment_order_price_breakdown_id' => $breakdown->id,
            'shipment_order_id' => $breakdown->shipment_order_id,
        ]);

        return [
            'calculated_at' => $breakdown->calculated_at?->format('Y-m-d H:i:s'),
            'currency' => $breakdown->currency,
            'total' => (float) $breakdown->final_total,
            'detail_status' => 'degraded',
            'warnings' => ['Price breakdown detail unavailable'],
        ];
    }
}
