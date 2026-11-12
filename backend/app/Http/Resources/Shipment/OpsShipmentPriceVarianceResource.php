<?php

namespace App\Http\Resources\Shipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops-facing variance row for final shipment price reads (Phase 5.5d).
 *
 * @mixin \App\Models\ShipmentPriceVariance
 */
class OpsShipmentPriceVarianceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'factor_code' => $this->factor_code?->value,
            'baseline_value' => $this->baseline_value !== null ? (float) $this->baseline_value : null,
            'actual_value' => $this->actual_value !== null ? (float) $this->actual_value : null,
            'delta_value' => $this->delta_value !== null ? (float) $this->delta_value : null,
            'delta_unit' => $this->delta_unit,
            'threshold_value' => $this->threshold_value !== null ? (float) $this->threshold_value : null,
            'threshold_type' => $this->threshold_type?->value,
            'threshold_scope' => $this->threshold_scope,
            'material' => $this->material,
            'billable' => $this->billable,
            'price_impact' => (float) $this->price_impact,
            'reason_code' => $this->reason_code,
            'customer_message' => $this->customer_message,
            'internal_message' => $this->internal_message,
            'data_source' => $this->data_source,
            'sort_order' => $this->sort_order,
        ];
    }
}
