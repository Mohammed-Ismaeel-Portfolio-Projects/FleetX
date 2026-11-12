<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for GET /api/v1/client/shipment-requests/{shipmentOrder} (C2.9-A3.4.1).
 * Company scope is never accepted from the client.
 */
class ShowClientShipmentRequestRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'submitted_by_user_id.prohibited' => 'Ownership fields cannot be supplied by the client.',
        ];
    }
}
