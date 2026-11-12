<?php

namespace App\Http\Resources\Invoice;

use App\Models\InvoiceLineItem;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops invoice line item with audit reference to persisted final price (Phase 6c).
 */
class OpsInvoiceLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InvoiceLineItem $lineItem */
        $lineItem = $this->resource;

        return [
            'id' => $lineItem->id,
            'shipment_id' => $lineItem->shipment_id,
            'shipment_reference' => $this->shipmentReference(
                $lineItem->relationLoaded('shipment') ? $lineItem->shipment : null,
            ),
            'final_price_breakdown_id' => $lineItem->shipment_final_price_breakdown_id,
            'description' => $lineItem->description,
            'quantity' => $lineItem->quantity,
            'unit_price' => (float) $lineItem->unit_price,
            'line_total' => (float) $lineItem->line_total,
            'currency' => $lineItem->currency,
            'final_price_breakdown' => $this->when(
                $lineItem->relationLoaded('finalPriceBreakdown') && $lineItem->finalPriceBreakdown !== null,
                fn () => $this->auditFinalPriceSnapshot($lineItem->finalPriceBreakdown),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditFinalPriceSnapshot(ShipmentFinalPriceBreakdown $breakdown): array
    {
        return [
            'id' => $breakdown->id,
            'status' => $breakdown->getAttributes()['status'] ?? $breakdown->status?->value,
            'final_total' => (float) $breakdown->final_total,
            'currency' => $breakdown->currency,
            'calculated_at' => $breakdown->calculated_at?->format('Y-m-d H:i:s'),
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
