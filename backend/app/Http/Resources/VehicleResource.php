<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operational_code' => $this->operational_code,
            'fleet_number' => $this->fleet_number,
            'plate_number' => $this->plate_number,
            'vin' => $this->vin,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'production_year' => $this->production_year,
            'vehicle_type_id' => $this->vehicle_type_id,
            'driver_id' => $this->driver_id,
            'fuel_type_id' => $this->fuel_type_id,
            'transmission' => $this->transmission,
            'axle_count' => $this->axle_count,
            'payload_capacity_kg' => $this->payload_capacity_kg,
            'gross_weight_kg' => $this->gross_weight_kg,
            'fuel_tank_capacity_l' => $this->fuel_tank_capacity_l,
            'current_odometer' => $this->current_odometer,
            'status_id' => $this->status_id,
            'is_active' => $this->is_active,
            'gps_device_identifier' => $this->gps_device_identifier,
            'insurance_expiry_date' => $this->insurance_expiry_date?->toDateString(),
            'registration_expiry_date' => $this->registration_expiry_date?->toDateString(),
            'inspection_expiry_date' => $this->inspection_expiry_date?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),

            'driver' => $this->whenLoaded('driver', function () {
                if (!$this->driver) {
                    return null;
                }
                return [
                    'id' => $this->driver->id,
                    'first_name' => $this->driver->first_name,
                    'last_name' => $this->driver->last_name,
                    'full_name' => $this->driver->full_name,
                    'employee_number' => $this->driver->employee_number,
                    'phone' => $this->driver->phone,
                    'license_number' => $this->driver->license_number,
                    'user_id' => $this->driver->user_id,
                    'status' => $this->driver->status?->name,
                ];
            }),

            'vehicle_type' => $this->whenLoaded('vehicleType', fn () => [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
            ]),

            'fuel_type' => $this->whenLoaded('fuelType', fn () => [
                'id' => $this->fuelType->id,
                'name' => $this->fuelType->name,
            ]),

            'active_assignment' => $this->whenLoaded('activeAssignment', function () {
                if (!$this->activeAssignment) {
                    return null;
                }

                $assignment = $this->activeAssignment;

                return [
                    'id' => $assignment->id,
                    'assignment_code' => $assignment->assignment_code,
                    'is_active' => $assignment->is_active,
                    'status_id' => $assignment->status_id,
                    'driver' => $assignment->relationLoaded('driver') && $assignment->driver
                        ? [
                            'id' => $assignment->driver->id,
                            'first_name' => $assignment->driver->first_name,
                            'last_name' => $assignment->driver->last_name,
                            'full_name' => $assignment->driver->full_name,
                            'phone' => $assignment->driver->phone,
                        ]
                        : null,
                    'shipment' => $assignment->relationLoaded('shipment') && $assignment->shipment
                        ? [
                            'id' => $assignment->shipment->id,
                            'shipment_number' => $assignment->shipment->shipment_number,
                            'reference_number' => $assignment->shipment->reference_number,
                            'priority' => $assignment->shipment->priority,
                        ]
                        : null,
                    'trailer' => $assignment->relationLoaded('trailer') && $assignment->trailer
                        ? [
                            'id' => $assignment->trailer->id,
                            'trailer_code' => $assignment->trailer->trailer_code,
                            'fleet_number' => $assignment->trailer->fleet_number,
                            'plate_number' => $assignment->trailer->plate_number,
                            'trailer_type' => $assignment->trailer->trailer_type,
                        ]
                        : null,
                ];
            }),

            'tracking_state' => $this->whenLoaded('trackingState', function () {
                if (!$this->trackingState) {
                    return null;
                }

                $state = $this->trackingState;

                return [
                    'id' => $state->id,
                    'latitude' => (float) $state->latitude,
                    'longitude' => (float) $state->longitude,
                    'speed' => (float) $state->speed,
                    'heading' => $state->heading !== null ? (float) $state->heading : null,
                    'movement_status' => $state->movement_status,
                    'online_status' => $state->online_status,
                    'signal_quality' => $state->signal_quality,
                    'last_update_at' => $state->last_update_at?->toIso8601String(),
                ];
            }),
        ];
    }
}
