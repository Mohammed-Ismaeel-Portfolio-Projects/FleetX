<?php

namespace App\Http\Requests\Trailer;

use Illuminate\Foundation\Http\FormRequest;

class ToggleTrailerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'The is_active field is required.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}
