<?php

namespace App\Http\Resources\Driver;

use App\Support\DriverAssignmentStatusKey;
use App\Support\DriverExecutionPhase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver-safe work assignment projection (D2 + D4 execution).
 * Whitelist only — never expose Ops AssignmentResource fields wholesale.
 */
class DriverWorkAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusName = $this->status?->name;
        $phase = $this->execution_phase;

        return [
            'id' => $this->id,
            'assignment_code' => $this->assignment_code,
            'is_active' => (bool) $this->is_active,
            'status' => $this->status ? [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'key' => DriverAssignmentStatusKey::fromStatusName($statusName),
            ] : null,
            'execution' => [
                'phase' => $phase,
                'stage' => DriverExecutionPhase::stage($phase),
                'verification_boundary' => DriverExecutionPhase::isVerificationBoundary($phase),
            ],
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'ended_at' => $this->ended_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'shipment' => $this->whenLoaded('shipment', function () {
                $shipment = $this->shipment;
                if ($shipment === null) {
                    return null;
                }

                return [
                    'id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                    'reference_number' => $shipment->reference_number,
                    'status' => $shipment->status ? [
                        'id' => $shipment->status->id,
                        'name' => $shipment->status->name,
                    ] : null,
                    'pickup' => [
                        'company' => $shipment->pickup_company,
                        'address' => $shipment->pickup_address,
                        'city' => $shipment->pickup_city,
                        'country' => $shipment->pickup_country,
                    ],
                    'delivery' => [
                        'company' => $shipment->delivery_company,
                        'address' => $shipment->delivery_address,
                        'city' => $shipment->delivery_city,
                        'country' => $shipment->delivery_country,
                    ],
                    'planned_pickup_date' => $shipment->planned_pickup_date?->format('Y-m-d'),
                    'planned_delivery_date' => $shipment->planned_delivery_date?->format('Y-m-d'),
                ];
            }),
            'vehicle' => $this->whenLoaded('vehicle', function () {
                $vehicle = $this->vehicle;
                if ($vehicle === null) {
                    return null;
                }

                return [
                    'id' => $vehicle->id,
                    'fleet_number' => $vehicle->fleet_number,
                    'plate_number' => $vehicle->plate_number,
                    'manufacturer' => $vehicle->manufacturer,
                    'model' => $vehicle->model,
                ];
            }),
            'trailer' => $this->whenLoaded('trailer', function () {
                $trailer = $this->trailer;
                if ($trailer === null) {
                    return null;
                }

                return [
                    'id' => $trailer->id,
                    'trailer_code' => $trailer->trailer_code,
                    'fleet_number' => $trailer->fleet_number,
                    'plate_number' => $trailer->plate_number,
                ];
            }),
        ];
    }
}
