<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class QueryDriversRequest extends FormRequest
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
            'nationality' => ['nullable', 'string', 'max:100'],
            'license_class' => ['nullable', 'string', 'max:50'],
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'status' => ['nullable', 'string', 'in:Available,Busy,Offline,On Leave,Suspended'],
            'is_active' => ['nullable', 'boolean'],
            'min_experience' => ['nullable', 'integer', 'min:0', 'max:50'],
            'max_experience' => ['nullable', 'integer', 'min:0', 'max:50'],
            'sort_by' => ['nullable', 'string', 'in:employee_number,first_name,last_name,email,employment_date,years_of_experience,created_at,updated_at'],
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
