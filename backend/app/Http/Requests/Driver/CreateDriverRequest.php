<?php

namespace App\Http\Requests\Driver;

class CreateDriverRequest extends FoundationDriverRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // Override unique rules for creation
        $rules['employee_number'] = ['required', 'string', 'max:50', 'unique:drivers,employee_number'];
        $rules['email'] = ['required', 'email', 'max:255', 'unique:drivers,email'];
        $rules['phone'] = ['required', 'string', 'max:50', 'unique:drivers,phone'];
        $rules['license_number'] = ['required', 'string', 'max:50', 'unique:drivers,license_number'];

        return $rules;
    }
}
