<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class CompleteDriverAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'status_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'execution_phase' => ['prohibited'],
        ];
    }
}
