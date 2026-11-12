<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query Companies Request
 * 
 * Validates input for listing companies with pagination, filtering, sorting, and search.
 */
class QueryCompaniesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Authorization is handled by authentication middleware on the route.
     * Permission-based authorization will be added later via Policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Pagination
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            
            // Search
            'search' => ['nullable', 'string', 'max:255'],
            
            // Filters
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'country' => ['nullable', 'string', 'size:2'],
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'company_size_id' => ['nullable', 'integer', 'exists:company_sizes,id'],
            'company_type' => ['nullable', 'string', 'in:internal,client,partner_carrier,hybrid'],
            'is_active' => ['nullable', 'boolean'],
            
            // Sorting
            'sort_by' => ['nullable', 'string', 'in:company_name,legal_name,company_code,country,city,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
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

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'Cannot request more than 100 items per page.',
            'sort_by.in' => 'Invalid sort field. Allowed fields: company_name, legal_name, company_code, country, city, created_at, updated_at.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
            'country.size' => 'Country code must be 2 characters (ISO 3166-1 alpha-2).',
        ];
    }
}
