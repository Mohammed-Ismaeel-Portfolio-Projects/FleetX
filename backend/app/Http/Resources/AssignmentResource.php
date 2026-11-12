<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'shipment' => $this->whenLoaded('shipment', fn () => [
                'id' => $this->shipment->id,
                'shipment_number' => $this->shipment->shipment_number,
                'reference_number' => $this->shipment->reference_number,
                'priority' => $this->shipment->priority,
            ]),
            'assignment_code' => $this->assignment_code,
            'driver_id' => $this->driver_id,
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'employee_number' => $this->driver->employee_number,
                'full_name' => $this->driver->full_name,
                'email' => $this->driver->email,
                'phone' => $this->driver->phone,
            ]),
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle->id,
                'fleet_number' => $this->vehicle->fleet_number,
                'plate_number' => $this->vehicle->plate_number,
                'manufacturer' => $this->vehicle->manufacturer,
                'model' => $this->vehicle->model,
            ]),
            'trailer_id' => $this->trailer_id,
            'trailer' => $this->whenLoaded('trailer', fn () => [
                'id' => $this->trailer->id,
                'trailer_code' => $this->trailer->trailer_code,
                'fleet_number' => $this->trailer->fleet_number,
                'plate_number' => $this->trailer->plate_number,
            ]),
            'assigned_by' => $this->assigned_by,
            'assigned_by_user' => $this->whenLoaded('assignedBy', fn () => [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
                'email' => $this->assignedBy->email,
            ]),
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
            ]),
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'ended_at' => $this->ended_at?->format('Y-m-d H:i:s'),
            'is_active' => $this->is_active,
            'engine_enrollment_status' => $this->engine_enrollment_status,
            'engine_enrolled_at' => $this->engine_enrolled_at?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
