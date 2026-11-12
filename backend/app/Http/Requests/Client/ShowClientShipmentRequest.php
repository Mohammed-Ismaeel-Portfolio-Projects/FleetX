<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for GET /api/v1/client/shipments/{shipment} (C2.6-C5-D3).
 * Company scope is never accepted from the client.
 */
class ShowClientShipmentRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
        ];
    }
}
