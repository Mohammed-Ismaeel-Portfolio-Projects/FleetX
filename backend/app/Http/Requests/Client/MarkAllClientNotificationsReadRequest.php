<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/client/notifications/read-all (C2.8-C).
 */
class MarkAllClientNotificationsReadRequest extends FormRequest
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
