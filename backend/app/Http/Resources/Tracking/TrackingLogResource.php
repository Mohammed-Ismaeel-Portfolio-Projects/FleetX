<?php

namespace App\Http\Resources\Tracking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'shipment' => $this->whenLoaded('shipment', fn () => [
                'id' => $this->shipment->id,
                'shipment_number' => $this->shipment->shipment_number,
            ]),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'speed' => $this->speed !== null ? (float) $this->speed : null,
            'heading' => $this->heading !== null ? (float) $this->heading : null,
            'accuracy' => $this->accuracy !== null ? (float) $this->accuracy : null,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
