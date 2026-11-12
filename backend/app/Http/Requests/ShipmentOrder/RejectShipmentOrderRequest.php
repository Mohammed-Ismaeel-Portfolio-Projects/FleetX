<?php

namespace App\Http\Requests\ShipmentOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/shipment-requests/{shipmentOrder}/reject (C2.9-B2.2).
 */
class RejectShipmentOrderRequest extends FormRequest
{
    use ProhibitsOpsShipmentOrderLifecycleInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->lifecycleProhibitedRules(), [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('reason')) {
            $reason = $this->input('reason');
            $this->merge([
                'reason' => is_string($reason) ? trim($reason) : $reason,
            ]);
        }
    }
}
