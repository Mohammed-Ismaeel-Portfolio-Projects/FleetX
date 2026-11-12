<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/client/notifications (C2.8-B / C2.8-C).
 * Ownership / company scope never accepted from the client.
 */
class QueryClientNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'customer_company_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'recipient_id' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_read' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.prohibited' => 'Notification ownership cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'owner_id.prohibited' => 'Notification ownership cannot be supplied by the client.',
            'recipient_id.prohibited' => 'Notification ownership cannot be supplied by the client.',
        ];
    }
}
