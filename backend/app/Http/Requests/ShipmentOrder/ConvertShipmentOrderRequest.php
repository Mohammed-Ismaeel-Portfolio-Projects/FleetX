<?php

namespace App\Http\Requests\ShipmentOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/shipment-requests/{shipmentOrder}/convert (C2.9-B3.4).
 *
 * Empty body by design — conversion mapping is server-owned.
 */
class ConvertShipmentOrderRequest extends FormRequest
{
    use ProhibitsOpsShipmentOrderLifecycleInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->lifecycleProhibitedRules(), $this->conversionProhibitedRules());
    }

    /**
     * @return array<string, list<string>>
     */
    private function conversionProhibitedRules(): array
    {
        return [
            'reason' => ['prohibited'],
            'shipment_order_id' => ['prohibited'],
            'shipment_number' => ['prohibited'],
            'reference_number' => ['prohibited'],
            'cargo_type' => ['prohibited'],
            'cargo_description' => ['prohibited'],
            'cargo_weight' => ['prohibited'],
            'cargo_volume' => ['prohibited'],
            'package_count' => ['prohibited'],
            'hazardous_cargo' => ['prohibited'],
            'temperature_controlled' => ['prohibited'],
            'pickup_company' => ['prohibited'],
            'pickup_contact' => ['prohibited'],
            'pickup_phone' => ['prohibited'],
            'pickup_address' => ['prohibited'],
            'pickup_city' => ['prohibited'],
            'pickup_country' => ['prohibited'],
            'delivery_company' => ['prohibited'],
            'delivery_contact' => ['prohibited'],
            'delivery_phone' => ['prohibited'],
            'delivery_address' => ['prohibited'],
            'delivery_city' => ['prohibited'],
            'delivery_country' => ['prohibited'],
            'requested_pickup_date' => ['prohibited'],
            'requested_delivery_date' => ['prohibited'],
            'planned_pickup_date' => ['prohibited'],
            'planned_delivery_date' => ['prohibited'],
            'actual_pickup_date' => ['prohibited'],
            'actual_delivery_date' => ['prohibited'],
            'special_instructions' => ['prohibited'],
            'client_notes' => ['prohibited'],
            'notes' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
