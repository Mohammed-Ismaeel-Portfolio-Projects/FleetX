<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/driver/assignments/{assignment}/acknowledge
 */
class AcknowledgeDriverAssignmentRequest extends FormRequest
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
            'status_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'user_id.prohibited' => 'User scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'status_id.prohibited' => 'Status cannot be supplied by the client.',
            'status.prohibited' => 'Status cannot be supplied by the client.',
        ];
    }
}
