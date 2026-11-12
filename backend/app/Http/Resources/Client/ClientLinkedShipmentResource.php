<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe linked shipment summary for Request → Shipment handoff (C2.9-C3.5).
 * Allowlist only — no operational, internal, or Engine fields.
 */
class ClientLinkedShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>|null
     */
    public static function summary(mixed $shipment): ?array
    {
        if ($shipment === null) {
            return null;
        }

        return (new self($shipment))->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
        ];
    }
}
