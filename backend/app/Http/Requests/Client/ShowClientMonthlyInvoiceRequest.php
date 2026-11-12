<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for GET /api/v1/client/monthly-invoices/{monthlyInvoice} (Phase 6c).
 */
class ShowClientMonthlyInvoiceRequest extends FormRequest
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
