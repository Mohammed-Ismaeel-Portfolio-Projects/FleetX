<?php

namespace App\Http\Requests\ShipmentOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query validation for GET /api/v1/shipment-requests (C2.9-B2.1).
 */
class QueryShipmentRequestsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_company_id' => ['sometimes', 'uuid', 'exists:companies,id'],
            'status' => ['sometimes', 'string', 'max:100'],
            'status_id' => ['sometimes', 'integer', 'exists:statuses,id'],
            'exclude_converted' => ['sometimes', 'boolean'],
            'requested_pickup_date_from' => ['sometimes', 'date'],
            'requested_pickup_date_to' => ['sometimes', 'date', 'after_or_equal:requested_pickup_date_from'],
            'company_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'submission_revision' => ['prohibited'],
            'payload_json' => ['prohibited'],
            'converted_shipment_id' => ['prohibited'],
            'sort_by' => ['prohibited'],
            'sort_column' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('search')) {
            $search = $this->input('search');
            $this->merge([
                'search' => is_string($search) ? trim($search) : $search,
            ]);
        }

        if ($this->has('exclude_converted')) {
            $this->merge([
                'exclude_converted' => filter_var($this->input('exclude_converted'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
