<?php

namespace App\Http\Requests\Client;

use App\Enums\CargoType;
use App\Enums\TrailerType;
use App\Support\ClientShipmentRequestCoordinateRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for POST /api/v1/client/shipment-requests (C2.9-A3.4.2 draft create).
 */
class CreateClientShipmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'id' => ['prohibited'],
            'request_number' => ['prohibited'],
            'customer_company_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'status_id' => ['prohibited'],
            'status' => ['prohibited'],
            'submission_revision' => ['prohibited'],
            'payload_json' => ['prohibited'],
            'converted_shipment_id' => ['prohibited'],
            'priority' => ['prohibited'],
            'dispatcher_notes' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],

            'reference_number' => ['nullable', 'string', 'max:100'],
            // Nullable for incomplete drafts; when present must be CargoType enum (SHIPMENT-REQUEST-02).
            'cargo_type' => ['nullable', Rule::enum(CargoType::class)],
            'cargo_description' => ['nullable', 'string', 'max:5000'],
            'cargo_weight' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'cargo_volume' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'package_count' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'hazardous_cargo' => ['sometimes', 'boolean'],
            'temperature_controlled' => ['sometimes', 'boolean'],
            'trailer_type' => ['nullable', Rule::enum(TrailerType::class)],
            'selected_service_ids' => ['sometimes', 'array'],
            'selected_service_ids.*' => ['integer', 'min:1'],
            'border_crossing' => ['sometimes', 'boolean'],
            'insurance_required' => ['sometimes', 'boolean'],
            'pickup_company' => ['nullable', 'string', 'max:200'],
            'pickup_contact' => ['nullable', 'string', 'max:100'],
            'pickup_phone' => ['nullable', 'string', 'max:50'],
            'pickup_address' => ['nullable', 'string', 'max:2000'],
            'pickup_city' => ['nullable', 'string', 'max:100'],
            'pickup_country' => ['nullable', 'string', 'max:100'],
            'delivery_company' => ['nullable', 'string', 'max:200'],
            'delivery_contact' => ['nullable', 'string', 'max:100'],
            'delivery_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'delivery_city' => ['nullable', 'string', 'max:100'],
            'delivery_country' => ['nullable', 'string', 'max:100'],
            'requested_pickup_date' => ['nullable', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:requested_pickup_date'],
            'special_instructions' => ['nullable', 'string', 'max:5000'],
            'client_notes' => ['nullable', 'string', 'max:5000'],
        ], ClientShipmentRequestCoordinateRules::createRules());
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('hazardous_cargo')) {
            $this->merge(['hazardous_cargo' => $this->boolean('hazardous_cargo')]);
        }

        if ($this->has('temperature_controlled')) {
            $this->merge(['temperature_controlled' => $this->boolean('temperature_controlled')]);
        }

        foreach (['border_crossing', 'insurance_required'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'customer_company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'submitted_by_user_id.prohibited' => 'Ownership fields cannot be supplied by the client.',
            'status_id.prohibited' => 'Lifecycle status cannot be supplied by the client.',
            'status.prohibited' => 'Lifecycle status cannot be supplied by the client.',
            'submission_revision.prohibited' => 'Submission revision cannot be supplied by the client.',
            'payload_json.prohibited' => 'Payload snapshot cannot be supplied by the client.',
            'request_number.prohibited' => 'Request number is server-generated.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedFieldNames(): array
    {
        return [
            'reference_number',
            'cargo_type',
            'cargo_description',
            'cargo_weight',
            'cargo_volume',
            'package_count',
            'hazardous_cargo',
            'temperature_controlled',
            'trailer_type',
            'selected_service_ids',
            'border_crossing',
            'insurance_required',
            'pickup_company',
            'pickup_contact',
            'pickup_phone',
            'pickup_address',
            'pickup_city',
            'pickup_country',
            'pickup_latitude',
            'pickup_longitude',
            'delivery_company',
            'delivery_contact',
            'delivery_phone',
            'delivery_address',
            'delivery_city',
            'delivery_country',
            'delivery_latitude',
            'delivery_longitude',
            'requested_pickup_date',
            'requested_delivery_date',
            'special_instructions',
            'client_notes',
        ];
    }
}
