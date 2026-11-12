<?php

namespace App\Http\Requests\Invoice;

use App\Enums\MonthlyInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query validation for GET /api/v1/monthly-invoices (Phase 6c).
 */
class QueryMonthlyInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'company_id' => ['sometimes', 'uuid', 'exists:companies,id'],
            'billing_period_start' => ['sometimes', 'date'],
            'billing_period_end' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::enum(MonthlyInvoiceStatus::class)],
            'sort_by' => ['sometimes', 'in:created_at,updated_at,billing_period_start,billing_period_end,total,subtotal,currency,status'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
