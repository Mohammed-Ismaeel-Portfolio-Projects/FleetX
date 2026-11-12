<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe shipment list item (C2.6-B / C2.6-C1).
 * No driver, contacts, street addresses, assignment, or tracking payloads.
 */
class ClientShipmentListResource extends JsonResource
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
            'customer_company' => $this->whenLoaded('customerCompany', fn () => [
                'id' => $this->customerCompany->id,
                'company_code' => $this->customerCompany->company_code,
                'company_name' => $this->customerCompany->company_name,
            ]),
            'final_price_summary' => $this->finalPriceSummary(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     total: float,
     *     currency: string,
     *     price_delta: float,
     *     change_direction: string,
     *     material_change: bool,
     *     calculated_at: string|null
     * }|null
     */
    private function finalPriceSummary(): ?array
    {
        if (! $this->relationLoaded('finalPriceBreakdown')
            || $this->finalPriceBreakdown === null
            || ! $this->finalPriceBreakdown->isFinalized()) {
            return null;
        }

        $final = $this->finalPriceBreakdown;

        return [
            'status' => $final->status->value,
            'total' => (float) $final->final_total,
            'currency' => $final->currency,
            'price_delta' => (float) $final->price_delta,
            'change_direction' => $final->change_direction->value,
            'material_change' => $final->material_change,
            'calculated_at' => $final->calculated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
