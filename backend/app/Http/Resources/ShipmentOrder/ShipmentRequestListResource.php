<?php

namespace App\Http\Resources\ShipmentOrder;

use App\Support\ClientShipmentRequestCoordinateRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops Shipment Request list item (C2.9-B2.1).
 */
class ShipmentRequestListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'reference_number' => $this->reference_number,
            'customer_company' => $this->whenLoaded('customerCompany', fn () => [
                'id' => $this->customerCompany->id,
                'company_name' => $this->customerCompany->company_name,
                'company_code' => $this->customerCompany->company_code,
            ]),
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
            'converted_shipment_id' => $this->converted_shipment_id,
            'submission_revision' => $this->submission_revision,
            'cargo_type' => $this->cargo_type,
            'cargo_description' => $this->cargo_description,
            'cargo_weight' => $this->cargo_weight,
            'package_count' => $this->package_count,
            'trailer_type' => $this->trailer_type,
            'pickup_city' => $this->pickup_city,
            'pickup_country' => $this->pickup_country,
            'pickup_latitude' => ClientShipmentRequestCoordinateRules::toFloat($this->pickup_latitude),
            'pickup_longitude' => ClientShipmentRequestCoordinateRules::toFloat($this->pickup_longitude),
            'delivery_city' => $this->delivery_city,
            'delivery_country' => $this->delivery_country,
            'delivery_latitude' => ClientShipmentRequestCoordinateRules::toFloat($this->delivery_latitude),
            'delivery_longitude' => ClientShipmentRequestCoordinateRules::toFloat($this->delivery_longitude),
            'requested_pickup_date' => $this->requested_pickup_date?->format('Y-m-d'),
            'requested_delivery_date' => $this->requested_delivery_date?->format('Y-m-d'),
            'priority' => $this->priority,
            'price_summary' => $this->priceSummary(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{currency: string, total: float, calculated_at: string|null}|null
     */
    private function priceSummary(): ?array
    {
        if (! $this->relationLoaded('priceBreakdown') || $this->priceBreakdown === null) {
            return null;
        }

        return [
            'currency' => $this->priceBreakdown->currency,
            'total' => (float) $this->priceBreakdown->final_total,
            'calculated_at' => $this->priceBreakdown->calculated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
