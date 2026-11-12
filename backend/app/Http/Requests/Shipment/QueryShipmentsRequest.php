<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class QueryShipmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|nullable|string|max:255',
            'customer_company_id' => 'sometimes|uuid|exists:companies,id',
            'executing_company_id' => 'sometimes|nullable|uuid|exists:companies,id',
            'status_id' => 'sometimes|exists:statuses,id',
            'status' => 'sometimes|string|max:100',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'country' => 'sometimes|string|max:100',
            'pickup_from' => 'sometimes|date',
            'pickup_to' => 'sometimes|date|after_or_equal:pickup_from',
            'delivery_from' => 'sometimes|date',
            'delivery_to' => 'sometimes|date|after_or_equal:delivery_from',
            'sort_by' => 'sometimes|in:shipment_number,priority,planned_pickup_date,planned_delivery_date,created_at,updated_at',
            'sort_direction' => 'sometimes|in:asc,desc',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Only touch search when the client sent it — merging search=null on every
        // request makes the key "present" and fails sometimes|string → HTTP 422.
        if ($this->exists('search')) {
            $search = $this->input('search');
            $this->merge([
                'search' => is_string($search) ? trim($search) : $search,
            ]);
        }
    }
}
