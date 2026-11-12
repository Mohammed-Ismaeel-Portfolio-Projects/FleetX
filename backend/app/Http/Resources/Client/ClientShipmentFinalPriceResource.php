<?php

namespace App\Http\Resources\Client;

use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing persisted final shipment price (Phase 5.5d).
 * Projects ShipmentFinalPriceBreakdown only — never recalculates pricing.
 */
class ClientShipmentFinalPriceResource extends JsonResource
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
            'status' => $breakdown->status?->value,
            'submission_price' => [
                'total' => (float) $breakdown->submission_total,
                'currency' => $breakdown->currency,
                'calculated_at' => $this->submissionCalculatedAt($breakdown),
                'label' => 'Price confirmed at submission',
            ],
            'final_price' => [
                'total' => (float) $breakdown->final_total,
                'currency' => $breakdown->currency,
                'calculated_at' => $breakdown->calculated_at?->format('Y-m-d H:i:s'),
                'label' => 'Final shipment price',
            ],
            'comparison' => [
                'delta' => (float) $breakdown->price_delta,
                'delta_percentage' => $breakdown->price_delta_percentage !== null
                    ? (float) $breakdown->price_delta_percentage
                    : null,
                'change_direction' => $breakdown->change_direction?->value,
                'material_change' => $breakdown->material_change,
            ],
            'reasons' => $this->mapReasons($breakdown),
        ];

        if ($chargesUsable) {
            $result['charges_summary'] = [
                'charges' => $this->mapCharges($json),
                'subtotal' => (float) $json['subtotal'],
                'discount' => [
                    'amount' => (float) $json['discount'],
                    'type' => $json['discountType'] ?? null,
                    'rate' => isset($json['discountRate']) ? (float) $json['discountRate'] : null,
                ],
                'after_discount' => (float) $json['afterDiscount'],
                'tax' => [
                    'amount' => (float) $json['tax'],
                    'rate' => isset($json['taxRate']) ? (float) $json['taxRate'] : null,
                ],
                'total' => (float) $json['finalTotal'],
                'service_items' => $json['serviceItems'] ?? [],
            ];
        } else {
            $result['warnings'] = ['Final price detail unavailable'];
        }

        return $result;
    }

    /**
     * @return array<int, array{reason_code: string|null, message: string|null, price_impact: float}>
     */
    private function mapReasons(ShipmentFinalPriceBreakdown $breakdown): array
    {
        if (! $breakdown->relationLoaded('variances')) {
            return [];
        }

        return $breakdown->variances
            ->filter(static fn (ShipmentPriceVariance $variance) => $variance->material && $variance->billable)
            ->map(static fn (ShipmentPriceVariance $variance) => [
                'reason_code' => $variance->reason_code,
                'message' => $variance->customer_message,
                'price_impact' => (float) $variance->price_impact,
            ])
            ->values()
            ->all();
    }

    private function submissionCalculatedAt(ShipmentFinalPriceBreakdown $breakdown): ?string
    {
        if ($breakdown->relationLoaded('submissionPriceBreakdown') && $breakdown->submissionPriceBreakdown !== null) {
            return $breakdown->submissionPriceBreakdown->calculated_at?->format('Y-m-d H:i:s');
        }

        return null;
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
