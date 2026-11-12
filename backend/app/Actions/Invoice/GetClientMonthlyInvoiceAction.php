<?php

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Models\MonthlyInvoice;
use App\Models\User;

/**
 * Company-scoped monthly invoice show for authenticated client users (Phase 6c).
 */
class GetClientMonthlyInvoiceAction
{
    public function execute(User $user, int|string $monthlyInvoiceId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $invoice = MonthlyInvoice::query()
            ->forCompany($companyId)
            ->with([
                'lineItems.shipment',
            ])
            ->find($monthlyInvoiceId);

        if ($invoice === null) {
            return ApiResult::notFound('Monthly invoice not found');
        }

        return ApiResult::success($invoice, 'Monthly invoice retrieved successfully');
    }
}
