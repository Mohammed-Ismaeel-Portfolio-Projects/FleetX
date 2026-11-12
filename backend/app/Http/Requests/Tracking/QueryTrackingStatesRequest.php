<?php

namespace App\Http\Requests\Tracking;

use Illuminate\Foundation\Http\FormRequest;

class QueryTrackingStatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'assignment_id' => ['nullable', 'integer', 'exists:assignments,id'],
            'shipment_id' => ['nullable', 'string', 'exists:shipments,id'],
            'vehicle_id' => ['nullable', 'string', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'movement_status' => ['nullable', 'in:Idle,Moving,Stopped'],
            'online_status' => ['nullable', 'in:Online,Offline'],
            'signal_quality' => ['nullable', 'in:Good,Weak,Lost'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:last_update_at,created_at,updated_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
