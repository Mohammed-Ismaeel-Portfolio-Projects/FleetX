<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class QueryConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|string|max:40',
            'shipment_id' => 'sometimes|uuid|exists:shipments,id',
            'status' => 'sometimes|string|in:open,closed',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
