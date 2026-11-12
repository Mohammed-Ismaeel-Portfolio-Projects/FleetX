<?php

declare(strict_types=1);

namespace App\Services\Invoice\Documents;

use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;

/**
 * Builds invoice document view models from persisted billing data only (Phase 6d).
 */
class InvoiceDocumentViewModelFactory
{
    public function forOps(MonthlyInvoice $invoice): InvoiceDocumentViewModel
    {
        return $this->build($invoice, audience: 'ops', includeAuditFields: true);
    }

    public function forClient(MonthlyInvoice $invoice): InvoiceDocumentViewModel
    {
        return $this->build($invoice, audience: 'client', includeAuditFields: false);
    }

    private function build(
        MonthlyInvoice $invoice,
        string $audience,
        bool $includeAuditFields,
    ): InvoiceDocumentViewModel {
        $invoice->loadMissing(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']);

        $lineItems = $invoice->lineItems
            ->sortBy('id')
            ->map(fn (InvoiceLineItem $lineItem) => $this->lineItemViewModel($lineItem, $includeAuditFields))
            ->values()
            ->all();

        return new InvoiceDocumentViewModel(
            audience: $audience,
            invoiceId: $invoice->id,
            status: $invoice->status?->value ?? '',
            companyName: $invoice->company?->company_name ?? '—',
            companyCode: $invoice->company?->company_code,
            billingPeriodStart: $invoice->billing_period_start?->format('Y-m-d') ?? '',
            billingPeriodEnd: $invoice->billing_period_end?->format('Y-m-d') ?? '',
            currency: $invoice->currency,
            subtotalFormatted: InvoiceMoneyFormatter::format((string) $invoice->subtotal, $invoice->currency),
            totalFormatted: InvoiceMoneyFormatter::format((string) $invoice->total, $invoice->currency),
            issuedAtFormatted: $invoice->issued_at?->format('Y-m-d H:i:s'),
            lineItems: $lineItems,
        );
    }

    private function lineItemViewModel(
        InvoiceLineItem $lineItem,
        bool $includeAuditFields,
    ): InvoiceDocumentLineItemViewModel {
        $currency = $lineItem->currency;

        return new InvoiceDocumentLineItemViewModel(
            description: $lineItem->description,
            quantity: $lineItem->quantity,
            unitPriceFormatted: InvoiceMoneyFormatter::format((string) $lineItem->unit_price, $currency),
            lineTotalFormatted: InvoiceMoneyFormatter::format((string) $lineItem->line_total, $currency),
            currency: $currency,
            shipmentReference: $this->shipmentReference(
                $lineItem->relationLoaded('shipment') ? $lineItem->shipment : null,
            ),
            lineItemId: $includeAuditFields ? $lineItem->id : null,
            shipmentId: $includeAuditFields ? $lineItem->shipment_id : null,
            finalPriceBreakdownId: $includeAuditFields ? $lineItem->shipment_final_price_breakdown_id : null,
            finalPriceSnapshotSummary: $includeAuditFields
                ? $this->finalPriceSnapshotSummary(
                    $lineItem->relationLoaded('finalPriceBreakdown') ? $lineItem->finalPriceBreakdown : null,
                )
                : null,
        );
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

    private function finalPriceSnapshotSummary(?ShipmentFinalPriceBreakdown $breakdown): ?string
    {
        if ($breakdown === null) {
            return null;
        }

        $status = $breakdown->getAttributes()['status'] ?? $breakdown->status?->value ?? '';
        $calculatedAt = $breakdown->calculated_at?->format('Y-m-d H:i:s') ?? '';

        return sprintf('Snapshot #%d · %s · %s', $breakdown->id, $status, $calculatedAt);
    }
}
