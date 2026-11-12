<?php

namespace App\Http\Requests\Assignment;

class CreateAssignmentRequest extends FoundationAssignmentRequest
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
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'trailer_id' => 'nullable|exists:trailers,id',
            'assigned_by' => 'nullable',
            'status_id' => 'required|exists:statuses,id',
            'assigned_at' => 'required|date',
            'ended_at' => 'nullable|date|after:assigned_at',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
