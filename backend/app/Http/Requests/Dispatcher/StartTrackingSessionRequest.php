<?php

namespace App\Http\Requests\Dispatcher;

use Illuminate\Foundation\Http\FormRequest;

class StartTrackingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipment_order_id' => ['required', 'uuid', 'exists:shipment_orders,id'],
        ];
    }
}
