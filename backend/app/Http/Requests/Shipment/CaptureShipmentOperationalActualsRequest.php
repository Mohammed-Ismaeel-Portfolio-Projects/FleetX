<?php

namespace App\Http\Requests\Shipment;

use App\Enums\OperationalActualsCaptureSource;
use App\Enums\TrailerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaptureShipmentOperationalActualsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipment_id' => ['prohibited'],
            'captured_at' => ['prohibited'],
            'captured_by_user_id' => ['prohibited'],

            'capture_source' => ['required', Rule::enum(OperationalActualsCaptureSource::class)],

            'actual_distance_km' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.999'],
            'actual_cargo_weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'actual_loading_wait_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_unloading_wait_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_traffic_delay_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_customer_delay_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_border_crossing' => ['sometimes', 'nullable', 'boolean'],
            'actual_hazmat' => ['sometimes', 'nullable', 'boolean'],
            'actual_service_ids' => ['sometimes', 'nullable', 'array'],
            'actual_service_ids.*' => ['integer', 'min:1', 'exists:pricing_service_catalog,id'],
            'actual_trailer_type' => ['sometimes', 'nullable', Rule::enum(TrailerType::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['actual_border_crossing', 'actual_hazmat'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'shipment_id.prohibited' => 'Shipment is resolved from the route and cannot be supplied in the request body.',
            'captured_at.prohibited' => 'Capture timestamp is server-controlled.',
            'captured_by_user_id.prohibited' => 'Capture actor is server-controlled.',
        ];
    }
}
