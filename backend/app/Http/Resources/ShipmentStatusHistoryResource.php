<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Append-only Shipment status history for ops show payload (C2.9-C1.8 / C2.9-C2.7).
 */
class ShipmentStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->whenLoaded('fromStatus', fn () => $this->fromStatus
                ? [
                    'id' => $this->fromStatus->id,
                    'name' => $this->fromStatus->name,
                    'type' => $this->fromStatus->type,
                ]
                : null),
            'to_status' => $this->whenLoaded('toStatus', fn () => $this->toStatus
                ? [
                    'id' => $this->toStatus->id,
                    'name' => $this->toStatus->name,
                    'type' => $this->toStatus->type,
                ]
                : null),
            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy
                ? [
                    'id' => $this->changedBy->id,
                    'name' => $this->changedBy->name,
                    'email' => $this->changedBy->email,
                ]
                : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
