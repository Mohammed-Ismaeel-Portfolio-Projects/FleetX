<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe Shipment Request list item (C2.9-A3.4.1).
 * Reduced presentation — no addresses, contacts, notes, or internal fields.
 */
class ClientShipmentRequestListResource extends JsonResource
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
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
            'submission_revision' => $this->submission_revision,
            'cargo_type' => $this->cargo_type,
            'cargo_description' => $this->cargo_description,
            'package_count' => $this->package_count,
            'pickup_city' => $this->pickup_city,
            'pickup_country' => $this->pickup_country,
            'pickup_company' => $this->pickup_company,
            'delivery_city' => $this->delivery_city,
            'delivery_country' => $this->delivery_country,
            'delivery_company' => $this->delivery_company,
            'requested_pickup_date' => $this->requested_pickup_date?->format('Y-m-d'),
            'requested_delivery_date' => $this->requested_delivery_date?->format('Y-m-d'),
            'linked_shipment' => ClientLinkedShipmentResource::summary(
                $this->relationLoaded('convertedShipment') ? $this->convertedShipment : null
            ),
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
