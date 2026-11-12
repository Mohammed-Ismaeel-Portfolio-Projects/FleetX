<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\InvoiceLifecycleErrorCode;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lifecycle transitions: draft → finalized, draft → void, finalized → void (Phase 6e).
 *
 * Uses persisted invoice data only. Does not invoke pricing services.
 */
class MonthlyInvoiceLifecycleService
{
    public function finalize(int|string $monthlyInvoiceId): MonthlyInvoice
    {
        return DB::transaction(function () use ($monthlyInvoiceId) {
            $invoice = $this->lockInvoice($monthlyInvoiceId);

            if ($invoice->status === MonthlyInvoiceStatus::FINALIZED) {
                return $this->reloadWithRelations($invoice);
            }

            if ($invoice->status === MonthlyInvoiceStatus::VOID) {
                throw $this->invalidTransition(
                    $invoice->status,
                    MonthlyInvoiceStatus::FINALIZED,
                    'Void invoices cannot be finalized.',
                );
            }

            if ($invoice->status !== MonthlyInvoiceStatus::DRAFT) {
                throw $this->invalidTransition(
                    $invoice->status,
                    MonthlyInvoiceStatus::FINALIZED,
                    'Only draft invoices can be finalized.',
                );
            }

            $this->assertDraftFinalizationInvariants($invoice);

            $invoice->status = MonthlyInvoiceStatus::FINALIZED;
            $invoice->issued_at = now();
            $invoice->save();

            return $this->reloadWithRelations($invoice);
        });
    }

    public function void(int|string $monthlyInvoiceId): MonthlyInvoice
    {
        return DB::transaction(function () use ($monthlyInvoiceId) {
            $invoice = $this->lockInvoice($monthlyInvoiceId, loadLineItems: false);

            if ($invoice->status === MonthlyInvoiceStatus::VOID) {
                throw $this->invalidTransition(
                    $invoice->status,
                    MonthlyInvoiceStatus::VOID,
                    'Invoice is already void.',
                    InvoiceLifecycleErrorCode::INVOICE_ALREADY_VOID,
                );
            }

            if ($invoice->status === MonthlyInvoiceStatus::DRAFT
                || $invoice->status === MonthlyInvoiceStatus::FINALIZED) {
                $invoice->status = MonthlyInvoiceStatus::VOID;
                $invoice->save();

                return $this->reloadWithRelations($invoice);
            }

            throw $this->invalidTransition(
                $invoice->status,
                MonthlyInvoiceStatus::VOID,
                'Invoice cannot be voided from its current status.',
            );
        });
    }

    private function lockInvoice(int|string $monthlyInvoiceId, bool $loadLineItems = true): MonthlyInvoice
    {
        $relations = $loadLineItems
            ? ['lineItems.shipment', 'lineItems.finalPriceBreakdown']
            : [];

        $query = MonthlyInvoice::query()->lockForUpdate();

        if ($relations !== []) {
            $query->with($relations);
        }

        $invoice = $query->find($monthlyInvoiceId);

        if ($invoice === null) {
            throw new MonthlyInvoiceLifecycleException(
                'Monthly invoice not found.',
                404,
                InvoiceLifecycleErrorCode::INVOICE_FINALIZATION_FAILED,
            );
        }

        return $invoice;
    }

    private function reloadWithRelations(MonthlyInvoice $invoice): MonthlyInvoice
    {
        return $invoice->fresh([
            'company',
            'lineItems.shipment',
            'lineItems.finalPriceBreakdown',
        ]);
    }

    private function assertDraftFinalizationInvariants(MonthlyInvoice $invoice): void
    {
        if ($invoice->company_id === null || trim((string) $invoice->company_id) === '') {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice billing company is required before finalization.',
                422,
                InvoiceLifecycleErrorCode::INVALID_INVOICE_DATA,
            );
        }

        if ($invoice->billing_period_start === null
            || $invoice->billing_period_end === null
            || $invoice->billing_period_start->greaterThanOrEqualTo($invoice->billing_period_end)) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice billing period is invalid.',
                422,
                InvoiceLifecycleErrorCode::INVALID_INVOICE_DATA,
            );
        }

        if ($invoice->lineItems->isEmpty()) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice must contain at least one line item before finalization.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_HAS_NO_LINE_ITEMS,
            );
        }

        if (! filled($invoice->currency)) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice currency is required before finalization.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_MISSING_CURRENCY,
            );
        }

        if (! $this->hasValidMoney($invoice->subtotal)) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice subtotal is required before finalization.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_MISSING_SUBTOTAL,
            );
        }

        if (! $this->hasValidMoney($invoice->total)) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice total is required before finalization.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_MISSING_TOTAL,
            );
        }

        foreach ($invoice->lineItems as $lineItem) {
            $this->assertLineItemFinalizationState($lineItem, $invoice->currency, (int) $invoice->id);
        }
    }

    private function assertLineItemFinalizationState(
        InvoiceLineItem $lineItem,
        string $invoiceCurrency,
        int $invoiceId,
    ): void {
        if ((int) $lineItem->monthly_invoice_id !== $invoiceId) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice line item does not belong to this invoice.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_INVALID_LINE_ITEM_STATE,
            );
        }

        if ($lineItem->shipment_id === null
            || $lineItem->shipment_final_price_breakdown_id === null
            || $lineItem->shipment === null
            || ! $this->hasValidMoney($lineItem->unit_price)
            || ! $this->hasValidMoney($lineItem->line_total)
            || ! filled($lineItem->currency)
            || $lineItem->quantity === null
            || $lineItem->quantity < 1) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice line item is missing required billing fields.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_INVALID_LINE_ITEM_STATE,
            );
        }

        if ($lineItem->currency !== $invoiceCurrency) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice line item currency must match invoice currency.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_CURRENCY_MISMATCH,
            );
        }

        $breakdown = $lineItem->finalPriceBreakdown;

        if ($breakdown === null) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice line item final price snapshot is missing.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_INVALID_LINE_ITEM_STATE,
            );
        }

        $this->assertFinalPriceBreakdownIsFinalized($breakdown);
    }

    private function assertFinalPriceBreakdownIsFinalized(ShipmentFinalPriceBreakdown $breakdown): void
    {
        $statusValue = $breakdown->getAttributes()['status'] ?? $breakdown->status?->value ?? null;

        if ($statusValue !== FinalPriceBreakdownStatus::FINALIZED->value) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice line item must reference a finalized shipment final price snapshot.',
                422,
                InvoiceLifecycleErrorCode::INVOICE_NOT_FINALIZABLE,
            );
        }
    }

    private function hasValidMoney(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return false;
        }

        return bccomp($normalized, '0', 2) >= 0;
    }

    private function invalidTransition(
        MonthlyInvoiceStatus $currentStatus,
        MonthlyInvoiceStatus $requestedStatus,
        string $message,
        ?InvoiceLifecycleErrorCode $errorCode = null,
    ): MonthlyInvoiceLifecycleException {
        return new MonthlyInvoiceLifecycleException(
            $message,
            409,
            $errorCode ?? InvoiceLifecycleErrorCode::INVALID_INVOICE_STATUS_TRANSITION,
            [
                'current_status' => $currentStatus->value,
                'requested_status' => $requestedStatus->value,
            ],
        );
    }
}
