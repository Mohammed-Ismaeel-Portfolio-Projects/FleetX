<?php

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Models\User;
use App\Services\Invoice\MonthlyInvoicePdfService;

/**
 * Client-scoped monthly invoice PDF export (Phase 6d).
 */
class DownloadClientMonthlyInvoicePdfAction
{
    public function __construct(
        private readonly MonthlyInvoicePdfService $pdfService,
    ) {}

    public function execute(User $user, int|string $monthlyInvoiceId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $invoice = $this->pdfService->loadForClient($companyId, $monthlyInvoiceId);

        if ($invoice === null) {
            return ApiResult::notFound('Monthly invoice not found');
        }

        return ApiResult::success(
            $this->pdfService->renderClient($invoice),
            'Monthly invoice PDF generated successfully',
        );
    }
}
