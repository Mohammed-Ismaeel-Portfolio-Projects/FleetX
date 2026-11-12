<?php

namespace App\Http\Resources\Shipment;

use App\Http\Resources\ShipmentOperationalActualsResource;
use App\Models\ShipmentFinalPriceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

/**
 * Ops-facing persisted final shipment price (Phase 5.5d).
 * Projects ShipmentFinalPriceBreakdown only — never recalculates pricing.
 */
class OpsShipmentFinalPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ShipmentFinalPriceBreakdown $breakdown */
        $breakdown = $this->resource;
        $json = $breakdown->breakdown_json;
        $chargesUsable = is_array($json) && $this->isUsableBreakdownJson($json);

        $result = [
            'id' => $breakdown->id,
            'status' => $breakdown->status?->value,
            'shipment_id' => $breakdown->shipment_id,
            'shipment_order_id' => $breakdown->shipment_order_id,
            'submission_price_breakdown_id' => $breakdown->submission_price_breakdown_id,
            'pricing_policy_id' => $breakdown->pricing_policy_id,
            'operational_actuals_id' => $breakdown->operational_actuals_id,
            'submission_total' => (float) $breakdown->submission_total,
            'final_total' => (float) $breakdown->final_total,
            'price_delta' => (float) $breakdown->price_delta,
            'price_delta_percentage' => $breakdown->price_delta_percentage !== null
                ? (float) $breakdown->price_delta_percentage
                : null,
            'change_direction' => $breakdown->change_direction?->value,
            'material_change' => $breakdown->material_change,
            'currency' => $breakdown->currency,
            'calculated_at' => $breakdown->calculated_at?->format('Y-m-d H:i:s'),
            'calculated_by_user_id' => $breakdown->calculated_by_user_id,
            'calculation_trigger' => $breakdown->calculation_trigger?->value,
            'pricing_policy' => $this->when(
                $breakdown->relationLoaded('pricingPolicy') && $breakdown->pricingPolicy !== null,
                fn () => [
                    'id' => $breakdown->pricingPolicy->id,
                    'name' => $breakdown->pricingPolicy->name,
                    'source' => 'frozen_at_submission',
                ]
            ),
            'operational_actuals' => $this->when(
                $breakdown->relationLoaded('operationalActuals') && $breakdown->operationalActuals !== null,
                fn () => new ShipmentOperationalActualsResource($breakdown->operationalActuals)
            ),
            'variances' => OpsShipmentPriceVarianceResource::collection(
                $this->whenLoaded('variances')
            ),
        ];

        if ($chargesUsable) {
            $policyId = $json['pricingPolicyId'] ?? $breakdown->pricing_policy_id;
            $policyName = $json['pricingPolicyName'] ?? null;
            if ($policyName === null && $breakdown->relationLoaded('pricingPolicy') && $breakdown->pricingPolicy !== null) {
                $policyName = $breakdown->pricingPolicy->name;
            }

            $result['breakdown'] = [
                'currency' => $breakdown->currency,
                'pricing_policy' => [
                    'id' => $policyId,
                    'name' => $policyName,
                ],
                'charges' => $this->mapCharges($json),
                'subtotal' => (float) $json['subtotal'],
                'discount' => [
                    'amount' => (float) $json['discount'],
                    'type' => $json['discountType'] ?? null,
                    'rate' => isset($json['discountRate']) ? (float) $json['discountRate'] : null,
                ],
                'after_discount' => (float) $json['afterDiscount'],
                'minimum_fare_adjustment' => (float) ($json['minimumFareAdjustment'] ?? 0),
                'after_minimum_fare' => (float) ($json['afterMinimumFare'] ?? 0),
                'tax' => [
                    'amount' => (float) $json['tax'],
                    'rate' => isset($json['taxRate']) ? (float) $json['taxRate'] : null,
                ],
                'total' => (float) $json['finalTotal'],
                'service_items' => $json['serviceItems'] ?? [],
                'applied_rules' => $json['appliedRules'] ?? [],
                'pricing_rule_resolution' => [
                    'base_rate' => isset($json['baseRate']) ? (float) $json['baseRate'] : null,
                    'distance_tier_rate' => isset($json['distanceTierRate']) ? (float) $json['distanceTierRate'] : null,
                    'base_rate_resolution_type' => $json['baseRateResolutionType'] ?? null,
                ],
                'warnings' => $json['warnings'] ?? [],
            ];
        } else {
            Log::warning('Malformed shipment final price breakdown JSON', [
                'shipment_final_price_breakdown_id' => $breakdown->id,
                'shipment_id' => $breakdown->shipment_id,
            ]);

            $result['detail_status'] = 'degraded';
            $result['warnings'] = ['Final price breakdown detail unavailable'];
        }

        return $result;
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
        return isset($json['finalTotal'], $json['freightCharge'], $json['subtotal']);
    }
}
