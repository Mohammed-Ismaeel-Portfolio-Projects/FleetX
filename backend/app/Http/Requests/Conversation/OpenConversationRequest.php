<?php

namespace App\Http\Requests\Conversation;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Conversation::allowedTypes())],
            'shipment_id' => ['required', 'uuid', 'exists:shipments,id'],
            'driver_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'client_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
