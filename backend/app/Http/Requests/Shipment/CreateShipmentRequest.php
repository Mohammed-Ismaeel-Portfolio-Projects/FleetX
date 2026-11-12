<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FoundationShipmentRequest
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
            'customer_company_id' => 'required|uuid|exists:companies,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'status_id' => 'required|exists:statuses,id',
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
                    $validator->errors()->add('collaboration_type', 'Only FleetX internal dispatchers can create outbound partner shipments.');
                }
            }

            if ($this->has('executing_company_id') && $this->input('executing_company_id') !== null) {
                if (! $user || ! $user->isFleetXInternal()) {
                    $validator->errors()->add('executing_company_id', 'Only FleetX internal dispatchers can assign executing partner companies.');
                }
            }
        });
    }
}
