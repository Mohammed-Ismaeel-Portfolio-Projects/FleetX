<?php

namespace App\Http\Requests\Trailer;

use Illuminate\Foundation\Http\FormRequest;

class QueryTrailersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'trailer_type' => ['nullable', 'string', 'max:50'],
            'requested_trailer_type' => ['nullable', 'string', 'max:50'],
            'shipment_id' => ['nullable', 'uuid', 'exists:shipments,id'],
            'available_only' => ['nullable', 'boolean'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:trailer_code,fleet_number,plate_number,trailer_type,manufacturer,production_year,current_odometer,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.max' => 'Cannot request more than 100 items per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('available_only')) {
            $this->merge([
                'available_only' => filter_var($this->input('available_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
