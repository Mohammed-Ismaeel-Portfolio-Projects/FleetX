<?php

namespace App\Http\Requests\Client;

use App\Enums\CargoType;
use App\Enums\TrailerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Server-controlled fields — PROHIBITED
            'customer_type' => ['prohibited'],
            'customerType' => ['prohibited'],
            'distance' => ['prohibited'],
            'pricing_policy_id' => ['prohibited'],
            'pricingPolicyId' => ['prohibited'],
            'service_price' => ['prohibited'],
            'service_prices' => ['prohibited'],
            'tax_rate' => ['prohibited'],
            'discount' => ['prohibited'],
            'discount_amount' => ['prohibited'],
            'minimum_fare' => ['prohibited'],

            // Coordinates — required for distance calculation
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_latitude' => ['required', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['required', 'numeric', 'between:-180,180'],

            // Cargo
            'cargo_type' => ['required', Rule::enum(CargoType::class)],
            'trailer_type' => ['required', Rule::enum(TrailerType::class)],
            'cargo_weight' => ['required', 'numeric', 'min:0', 'max:999999.99'],

            // Operational
            'shipment_priority' => ['required', 'string', Rule::in(['standard', 'express', 'urgent', 'emergency'])],
            'border_crossing' => ['sometimes', 'boolean'],
            'hazmat' => ['sometimes', 'boolean'],
            'insurance_required' => ['sometimes', 'boolean'],

            // Waiting times (minutes)
            'loading_waiting_time' => ['sometimes', 'integer', 'min:0'],
            'unloading_waiting_time' => ['sometimes', 'integer', 'min:0'],
            'traffic_delay' => ['sometimes', 'integer', 'min:0'],
            'customer_delay' => ['sometimes', 'integer', 'min:0'],

            // Services — IDs only
            'selected_service_ids' => ['sometimes', 'array'],
            'selected_service_ids.*' => ['integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['border_crossing', 'hazmat', 'insurance_required'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'customer_type.prohibited' => 'Customer type is server-resolved and cannot be supplied by the client.',
            'customerType.prohibited' => 'Customer type is server-resolved and cannot be supplied by the client.',
            'distance.prohibited' => 'Distance is server-calculated and cannot be supplied by the client.',
            'pricing_policy_id.prohibited' => 'Pricing policy is server-resolved and cannot be supplied by the client.',
            'pricingPolicyId.prohibited' => 'Pricing policy is server-resolved and cannot be supplied by the client.',
            'service_price.prohibited' => 'Service prices are server-resolved and cannot be supplied by the client.',
            'service_prices.prohibited' => 'Service prices are server-resolved and cannot be supplied by the client.',
            'tax_rate.prohibited' => 'Tax rate is server-resolved and cannot be supplied by the client.',
            'discount.prohibited' => 'Discount is server-resolved and cannot be supplied by the client.',
            'discount_amount.prohibited' => 'Discount amount is server-resolved and cannot be supplied by the client.',
            'minimum_fare.prohibited' => 'Minimum fare is server-resolved and cannot be supplied by the client.',
        ];
    }
}
