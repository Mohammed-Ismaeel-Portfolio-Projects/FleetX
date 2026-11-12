<?php

namespace App\Http\Requests\Dispatcher;

use Illuminate\Foundation\Http\FormRequest;

class TrackingSessionOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'forced_speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'forced_deceleration' => ['nullable', 'boolean'],
            'forced_route_deviation' => ['nullable', 'boolean'],
        ];
    }
}
