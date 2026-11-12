<?php

namespace App\Http\Resources\ShipmentOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Append-only status history for ops Shipment Request detail (C2.9-B2.1).
 */
class ShipmentOrderStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->whenLoaded('fromStatus', fn () => [
                'id' => $this->fromStatus->id,
                'name' => $this->fromStatus->name,
                'type' => $this->fromStatus->type,
            ]),
            'to_status' => $this->whenLoaded('toStatus', fn () => [
                'id' => $this->toStatus->id,
                'name' => $this->toStatus->name,
                'type' => $this->toStatus->type,
            ]),
            'changed_by' => $this->whenLoaded('changedBy', fn () => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
                'email' => $this->changedBy->email,
            ]),
            'reason' => $this->reason,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
