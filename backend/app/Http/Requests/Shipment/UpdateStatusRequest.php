<?php

namespace App\Http\Requests\Shipment;

use App\Support\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->name === 'admin' || 
               $this->user()?->role?->name === 'dispatcher' ||
               $this->user()?->role?->name === 'driver';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ShipmentStatus::all())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be a canonical shipment status.',
            'notes.max' => 'The notes must not exceed 1000 characters.',
        ];
    }
}
