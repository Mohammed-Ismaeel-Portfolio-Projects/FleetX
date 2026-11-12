<?php

namespace App\Http\Requests\Client;

use App\Enums\CargoType;
use App\Enums\TrailerType;
use App\Support\ClientShipmentRequestCoordinateRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for PATCH /api/v1/client/shipment-requests/{shipmentOrder} (C2.9-A3.4.3a).
 * Partial update — only supplied writable fields are validated and applied.
 */
class UpdateClientShipmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->prohibitedFieldRules(), $this->writableFieldRules());
    }

    /**
     * @return array<string, list<string>>
     */
    private function prohibitedFieldRules(): array
    {
        return [
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
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'driver' => ['prohibited'],
            'driver_id' => ['prohibited'],
            'vehicle' => ['prohibited'],
            'vehicle_id' => ['prohibited'],
            'assignment' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'tracking' => ['prohibited'],
            'gps' => ['prohibited'],
            'eta' => ['prohibited'],
            'pricing' => ['prohibited'],
            'shipment_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function writableFieldRules(): array
    {
        return array_merge([
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Nullable for incomplete drafts; when present must be CargoType enum (SHIPMENT-REQUEST-02).
            'cargo_type' => ['sometimes', 'nullable', Rule::enum(CargoType::class)],
            'cargo_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cargo_weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'cargo_volume' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'package_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999'],
            'hazardous_cargo' => ['sometimes', 'boolean'],
            'temperature_controlled' => ['sometimes', 'boolean'],
            'trailer_type' => ['sometimes', 'nullable', Rule::enum(TrailerType::class)],
            'selected_service_ids' => ['sometimes', 'array'],
            'selected_service_ids.*' => ['integer', 'min:1'],
            'border_crossing' => ['sometimes', 'boolean'],
            'insurance_required' => ['sometimes', 'boolean'],
            'pickup_company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'pickup_contact' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pickup_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pickup_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'pickup_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pickup_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'delivery_contact' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'delivery_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'delivery_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'delivery_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'requested_pickup_date' => ['sometimes', 'nullable', 'date'],
            'requested_delivery_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:requested_pickup_date'],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'client_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ], ClientShipmentRequestCoordinateRules::updateRules());
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
}
