<?php

namespace App\Http\Resources\Shipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order' => [
                'id' => $this->order->id,
                'client' => [
                    'id' => $this->order->client->id,
                    'name' => $this->order->client->name,
                    'email' => $this->order->client->email,
                ],
                'pickup_location' => [
                    'id' => $this->order->pickup_location_id,
                    'name' => $this->order->pickupLocation->name,
                    'latitude' => (float) $this->order->pickupLocation->latitude,
                    'longitude' => (float) $this->order->pickupLocation->longitude,
                ],
                'delivery_location' => [
                    'id' => $this->order->delivery_location_id,
                    'name' => $this->order->deliveryLocation->name,
                    'latitude' => (float) $this->order->deliveryLocation->latitude,
                    'longitude' => (float) $this->order->deliveryLocation->longitude,
                ],
                'estimated_cost' => (float) $this->order->estimated_cost,
                'order_items' => $this->order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'weight' => (float) $item->weight,
                        'length' => (float) $item->length,
                        'width' => (float) $item->width,
                        'height' => (float) $item->height,
                        'type' => $item->type,
                        'quantity' => $item->quantity,
                        'price' => (float) $item->price,
                    ];
                }),
            ],
            'status' => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ],
            'total_weight' => (float) $this->total_weight,
            'current_location' => [
                'latitude' => $this->current_lat ? (float) $this->current_lat : null,
                'longitude' => $this->current_lng ? (float) $this->current_lng : null,
            ],
            'timestamps' => [
                'start_time' => $this->start_time?->toIso8601String(),
                'pickup_time' => $this->pickup_time?->toIso8601String(),
                'delivery_time' => $this->delivery_time?->toIso8601String(),
                'end_time' => $this->end_time?->toIso8601String(),
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ],
            'active_assignment' => $this->assignments->first(function ($assignment) {
                return $assignment->ended_at === null;
            }) ? [
                'id' => $this->assignments->first(function ($assignment) {
                    return $assignment->ended_at === null;
                })->id,
                'driver' => [
                    'id' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->driver->id,
                    'name' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->driver->user->name,
                    'phone' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->driver->phone,
                    'license_number' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->driver->license_number,
                ],
                'truck' => [
                    'id' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->truck->id,
                    'plate_number' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->truck->plate_number,
                    'type' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->truck->type,
                    'capacity' => (float) $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->truck->capacity,
                    'fuel_type' => $this->assignments->first(function ($assignment) {
                        return $assignment->ended_at === null;
                    })->truck->fuel_type,
                ],
                'assigned_at' => $this->assignments->first(function ($assignment) {
                    return $assignment->ended_at === null;
                })->assigned_at->toIso8601String(),
                'status' => $this->assignments->first(function ($assignment) {
                    return $assignment->ended_at === null;
                })->status->name,
            ] : null,
        ];
    }
}
