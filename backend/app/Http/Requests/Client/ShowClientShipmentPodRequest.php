<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for client POD routes.
 * Company scope and storage keys are never accepted from the client.
 */
class ShowClientShipmentPodRequest extends FormRequest
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
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
            'key' => ['prohibited'],
            'image_url' => ['prohibited'],
            'signature' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'disk.prohibited' => 'Storage location cannot be supplied by the client.',
            'path.prohibited' => 'Storage location cannot be supplied by the client.',
            'key.prohibited' => 'Storage location cannot be supplied by the client.',
            'image_url.prohibited' => 'Storage location cannot be supplied by the client.',
            'signature.prohibited' => 'Storage location cannot be supplied by the client.',
        ];
    }
}
