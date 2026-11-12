<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops operational actuals response (Phase 5.5a).
 */
class ShipmentOperationalActualsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'actual_distance_km' => $this->actual_distance_km !== null
                ? (float) $this->actual_distance_km
                : null,
            'actual_cargo_weight' => $this->actual_cargo_weight !== null
                ? (float) $this->actual_cargo_weight
                : null,
            'actual_loading_wait_minutes' => $this->actual_loading_wait_minutes,
            'actual_unloading_wait_minutes' => $this->actual_unloading_wait_minutes,
            'actual_traffic_delay_minutes' => $this->actual_traffic_delay_minutes,
            'actual_customer_delay_minutes' => $this->actual_customer_delay_minutes,
            'actual_border_crossing' => $this->actual_border_crossing,
            'actual_hazmat' => $this->actual_hazmat,
            'actual_service_ids' => $this->actual_service_ids,
            'actual_trailer_type' => $this->actual_trailer_type,
            'capture_source' => $this->capture_source?->value,
            'captured_at' => $this->captured_at?->format('Y-m-d H:i:s'),
            'captured_by_user_id' => $this->captured_by_user_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
