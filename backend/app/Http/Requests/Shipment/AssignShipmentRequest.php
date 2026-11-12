<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class AssignShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->name === 'admin' || $this->user()?->role?->name === 'dispatcher';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'truck_id' => ['required', 'integer', 'exists:trucks,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'driver_id.required' => 'The driver ID is required.',
            'driver_id.exists' => 'The specified driver does not exist.',
            'truck_id.required' => 'The truck ID is required.',
            'truck_id.exists' => 'The specified truck does not exist.',
        ];
    }
}
