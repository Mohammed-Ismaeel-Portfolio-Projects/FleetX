<?php

namespace App\Http\Requests\Driver;

class UpdateDriverRequest extends FoundationDriverRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $driverId = $this->route('driver')->id;

        // Override unique rules for update to exclude current driver
        $rules['employee_number'] = ['nullable', 'string', 'max:50', 'unique:drivers,employee_number,' . $driverId];
        $rules['first_name'] = ['nullable', 'string', 'max:100'];
        $rules['last_name'] = ['nullable', 'string', 'max:100'];
        $rules['email'] = ['nullable', 'email', 'max:255', 'unique:drivers,email,' . $driverId];
        $rules['phone'] = ['nullable', 'string', 'max:50', 'unique:drivers,phone,' . $driverId];
        $rules['nationality'] = ['nullable', 'string', 'max:100'];
        $rules['date_of_birth'] = ['nullable', 'date', 'before:today'];
        $rules['employment_date'] = ['nullable', 'date', 'before_or_equal:today'];
        $rules['license_number'] = ['nullable', 'string', 'max:50', 'unique:drivers,license_number,' . $driverId];
        $rules['license_class'] = ['nullable', 'string', 'max:50'];
        $rules['license_expiry_date'] = ['nullable', 'date', 'after:today'];
        $rules['medical_certificate_expiry'] = ['nullable', 'date', 'after:today'];
        $rules['years_of_experience'] = ['nullable', 'integer', 'min:0', 'max:50'];
        $rules['status_id'] = ['nullable', 'integer', 'exists:statuses,id'];
        $rules['is_active'] = ['nullable', 'boolean'];
        $rules['emergency_contact_name'] = ['nullable', 'string', 'max:100'];
        $rules['emergency_contact_phone'] = ['nullable', 'string', 'max:50'];
        $rules['notes'] = ['nullable', 'string'];

        return $rules;
    }
}
