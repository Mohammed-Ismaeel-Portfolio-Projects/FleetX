<?php

namespace App\Http\Requests\Client;

use App\Support\ShipmentOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Query validation for GET /api/v1/client/shipment-requests (C2.9-A3.4.1 / C5.4).
 * Company scope is never accepted from the client.
 * Optional status filter is Client-list only — does not alter lifecycle.
 */
class QueryClientShipmentRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_company_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'status' => ['sometimes', 'string', Rule::in(ShipmentOrderStatus::all())],
            'search' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('status') && $this->filled('status_id')) {
                $validator->errors()->add(
                    'status',
                    'Provide either status or status_id, not both.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'submitted_by_user_id.prohibited' => 'Ownership fields cannot be supplied by the client.',
            'search.prohibited' => 'Request search is not supported.',
        ];
    }
}
