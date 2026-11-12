<?php

namespace App\Http\Requests\ShipmentOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/shipment-requests/{shipmentOrder}/start-review (C2.9-B2.2).
 */
class StartReviewShipmentOrderRequest extends FormRequest
{
    use ProhibitsOpsShipmentOrderLifecycleInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->lifecycleProhibitedRules(), [
            'reason' => ['prohibited'],
        ]);
    }
}
