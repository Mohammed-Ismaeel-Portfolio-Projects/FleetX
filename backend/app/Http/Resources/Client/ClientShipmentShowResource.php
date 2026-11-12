<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe shipment show payload (C2.6-C5-D2 / C2.6-C5-D3).
 * Whitelist only — no driver, contacts, street addresses, notes, assignment, or tracking.
 */
class ClientShipmentShowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'reference_number' => $this->reference_number,
            'priority' => $this->priority,
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
            'pickup_city' => $this->pickup_city,
            'pickup_country' => $this->pickup_country,
            'pickup_company' => $this->pickup_company,
            'delivery_city' => $this->delivery_city,
            'delivery_country' => $this->delivery_country,
            'delivery_company' => $this->delivery_company,
            'planned_pickup_date' => $this->planned_pickup_date?->format('Y-m-d'),
            'planned_delivery_date' => $this->planned_delivery_date?->format('Y-m-d'),
            'actual_pickup_date' => $this->actual_pickup_date?->format('Y-m-d'),
            'actual_delivery_date' => $this->actual_delivery_date?->format('Y-m-d'),
            'cargo_type' => $this->cargo_type,
            'cargo_description' => $this->cargo_description,
            'package_count' => $this->package_count,
            'customer_company' => $this->whenLoaded('customerCompany', fn () => [
                'id' => $this->customerCompany->id,
                'company_code' => $this->customerCompany->company_code,
                'company_name' => $this->customerCompany->company_name,
            ]),
            'final_price' => $this->relationLoaded('finalPriceBreakdown')
                && $this->finalPriceBreakdown !== null
                && $this->finalPriceBreakdown->isFinalized()
                ? new ClientShipmentFinalPriceResource($this->finalPriceBreakdown)
                : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
