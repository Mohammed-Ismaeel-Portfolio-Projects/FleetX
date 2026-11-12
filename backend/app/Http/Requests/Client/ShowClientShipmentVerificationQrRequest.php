<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for GET /api/v1/client/shipments/{shipment}/verification-qr (CLIENT-QR-02).
 * Company scope cannot be supplied by the client in inputs or query string.
 */
class ShowClientShipmentVerificationQrRequest extends FormRequest
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
            'shipment_owner_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'shipment_owner_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'customer_id.prohibited' => 'Company scope cannot be supplied by the client.',
        ];
    }
}
