<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class QueryAssignmentsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
            'shipment_id' => 'sometimes|exists:shipments,id',
            'driver_id' => 'sometimes|exists:drivers,id',
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'trailer_id' => 'sometimes|exists:trailers,id',
            'status_id' => 'sometimes|exists:statuses,id',
            'is_active' => 'sometimes|boolean',
            'assigned_by' => 'sometimes|exists:users,id',
            'assigned_from' => 'sometimes|date',
            'assigned_to' => 'sometimes|date|after_or_equal:assigned_from',
            'sort_by' => 'sometimes|string|in:assignment_code,assigned_at,ended_at,created_at,updated_at',
            'sort_direction' => 'sometimes|string|in:asc,desc',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim($this->search ?? ''),
        ]);
    }
}
