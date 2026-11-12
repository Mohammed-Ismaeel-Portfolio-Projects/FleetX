<?php

namespace App\Http\Requests\Driver;

use App\Support\DriverIssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDriverIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(DriverIssueType::all())],
            'description' => ['nullable', 'string', 'max:1000'],
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'status_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
