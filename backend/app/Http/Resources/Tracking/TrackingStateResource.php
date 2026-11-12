<?php

namespace App\Http\Resources\Tracking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingStateResource extends JsonResource
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
            'assignment_id' => $this->assignment_id,
            'shipment_id' => $this->shipment_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'trailer_id' => $this->trailer_id,

            // Location
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            // Telemetry
            'speed' => (float) $this->speed,
            'heading' => $this->heading ? (float) $this->heading : null,
            'accuracy' => $this->accuracy ? (float) $this->accuracy : null,

            // State
            'movement_status' => $this->movement_status,
            'online_status' => $this->online_status,
            'signal_quality' => $this->signal_quality,

            // Timestamps
            'last_update_at' => $this->last_update_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships (when loaded)
            'assignment' => $this->whenLoaded('assignment'),
            'shipment' => $this->whenLoaded('shipment'),
            'vehicle' => $this->whenLoaded('vehicle'),
            'driver' => $this->whenLoaded('driver'),
            'trailer' => $this->whenLoaded('trailer'),
        ];
    }
}
