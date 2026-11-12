<?php

namespace App\Http\Requests\Issue;

use Illuminate\Foundation\Http\FormRequest;

class CreateIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipment_id' => 'sometimes|string|exists:shipments,id',
            'driver_id' => 'sometimes|integer|exists:drivers,id',
            'type' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'status_id' => 'required|integer|exists:statuses,id',
        ];
    }
}
