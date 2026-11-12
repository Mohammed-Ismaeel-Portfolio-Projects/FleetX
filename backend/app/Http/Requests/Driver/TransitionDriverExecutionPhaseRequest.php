<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Driver execution phase mutations (D4) — ownership never from client.
 */
class TransitionDriverExecutionPhaseRequest extends FormRequest
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
            'shipment_id' => ['prohibited'],
            'execution_phase' => ['prohibited'],
            'status_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'user_id.prohibited' => 'User scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'shipment_id.prohibited' => 'Shipment scope cannot be supplied by the client.',
            'execution_phase.prohibited' => 'Execution phase cannot be supplied by the client.',
            'status_id.prohibited' => 'Status cannot be supplied by the client.',
        ];
    }
}
