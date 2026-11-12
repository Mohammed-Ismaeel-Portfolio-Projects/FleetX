<?php

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Services\Invoice\Documents\InvoicePdfResult;
use App\Services\Invoice\MonthlyInvoicePdfService;

/**
 * Ops monthly invoice PDF export (Phase 6d).
 */
class DownloadOpsMonthlyInvoicePdfAction
{
    public function __construct(
        private readonly MonthlyInvoicePdfService $pdfService,
    ) {}

    public function execute(int|string $monthlyInvoiceId): ApiResult
    {
        $invoice = $this->pdfService->loadForOps($monthlyInvoiceId);

        if ($invoice === null) {
            return ApiResult::notFound('Monthly invoice not found');
        }

        return ApiResult::success(
            $this->pdfService->renderOps($invoice),
            'Monthly invoice PDF generated successfully',
        );
    }
}
