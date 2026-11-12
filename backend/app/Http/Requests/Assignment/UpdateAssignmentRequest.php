<?php

namespace App\Http\Requests\Assignment;

class UpdateAssignmentRequest extends FoundationAssignmentRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipment_id' => 'sometimes|exists:shipments,id',
            'assignment_code' => 'sometimes|string|max:50|unique:assignments,assignment_code,' . $this->route('assignment'),
            'driver_id' => 'sometimes|exists:drivers,id',
            'vehicle_id' => 'sometimes|nullable|exists:vehicles,id',
            'trailer_id' => 'sometimes|nullable|exists:trailers,id',
            'assigned_by' => 'sometimes|exists:users,id',
            'status_id' => 'sometimes|exists:statuses,id',
            'assigned_at' => 'sometimes|date',
            'ended_at' => 'sometimes|nullable|date|after:assigned_at',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:5000',
        ];
    }
}
