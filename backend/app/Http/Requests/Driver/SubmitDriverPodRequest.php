<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDriverPodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'signature' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,application/json,text/plain'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_role' => ['nullable', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'condition_status' => ['required', 'in:good,damaged,partial,refused'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'image_url' => ['prohibited'],
            'confirmed_at' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
