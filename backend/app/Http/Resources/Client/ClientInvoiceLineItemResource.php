<?php

namespace App\Http\Resources\Client;

use App\Models\InvoiceLineItem;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe invoice line item (Phase 6c).
 */
class ClientInvoiceLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InvoiceLineItem $lineItem */
        $lineItem = $this->resource;

        return [
            'description' => $lineItem->description,
            'quantity' => $lineItem->quantity,
            'unit_price' => (float) $lineItem->unit_price,
            'line_total' => (float) $lineItem->line_total,
            'currency' => $lineItem->currency,
            'shipment_reference' => $this->shipmentReference(
                $lineItem->relationLoaded('shipment') ? $lineItem->shipment : null,
            ),
        ];
    }

    private function shipmentReference(?Shipment $shipment): ?string
    {
        if ($shipment === null) {
            return null;
        }

        return filled($shipment->reference_number)
            ? $shipment->reference_number
            : $shipment->shipment_number;
    }
}
