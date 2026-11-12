<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for POST /api/v1/client/shipment-requests/{shipmentOrder}/submit (C2.9-A3.4.3b).
 * Empty body preferred — persisted draft is submitted.
 */
class SubmitClientShipmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
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
            'customer_type' => ['prohibited'],
            'distance' => ['prohibited'],
            'pricing_policy_id' => ['prohibited'],
            'service_price' => ['prohibited'],
            'tax_rate' => ['prohibited'],
            'discount' => ['prohibited'],
            'minimum_fare' => ['prohibited'],
            'priority_multiplier' => ['prohibited'],
        ];
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
        ];
    }
}
