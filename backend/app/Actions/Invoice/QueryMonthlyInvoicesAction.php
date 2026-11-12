<?php

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Models\MonthlyInvoice;

/**
 * Global monthly invoice list for Dispatcher/Admin (Phase 6c).
 */
class QueryMonthlyInvoicesAction
{
    public function execute(array $params): ApiResult
    {
        $query = MonthlyInvoice::query()
            ->with(['company'])
            ->withCount('lineItems');

        if (! empty($params['company_id'])) {
            $query->where('company_id', $params['company_id']);
        }

        if (! empty($params['billing_period_start'])) {
            $query->whereDate('billing_period_start', $params['billing_period_start']);
        }

        if (! empty($params['billing_period_end'])) {
            $query->whereDate('billing_period_end', $params['billing_period_end']);
        }

        if (! empty($params['currency'])) {
            $query->where('currency', strtoupper($params['currency']));
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDirection = $params['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        $page = (int) ($params['page'] ?? 1);

        $invoices = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($invoices, 'Monthly invoices retrieved successfully');
    }
}
