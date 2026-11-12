<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver-safe self-service availability projection (D13).
 *
 * @property array{availability: string, can_change: bool, reason: string|null} $resource
 */
class DriverAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'availability' => $this->resource['availability'],
            'can_change' => (bool) $this->resource['can_change'],
            'reason' => $this->resource['reason'],
        ];
    }
}
