<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/v1/driver/availability — identity never accepted from client.
 */
class UpdateDriverAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'availability' => ['required', 'string', 'in:available,unavailable'],
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability.in' => 'Availability must be available or unavailable.',
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'user_id.prohibited' => 'User scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
        ];
    }
}
