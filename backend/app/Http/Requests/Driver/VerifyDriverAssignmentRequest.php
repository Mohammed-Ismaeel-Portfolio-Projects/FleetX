<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/driver/assignments/{assignment}/pickup|delivery/verify
 */
class VerifyDriverAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge' => ['required', 'string', 'max:512'],
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'execution_phase' => ['prohibited'],
            'status_id' => ['prohibited'],
            'event' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge.required' => 'A verification challenge is required.',
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'shipment_id.prohibited' => 'Shipment scope cannot be supplied by the client.',
            'assignment_id.prohibited' => 'Assignment scope cannot be supplied by the client.',
            'event.prohibited' => 'Verification event cannot be supplied by the client.',
        ];
    }
}
