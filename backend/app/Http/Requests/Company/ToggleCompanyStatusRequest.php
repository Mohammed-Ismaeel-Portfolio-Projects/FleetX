<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Toggle Company Status Request
 * 
 * Validates input for toggling company active status.
 * Only accepts is_active boolean field.
 */
class ToggleCompanyStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'is_active.required' => 'The is_active field is required.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}
