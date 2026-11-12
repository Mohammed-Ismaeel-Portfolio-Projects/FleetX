<?php

namespace App\Http\Requests\Client;

use App\Enums\MonthlyInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query validation for GET /api/v1/client/monthly-invoices (Phase 6c).
 */
class QueryClientMonthlyInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'],
            'customer_company_id' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'billing_period_start' => ['sometimes', 'date'],
            'billing_period_end' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::enum(MonthlyInvoiceStatus::class)],
            'sort_by' => ['sometimes', 'in:created_at,billing_period_start,billing_period_end,total,subtotal,currency,status'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
        ];
    }
}
