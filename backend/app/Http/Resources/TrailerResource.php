<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrailerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trailer_code' => $this->trailer_code,
            'fleet_number' => $this->fleet_number,
            'plate_number' => $this->plate_number,
            'trailer_type' => $this->trailer_type,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'production_year' => $this->production_year,
            'axle_count' => $this->axle_count,
            'payload_capacity_kg' => $this->payload_capacity_kg,
            'length_m' => $this->length_m,
            'width_m' => $this->width_m,
            'height_m' => $this->height_m,
            'cargo_volume_m3' => $this->cargo_volume_m3,
            'current_odometer' => $this->current_odometer,
            'gps_device_identifier' => $this->gps_device_identifier,
            'insurance_expiry_date' => $this->insurance_expiry_date?->toDateString(),
            'inspection_expiry_date' => $this->inspection_expiry_date?->toDateString(),
            'registration_expiry_date' => $this->registration_expiry_date?->toDateString(),
            'status_id' => $this->status_id,
            'is_active' => $this->is_active,
            'has_active_assignment' => $this->assignments()->where('is_active', true)->whereNull('deleted_at')->exists(),
            'is_matching_requested_type' => $request->filled('requested_trailer_type')
                ? strtolower((string) $this->trailer_type) === strtolower((string) $request->input('requested_trailer_type'))
                : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
