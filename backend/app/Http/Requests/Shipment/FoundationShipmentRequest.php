<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class FoundationShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_company_id' => 'nullable|uuid|exists:companies,id',
            'executing_company_id' => 'nullable|uuid|exists:companies,id',
            'collaboration_type' => 'nullable|string|in:internal,outbound_partner,inbound_partner',
            'reference_number' => 'nullable|string|max:100',
            'priority' => 'required|in:low,normal,high,urgent',
            'cargo_type' => 'nullable|string|max:100',
            'cargo_description' => 'nullable|string',
            'cargo_weight' => 'nullable|numeric|min:0|max:999999.99',
            'cargo_volume' => 'nullable|numeric|min:0|max:999999.99',
            'package_count' => 'nullable|integer|min:0|max:999999',
            'hazardous_cargo' => 'boolean',
            'temperature_controlled' => 'boolean',
            'pickup_company' => 'nullable|string|max:200',
            'pickup_contact' => 'nullable|string|max:100',
            'pickup_phone' => 'nullable|string|max:50',
            'pickup_address' => 'nullable|string',
            'pickup_city' => 'nullable|string|max:100',
            'pickup_country' => 'nullable|string|max:100',
            'delivery_company' => 'nullable|string|max:200',
            'delivery_contact' => 'nullable|string|max:100',
            'delivery_phone' => 'nullable|string|max:50',
            'delivery_address' => 'nullable|string',
            'delivery_city' => 'nullable|string|max:100',
            'delivery_country' => 'nullable|string|max:100',
            'planned_pickup_date' => 'nullable|date',
            'planned_delivery_date' => 'nullable|date|after:planned_pickup_date',
            'actual_pickup_date' => 'nullable|date',
            'actual_delivery_date' => 'nullable|date|after:actual_pickup_date',
            'special_instructions' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hazardous_cargo' => $this->boolean('hazardous_cargo'),
            'temperature_controlled' => $this->boolean('temperature_controlled'),
        ]);
    }
}
