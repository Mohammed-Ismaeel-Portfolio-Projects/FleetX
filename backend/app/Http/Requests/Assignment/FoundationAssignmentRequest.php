<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class FoundationAssignmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipment_id' => 'required|exists:shipments,id',
            'assignment_code' => 'required|string|max:50|unique:assignments,assignment_code',
            'driver_id' => 'required|exists:drivers,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'trailer_id' => 'nullable|exists:trailers,id',
            'assigned_by' => 'required|exists:users,id',
            'status_id' => 'required|exists:statuses,id',
            'assigned_at' => 'required|date',
            'ended_at' => 'nullable|date|after:assigned_at',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'assignment_code' => strtoupper(trim($this->assignment_code ?? '')),
        ]);
    }
}
