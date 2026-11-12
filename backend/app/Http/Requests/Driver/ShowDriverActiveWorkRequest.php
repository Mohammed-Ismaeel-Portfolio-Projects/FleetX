<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/driver/work/active — ownership never accepted from client.
 */
class ShowDriverActiveWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'user_id.prohibited' => 'User scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'assignment_id.prohibited' => 'Assignment scope cannot be supplied by the client.',
        ];
    }
}
