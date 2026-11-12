<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver-safe device projection (D12). Never includes the raw push token.
 */
class DriverDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'is_active' => (bool) $this->is_active,
            'last_seen_at' => $this->last_seen_at?->format('Y-m-d H:i:s'),
            'invalidated_at' => $this->invalidated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
