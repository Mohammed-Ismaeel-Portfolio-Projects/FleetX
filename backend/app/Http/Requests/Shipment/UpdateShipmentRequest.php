<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FoundationShipmentRequest
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
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customer_company_id' => 'sometimes|nullable|uuid|exists:companies,id',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'status_id' => 'sometimes|exists:statuses,id',
            'shipment_number' => 'sometimes|string|max:50|unique:shipments,shipment_number,' . $this->route('shipment'),
            'collaboration_type' => 'sometimes|string|in:internal,outbound_partner,inbound_partner',
            'executing_company_id' => 'sometimes|nullable|uuid|exists:companies,id',
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if ($this->has('collaboration_type') && $this->input('collaboration_type') === 'outbound_partner') {
                if (! $user || ! $user->isFleetXInternal()) {
                    $validator->errors()->add('collaboration_type', 'Only FleetX internal dispatchers can set outbound partner collaboration.');
                }
            }

            if ($this->has('executing_company_id') && $this->input('executing_company_id') !== null) {
                if (! $user || ! $user->isFleetXInternal()) {
                    $validator->errors()->add('executing_company_id', 'Only FleetX internal dispatchers can set executing partner company.');
                }
            }
        });
    }
}
