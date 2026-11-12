<?php

namespace App\Http\Requests\Issue;

use Illuminate\Foundation\Http\FormRequest;

class QueryIssuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|string',
            'status_id' => 'sometimes|integer|exists:statuses,id',
            'shipment_id' => 'sometimes|string|exists:shipments,id',
            'driver_id' => 'sometimes|integer|exists:drivers,id',
            'sort_by' => 'sometimes|string|in:created_at,updated_at',
            'sort_direction' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
