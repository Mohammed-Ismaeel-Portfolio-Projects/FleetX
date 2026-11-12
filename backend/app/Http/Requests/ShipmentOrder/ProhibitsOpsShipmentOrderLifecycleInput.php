<?php

namespace App\Http\Requests\ShipmentOrder;

trait ProhibitsOpsShipmentOrderLifecycleInput
{
    /**
     * @return array<string, list<string>>
     */
    protected function lifecycleProhibitedRules(): array
    {
        return [
            'id' => ['prohibited'],
            'request_number' => ['prohibited'],
            'customer_company_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'submitted_by_user_id' => ['prohibited'],
            'changed_by_user_id' => ['prohibited'],
            'status_id' => ['prohibited'],
            'status' => ['prohibited'],
            'target_status' => ['prohibited'],
            'submission_revision' => ['prohibited'],
            'payload_json' => ['prohibited'],
            'converted_shipment_id' => ['prohibited'],
            'priority' => ['prohibited'],
            'dispatcher_notes' => ['prohibited'],
            'driver_id' => ['prohibited'],
            'vehicle_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
            'tracking' => ['prohibited'],
            'gps' => ['prohibited'],
            'eta' => ['prohibited'],
            'pricing' => ['prohibited'],
            'shipment_id' => ['prohibited'],
        ];
    }
}
