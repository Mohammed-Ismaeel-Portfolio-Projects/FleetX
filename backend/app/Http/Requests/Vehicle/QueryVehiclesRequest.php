<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class QueryVehiclesRequest extends FormRequest
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
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'fuel_type_id' => ['nullable', 'integer', 'exists:fuel_types,id'],
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'is_active' => ['nullable', 'boolean'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:operational_code,fleet_number,plate_number,manufacturer,model,production_year,current_odometer,created_at,updated_at'],
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
    }
}
