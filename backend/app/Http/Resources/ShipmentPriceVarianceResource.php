<?php

namespace App\Http\Resources;

use App\Models\ShipmentPriceVariance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShipmentPriceVariance
 */
class ShipmentPriceVarianceResource extends JsonResource
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
            'material' => $this->material,
            'billable' => $this->billable,
            'price_impact' => (float) $this->price_impact,
            'reason_code' => $this->reason_code,
            'sort_order' => $this->sort_order,
        ];
    }
}
