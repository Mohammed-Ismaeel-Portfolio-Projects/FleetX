<?php

namespace App\Http\Requests\ShipmentOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/shipment-requests/{shipmentOrder}/approve (C2.9-B2.2).
 */
class ApproveShipmentOrderRequest extends FormRequest
{
    use ProhibitsOpsShipmentOrderLifecycleInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->lifecycleProhibitedRules(), [
            'reason' => ['sometimes', 'nullable', 'string', 'min:3', 'max:2000'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('reason')) {
            $reason = $this->input('reason');
            if (is_string($reason)) {
                $trimmed = trim($reason);
                $this->merge(['reason' => $trimmed === '' ? null : $trimmed]);
            }
        }
    }
}
