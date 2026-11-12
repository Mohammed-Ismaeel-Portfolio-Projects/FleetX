<?php

namespace App\Http\Resources;

use App\Models\TrackingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrackingSession
 */
class TrackingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $driverName = null;
        if ($this->driver) {
            $driverName = trim(($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? '')) ?: null;
        }

        $trailer = $this->assignment?->trailer;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'shipment_order_id' => $this->shipment_order_id,
            'shipment_id' => $this->shipment_id,
            'assignment_id' => $this->assignment_id ? (string) $this->assignment_id : null,

            'driver' => $this->driver ? [
                'id' => (string) $this->driver->id,
                'name' => $driverName,
                'first_name' => $this->driver->first_name,
                'last_name' => $this->driver->last_name,
                'employee_number' => $this->driver->employee_number,
            ] : null,

            'vehicle' => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'operational_code' => $this->vehicle->operational_code,
            ] : null,

            'trailer' => $trailer ? [
                'id' => $trailer->id,
                'code' => $trailer->trailer_code ?? $trailer->fleet_number ?? $trailer->plate_number,
                'plate_number' => $trailer->plate_number,
            ] : null,

            'simulation' => [
                'forced_speed_kmh' => $this->forced_speed_kmh !== null ? (float) $this->forced_speed_kmh : null,
                'forced_deceleration' => (bool) $this->forced_deceleration,
                'forced_route_deviation' => (bool) $this->forced_route_deviation,
                'speed_multiplier' => (float) $this->speed_multiplier,
            ],

            'speed_override' => $this->forced_speed_kmh !== null ? (float) $this->forced_speed_kmh : null,
            'forced_deceleration' => (bool) $this->forced_deceleration,
            'forced_route_deviation' => (bool) $this->forced_route_deviation,

            'timestamps' => [
                'started_at' => $this->started_at?->toIso8601String(),
                'paused_at' => $this->paused_at?->toIso8601String(),
                'ended_at' => $this->ended_at?->toIso8601String(),
            ],

            'started_at' => $this->started_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
        ];
    }
}
