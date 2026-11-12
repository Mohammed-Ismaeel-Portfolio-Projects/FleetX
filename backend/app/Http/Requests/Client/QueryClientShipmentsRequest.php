<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Query validation for GET /api/v1/client/shipments (C2.6-B / C2.6-C1).
 * Company scope is never accepted from the client.
 */
class QueryClientShipmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_company_id' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'status' => ['sometimes', 'string', 'max:100'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'country' => ['sometimes', 'string', 'max:100'],
            'pickup_from' => ['sometimes', 'date'],
            'pickup_to' => ['sometimes', 'date', 'after_or_equal:pickup_from'],
            'delivery_from' => ['sometimes', 'date'],
            'delivery_to' => ['sometimes', 'date', 'after_or_equal:delivery_from'],
            'sort_by' => [
                'sometimes',
                'in:shipment_number,priority,planned_pickup_date,planned_delivery_date,created_at,updated_at',
            ],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
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

    protected function prepareForValidation(): void
    {
        if ($this->exists('search')) {
            $search = $this->input('search');
            $this->merge([
                'search' => is_string($search) ? trim($search) : $search,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
        ];
    }
}
