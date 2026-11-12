<?php

namespace App\Http\Resources\Driver;

use App\Support\DriverAssignmentStatusKey;
use App\Support\DriverExecutionPhase;
use App\Support\DriverHistoryOutcome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver-safe historical list item (D11). Whitelist only.
 */
class DriverAssignmentHistoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusName = $this->status?->name;
        $phase = $this->execution_phase;
        $shipment = $this->shipment;
        $outcome = DriverHistoryOutcome::fromAssignment($statusName, $phase);

        return [
            'id' => $this->id,
            'assignment_code' => $this->assignment_code,
            'status' => $this->status ? [
                'name' => $this->status->name,
                'key' => DriverAssignmentStatusKey::fromStatusName($statusName),
            ] : null,
            'outcome' => $outcome,
            'execution_phase' => $phase,
            'execution_stage' => DriverExecutionPhase::stage($phase),
            'shipment_number' => $shipment?->shipment_number,
            'pickup' => [
                'company' => $shipment?->pickup_company,
                'city' => $shipment?->pickup_city,
                'country' => $shipment?->pickup_country,
            ],
            'delivery' => [
                'company' => $shipment?->delivery_company,
                'city' => $shipment?->delivery_city,
                'country' => $shipment?->delivery_country,
            ],
            'planned_pickup_date' => $shipment?->planned_pickup_date?->format('Y-m-d'),
            'planned_delivery_date' => $shipment?->planned_delivery_date?->format('Y-m-d'),
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'ended_at' => $this->ended_at?->format('Y-m-d H:i:s'),
        ];
    }
}
