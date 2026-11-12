<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class FoundationDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:50', 'unique:drivers,employee_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:drivers,email'],
            'phone' => ['required', 'string', 'max:50', 'unique:drivers,phone'],
            'nationality' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'employment_date' => ['required', 'date', 'before_or_equal:today'],
            'license_number' => ['required', 'string', 'max:50', 'unique:drivers,license_number'],
            'license_class' => ['required', 'string', 'max:50'],
            'license_expiry_date' => ['required', 'date', 'after:today'],
            'medical_certificate_expiry' => ['nullable', 'date', 'after:today'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:50'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'is_active' => ['nullable', 'boolean'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_number.unique' => 'A driver with this employee number already exists.',
            'email.unique' => 'A driver with this email already exists.',
            'phone.unique' => 'A driver with this phone number already exists.',
            'license_number.unique' => 'A driver with this license number already exists.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'employment_date.before_or_equal' => 'Employment date cannot be in the future.',
            'license_expiry_date.after' => 'License expiry date must be in the future.',
            'medical_certificate_expiry.after' => 'Medical certificate expiry must be in the future.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_number' => $this->input('employee_number') ? strtoupper(trim($this->input('employee_number'))) : null,
            'license_number' => $this->input('license_number') ? strtoupper(trim($this->input('license_number'))) : null,
            'phone' => $this->input('phone') ? trim($this->input('phone')) : null,
            'email' => $this->input('email') ? strtolower(trim($this->input('email'))) : null,
            'first_name' => $this->input('first_name') ? trim($this->input('first_name')) : null,
            'last_name' => $this->input('last_name') ? trim($this->input('last_name')) : null,
            'nationality' => $this->input('nationality') ? trim($this->input('nationality')) : null,
        ]);
    }
}
