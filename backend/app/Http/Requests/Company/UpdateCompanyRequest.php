<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Company Request
 * 
 * Validates input for updating an existing company.
 * All fields are optional - only provided fields will be updated.
 */
class UpdateCompanyRequest extends FormRequest
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
        $companyId = $this->route('company')->id;

        return [
            // Company Identification (all optional for update)
            'company_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'company_code' => ['nullable', 'string', 'max:50', 'unique:companies,company_code,' . $companyId],
            
            // Tax and Registration
            'tax_number' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:100'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            
            // Company Classification
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'company_size_id' => ['nullable', 'integer', 'exists:company_sizes,id'],
            'company_type' => ['nullable', 'string', 'in:internal,client,partner_carrier,hybrid'],
            
            // Contact Information
            'primary_email' => ['nullable', 'email', 'max:255'],
            'primary_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            
            // Address Information
            'country' => ['nullable', 'string', 'size:2'], // ISO 3166-1 alpha-2
            'state_province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            
            // Geographic Data
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            
            // Localization
            'timezone' => ['nullable', 'string', 'max:50'],
            'currency_code' => ['nullable', 'string', 'size:3'], // ISO 4217
            'language_code' => ['nullable', 'string', 'max:5'], // ISO 639-1
            
            // Status and Metadata
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_code.unique' => 'A company with this code already exists.',
            'primary_email.email' => 'Please provide a valid email address.',
            'website.url' => 'Please provide a valid website URL.',
            'country.size' => 'Country code must be 2 characters (ISO 3166-1 alpha-2).',
            'currency_code.size' => 'Currency code must be 3 characters (ISO 4217).',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'industry_id.exists' => 'Selected industry does not exist.',
            'company_size_id.exists' => 'Selected company size does not exist.',
            'status_id.exists' => 'Selected status does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     * Normalizes input data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'primary_email' => $this->normalizeEmail($this->input('primary_email')),
            'website' => $this->normalizeWebsite($this->input('website')),
            'primary_phone' => $this->normalizePhone($this->input('primary_phone')),
            'company_name' => $this->input('company_name') ? trim($this->input('company_name')) : null,
            'legal_name' => $this->input('legal_name') ? trim($this->input('legal_name')) : null,
            'company_code' => $this->input('company_code') ? trim(strtoupper($this->input('company_code'))) : null,
            'city' => $this->input('city') ? trim($this->input('city')) : null,
            'address_line_1' => $this->input('address_line_1') ? trim($this->input('address_line_1')) : null,
            'address_line_2' => $this->input('address_line_2') ? trim($this->input('address_line_2')) : null,
        ]);
    }

    /**
     * Normalize email address.
     */
    protected function normalizeEmail(?string $email): ?string
    {
        return $email ? strtolower(trim($email)) : null;
    }

    /**
     * Normalize website URL.
     */
    protected function normalizeWebsite(?string $website): ?string
    {
        if (!$website) {
            return null;
        }

        $website = trim($website);
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $website)) {
            $website = 'https://' . $website;
        }

        return strtolower($website);
    }

    /**
     * Normalize phone number.
     */
    protected function normalizePhone(?string $phone): ?string
    {
        return $phone ? trim($phone) : null;
    }
}
