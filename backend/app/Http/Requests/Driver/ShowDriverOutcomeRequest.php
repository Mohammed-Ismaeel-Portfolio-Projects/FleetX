<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class ShowDriverOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
