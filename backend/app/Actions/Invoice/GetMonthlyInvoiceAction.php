<?php

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Models\MonthlyInvoice;

/**
 * Ops monthly invoice show (Phase 6c).
 */
class GetMonthlyInvoiceAction
{
    public function execute(int|string $monthlyInvoiceId): ApiResult
    {
        $invoice = MonthlyInvoice::query()
            ->with([
                'company',
                'lineItems.shipment',
                'lineItems.finalPriceBreakdown',
            ])
            ->find($monthlyInvoiceId);

        if ($invoice === null) {
            return ApiResult::notFound('Monthly invoice not found');
        }

        return ApiResult::success($invoice, 'Monthly invoice retrieved successfully');
    }
}
