<?php

namespace App\Http\Requests\ShipmentOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for GET /api/v1/shipment-requests/{shipmentOrder} (C2.9-B2.1).
 */
class ShowShipmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_company_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'status_id' => ['prohibited'],
            'submission_revision' => ['prohibited'],
            'payload_json' => ['prohibited'],
        ];
    }
}
