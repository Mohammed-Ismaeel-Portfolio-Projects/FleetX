<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDriverTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'recorded_at' => ['nullable', 'date'],
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'vehicle_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'violation' => ['prohibited'],
            'speed_alert' => ['prohibited'],
            'speed_exceeded' => ['prohibited'],
            'safety_event' => ['prohibited'],
            'event_type' => ['prohibited'],
            'freight_class' => ['prohibited'],
            'routing_class' => ['prohibited'],
            'vehicle_class' => ['prohibited'],
        ];
    }
}
