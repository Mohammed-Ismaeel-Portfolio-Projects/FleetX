<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dedicated client-facing QR verification payload (CLIENT-QR-02).
 */
class ClientShipmentVerificationQrResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{event: string, qr_payload: string, expires_at: ?string, status: string} $payload */
        $payload = $this->resource;

        return [
            'event' => $payload['event'],
            'qr_payload' => $payload['qr_payload'],
            'expires_at' => $payload['expires_at'],
            'status' => $payload['status'] ?? 'active',
        ];
    }
}
