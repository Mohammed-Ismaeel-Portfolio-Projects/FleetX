<?php

namespace App\Http\Resources;

use App\Models\ShipmentFinalPriceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal Ops resource for finalized shipment pricing (Phase 5.5c).
 *
 * @mixin ShipmentFinalPriceBreakdown
 */
class ShipmentFinalPriceBreakdownResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'shipment_order_id' => $this->shipment_order_id,
            'submission_price_breakdown_id' => $this->submission_price_breakdown_id,
            'pricing_policy_id' => $this->pricing_policy_id,
            'operational_actuals_id' => $this->operational_actuals_id,
            'submission_total' => (float) $this->submission_total,
            'final_total' => (float) $this->final_total,
            'price_delta' => (float) $this->price_delta,
            'price_delta_percentage' => $this->price_delta_percentage !== null
                ? (float) $this->price_delta_percentage
                : null,
            'change_direction' => $this->change_direction?->value,
            'material_change' => $this->material_change,
            'currency' => $this->currency,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'calculated_by_user_id' => $this->calculated_by_user_id,
            'calculation_trigger' => $this->calculation_trigger?->value,
            'status' => $this->status?->value,
            'variances' => ShipmentPriceVarianceResource::collection(
                $this->whenLoaded('variances')
            ),
        ];
    }
}
