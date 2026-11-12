<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\TrackingState;
use App\Models\User;
use App\Support\Client\ClientShipmentEndpointCoordinates;
use App\Support\Client\ClientShipmentLocationFreshness;
use App\Support\Client\ClientShipmentPlannedRoute;
use App\Support\DriverAssignmentStatusKey;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;

/**
 * Driver-safe trip map projection (D6). Own assignment only.
 */
class GetDriverTripMapAction
{
    public function __construct(
        private readonly ClientShipmentPlannedRoute $plannedRoute,
        private readonly ClientShipmentEndpointCoordinates $endpoints,
    ) {}

    public function execute(User $user, int|string $assignmentId): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        /** @var Assignment|null $assignment */
        $assignment = Assignment::query()
            ->forDriver($driver->id)
            ->with(['status', 'shipment.status', 'vehicle', 'trailer'])
            ->find($assignmentId);

        if ($assignment === null) {
            return ApiResult::notFound('Assignment not found');
        }

        $shipment = $assignment->shipment;
        $statusName = $assignment->status?->name;
        $phase = $assignment->execution_phase;

        $state = TrackingState::query()
            ->where('assignment_id', $assignment->id)
            ->first();

        $position = null;
        if ($state !== null && $state->latitude !== null && $state->longitude !== null) {
            $position = [
                'latitude' => (float) $state->latitude,
                'longitude' => (float) $state->longitude,
                'accuracy' => $state->accuracy !== null ? (float) $state->accuracy : null,
                'speed' => $state->speed !== null ? (float) $state->speed : null,
                'heading' => $state->heading !== null ? (float) $state->heading : null,
                'recorded_at' => $state->last_update_at?->format('Y-m-d H:i:s'),
                'received_at' => $state->last_update_at?->format('Y-m-d H:i:s'),
                'source' => 'server_last_known',
                'movement_status' => $state->movement_status,
                'signal_quality' => $state->signal_quality,
            ];
        }

        $freshness = ClientShipmentLocationFreshness::fromLastKnownPosition(
            $position !== null
                ? [
                    'latitude' => $position['latitude'],
                    'longitude' => $position['longitude'],
                    'recorded_at' => $position['recorded_at'],
                ]
                : null
        );

        $route = $shipment
            ? $this->plannedRoute->resolveForShipment((string) $shipment->id)
            : null;

        $hubs = $shipment
            ? $this->endpoints->resolveForShipment(
                (string) $shipment->id,
                $shipment->pickup_city,
                $shipment->delivery_city
            )
            : ['origin' => null, 'destination' => null];

        $trackingStatus = 'unavailable';
        if ($assignment->is_active && $statusName === DriverAssignmentTransitionMap::IN_OPERATION) {
            $trackingStatus = match ($freshness['state']) {
                'fresh' => 'active',
                'stale', 'very_stale' => 'limited',
                default => 'unavailable',
            };
        }

        return ApiResult::success([
            'assignment' => [
                'id' => $assignment->id,
                'assignment_code' => $assignment->assignment_code,
                'is_active' => (bool) $assignment->is_active,
                'status' => $assignment->status ? [
                    'name' => $assignment->status->name,
                    'key' => DriverAssignmentStatusKey::fromStatusName($statusName),
                ] : null,
            ],
            'execution' => [
                'phase' => $phase,
                'stage' => DriverExecutionPhase::stage($phase),
                'verification_boundary' => DriverExecutionPhase::isVerificationBoundary($phase),
            ],
            'shipment' => $shipment ? [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status ? [
                    'id' => $shipment->status->id,
                    'name' => $shipment->status->name,
                ] : null,
                'pickup' => [
                    'company' => $shipment->pickup_company,
                    'address' => $shipment->pickup_address,
                    'city' => $shipment->pickup_city,
                    'country' => $shipment->pickup_country,
                    'coordinates' => $hubs['origin'],
                ],
                'delivery' => [
                    'company' => $shipment->delivery_company,
                    'address' => $shipment->delivery_address,
                    'city' => $shipment->delivery_city,
                    'country' => $shipment->delivery_country,
                    'coordinates' => $hubs['destination'],
                ],
            ] : null,
            'route' => $route,
            'route_available' => $route !== null,
            'current_position' => $position,
            'freshness' => $freshness,
            'tracking_status' => $trackingStatus,
        ], 'Trip map loaded');
    }
}
