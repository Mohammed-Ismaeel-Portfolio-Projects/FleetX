<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for GET /api/v1/client/shipments/{shipment}/tracking (C2.7-C).
 * Company scope and ops probe params are never accepted from the client.
 */
class ShowClientShipmentTrackingRequest extends FormRequest
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
            'tenant_id' => ['prohibited'],
            'driver_id' => ['prohibited'],
            'vehicle_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'tenant_id.prohibited' => 'Tenant scope cannot be supplied by the client.',
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'vehicle_id.prohibited' => 'Vehicle scope cannot be supplied by the client.',
            'assignment_id.prohibited' => 'Assignment scope cannot be supplied by the client.',
        ];
    }
}
