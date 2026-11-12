<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/driver/devices/register — ownership never accepted from client.
 */
class RegisterDriverDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_token' => ['required', 'string', 'min:16', 'max:255'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'user_id.prohibited' => 'User scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
        ];
    }
}
