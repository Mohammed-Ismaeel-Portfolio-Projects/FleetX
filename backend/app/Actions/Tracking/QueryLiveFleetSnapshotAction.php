<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\TrackingState;
use App\Simulation\Progress\RouteProgressEngine;
use Illuminate\Support\Facades\Cache;

use App\Models\TrackingSession;
use App\Services\Tracking\ShipmentTrackingEngine;

/**
 * QueryLiveFleetSnapshotAction
 *
 * Returns the Dispatcher live fleet snapshot: exactly one row per active vehicle
 * (active assignment), excluding inactive/historical TrackingStates.
 *
 * Distinct from QueryTrackingStatesAction (admin/history catalogue).
 */
class QueryLiveFleetSnapshotAction
{
    public function execute(): ApiResult
    {
        try {
            $assignments = Assignment::query()
                ->active()
                ->with([
                    'shipment.status',
                    'vehicle',
                    'driver',
                    'trailer',
                ])
                ->whereNotNull('vehicle_id')
                ->whereNotNull('shipment_id')
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->get();

            // Exactly one active assignment per vehicle (latest wins).
            $uniqueByVehicle = $assignments
                ->unique('vehicle_id')
                ->values();

            $assignmentIds = $uniqueByVehicle->pluck('id')->all();
            $trackingStates = TrackingState::query()
                ->whereIn('assignment_id', $assignmentIds)
                ->get()
                ->keyBy('assignment_id');

            $vehicles = $uniqueByVehicle->map(function (Assignment $assignment) use ($trackingStates) {
                $state = $trackingStates->get($assignment->id);
                $progress = $this->loadRouteProgress((string) $assignment->shipment_id);

                return $this->mapVehicle($assignment, $state, $progress);
            })->values()->all();

            return ApiResult::success([
                'vehicles' => $vehicles,
                'meta' => [
                    'count' => count($vehicles),
                    'generated_at' => now()->toIso8601String(),
                ],
            ], 'Live fleet snapshot retrieved successfully');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to query live fleet snapshot: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>|null  $progress
     * @return array<string, mixed>
     */
    private function mapVehicle(Assignment $assignment, ?TrackingState $state, ?array $progress): array
    {
        $shipment = $assignment->shipment;
        $vehicle = $assignment->vehicle;
        $driver = $assignment->driver;

        return [
            'vehicle_id' => (string) $assignment->vehicle_id,
            'assignment_id' => (string) $assignment->id,
            'shipment_id' => (string) $assignment->shipment_id,
            'driver_id' => $assignment->driver_id !== null ? (string) $assignment->driver_id : null,
            'trailer_id' => $assignment->trailer_id !== null ? (string) $assignment->trailer_id : null,

            'vehicle' => [
                'id' => $vehicle?->id,
                'operational_code' => $vehicle?->operational_code,
                'plate_number' => $vehicle?->plate_number,
                'fleet_number' => $vehicle?->fleet_number ?? $vehicle?->operational_code,
            ],
            'driver' => [
                'id' => $driver?->id,
                'employee_number' => $driver?->employee_number,
                'first_name' => $driver?->first_name,
                'last_name' => $driver?->last_name,
                'full_name' => $driver
                    ? trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''))
                    : null,
            ],
            'shipment' => [
                'id' => $shipment?->id,
                'shipment_order_id' => $shipment?->shipment_order_id,
                'shipment_number' => $shipment?->shipment_number,
                'pickup_city' => $shipment?->pickup_city,
                'delivery_city' => $shipment?->delivery_city,
                'status' => $shipment?->status?->name,
            ],
            'assignment' => [
                'id' => $assignment->id,
                'assignment_code' => $assignment->assignment_code,
                'is_active' => (bool) $assignment->is_active,
            ],

            'tracking_state' => $state ? [
                'id' => $state->id,
                'latitude' => (float) $state->latitude,
                'longitude' => (float) $state->longitude,
                'speed' => (float) ($state->speed ?? 0),
                'heading' => $state->heading !== null ? (float) $state->heading : null,
                'accuracy' => $state->accuracy !== null ? (float) $state->accuracy : null,
                'movement_status' => $state->movement_status,
                'online_status' => $state->online_status,
                'signal_quality' => $state->signal_quality,
                'last_update_at' => $state->last_update_at?->toIso8601String(),
            ] : null,

            'telemetry' => [
                'latitude' => $state ? (float) $state->latitude : ($progress['current_coordinate']['latitude'] ?? null),
                'longitude' => $state ? (float) $state->longitude : ($progress['current_coordinate']['longitude'] ?? null),
                'speed' => $state ? (float) ($state->speed ?? 0) : 0.0,
                'heading' => $state && $state->heading !== null
                    ? (float) $state->heading
                    : (isset($progress['heading']) ? (float) $progress['heading'] : null),
                'movement_status' => $state?->movement_status ?? 'Idle',
                'online_status' => $state?->online_status ?? 'Offline',
                'signal_quality' => $state?->signal_quality ?? 'Lost',
            ],

            'route_progress' => $progress ? [
                'route_id' => $progress['route_id'] ?? null,
                'completion_percentage' => isset($progress['completion_percentage'])
                    ? (float) $progress['completion_percentage']
                    : null,
                'distance_travelled_m' => isset($progress['distance_travelled_m'])
                    ? (float) $progress['distance_travelled_m']
                    : null,
                'remaining_distance_m' => isset($progress['remaining_distance_m'])
                    ? (float) $progress['remaining_distance_m']
                    : null,
                'segment_index' => isset($progress['segment_index']) ? (int) $progress['segment_index'] : null,
                'completed' => (bool) ($progress['completed'] ?? false),
                'provider' => $progress['provider'] ?? null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadRouteProgress(string $shipmentId): ?array
    {
        $payload = Cache::get(RouteProgressEngine::PROGRESS_CACHE_PREFIX . $shipmentId);

        return is_array($payload) ? $payload : null;
    }
}
