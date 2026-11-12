<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Models\MonthlyInvoice;
use App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory;
use App\Services\Invoice\Documents\InvoicePdfResult;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generates invoice PDF documents from persisted billing snapshots (Phase 6d).
 *
 * Does not recalculate pricing or mutate invoice records.
 */
class MonthlyInvoicePdfService
{
    public function __construct(
        private readonly InvoiceDocumentViewModelFactory $viewModelFactory,
    ) {}

    public function loadForOps(int|string $monthlyInvoiceId): ?MonthlyInvoice
    {
        return MonthlyInvoice::query()
            ->with([
                'company',
                'lineItems.shipment',
                'lineItems.finalPriceBreakdown',
            ])
            ->find($monthlyInvoiceId);
    }

    public function loadForClient(string $companyId, int|string $monthlyInvoiceId): ?MonthlyInvoice
    {
        return MonthlyInvoice::query()
            ->forCompany($companyId)
            ->with([
                'company',
                'lineItems.shipment',
            ])
            ->find($monthlyInvoiceId);
    }

    public function renderOps(MonthlyInvoice $invoice): InvoicePdfResult
    {
        $document = $this->viewModelFactory->forOps($invoice);

        return $this->render(
            view: 'invoices.ops',
            document: $document,
            disposition: 'inline',
        );
    }

    public function renderClient(MonthlyInvoice $invoice): InvoicePdfResult
    {
        $document = $this->viewModelFactory->forClient($invoice);

        return $this->render(
            view: 'invoices.client',
            document: $document,
            disposition: 'attachment',
        );
    }

    private function render(
        string $view,
        \App\Services\Invoice\Documents\InvoiceDocumentViewModel $document,
        string $disposition,
    ): InvoicePdfResult {
        $pdf = Pdf::loadView($view, ['document' => $document])
            ->setPaper('a4', 'portrait');

        return new InvoicePdfResult(
            content: $pdf->output(),
            filename: 'invoice-'.$document->invoiceId.'.pdf',
            disposition: $disposition,
        );
    }
}
