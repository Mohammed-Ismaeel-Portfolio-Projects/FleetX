<?php

namespace App\Services\Tracking;

use App\Actions\Tracking\CreateTrackingLogAction;
use App\Models\Assignment;
use App\Models\TrackingSession;
use App\Models\TrackingState;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Services\TrackingSimulator\VehicleMovementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShipmentTrackingEngine
{
    public function __construct(
        private CreateTrackingLogAction $createTrackingLogAction,
        private VehicleMovementService $movementService,
        private RouteProgressEngine $routeProgressEngine,
        private PlannedRouteCache $plannedRouteCache,
    ) {}

    public function tick(TrackingSession $session): ?TrackingState
    {
        if ($session->status !== TrackingSession::STATUS_ACTIVE) {
            return null;
        }

        $session->loadMissing(['shipmentOrder', 'shipment', 'assignment', 'vehicle', 'driver']);

        $shipmentOrder = $session->shipmentOrder;
        if (! $shipmentOrder) {
            return null;
        }

        $shipmentId = (string) ($session->shipment_id
            ?? $session->assignment?->shipment_id
            ?? $shipmentOrder->converted_shipment_id);

        if (! $shipmentId) {
            return null;
        }

        // 1. Ensure PlannedRouteCache has usable route geometry for this shipment
        if (! $this->plannedRouteCache->has($shipmentId)) {
            $rawPoints = $shipmentOrder->planned_route_geometry;

            if (empty($rawPoints) || ! is_array($rawPoints)) {
                $pLat = (float) ($shipmentOrder->pickup_latitude ?? 33.5138);
                $pLng = (float) ($shipmentOrder->pickup_longitude ?? 36.2765);
                $dLat = (float) ($shipmentOrder->delivery_latitude ?? 36.2021);
                $dLng = (float) ($shipmentOrder->delivery_longitude ?? 37.1343);
                $rawPoints = [[$pLat, $pLng], [$dLat, $dLng]];
            }

            $coordinates = array_values(array_map(function ($pt) {
                return [
                    'latitude' => (float) $pt[0],
                    'longitude' => (float) $pt[1],
                ];
            }, $rawPoints));

            $origin = ['latitude' => $coordinates[0]['latitude'], 'longitude' => $coordinates[0]['longitude']];
            $dest = ['latitude' => end($coordinates)['latitude'], 'longitude' => end($coordinates)['longitude']];

            $this->plannedRouteCache->storeIfAbsent(
                shipmentId: $shipmentId,
                coordinates: $coordinates,
                destination: $dest,
                origin: $origin,
                apiPayload: [
                    'provider' => 'graphhopper',
                    'route' => [
                        'id' => 'route-' . $shipmentId,
                        'geometry' => ['coordinates' => $coordinates],
                    ],
                ],
            );
        }

        // 2. Resolve active assignment
        $assignment = $session->assignment;
        if (! $assignment) {
            $assignment = Assignment::where('shipment_id', $shipmentId)
                ->where('is_active', true)
                ->first();
        }

        if (! $assignment) {
            return null;
        }

        // 3. Determine Speed & Simulation Overrides
        $speed = 60.0;
        if ($session->forced_speed_kmh !== null) {
            $speed = (float) $session->forced_speed_kmh;
        }
        if ($session->forced_deceleration) {
            $speed = 0.0;
        }

        // 4. Calculate dynamic elapsed seconds based on wall-clock time between ticks
        $now = now();
        $lastTickedKey = "tracking_session:{$session->id}:last_ticked_at";
        $lastTickedStr = Cache::get($lastTickedKey);
        $secondsElapsed = 2;

        if ($lastTickedStr) {
            $lastTickedAt = \Carbon\Carbon::parse($lastTickedStr);
            $diff = (int) $now->diffInSeconds($lastTickedAt);
            if ($diff >= 1 && $diff <= 30) {
                $secondsElapsed = $diff;
            }
        }
        Cache::put($lastTickedKey, $now->toIso8601String());

        // 5. Advance progress along GraphHopper road geometry via RouteProgressEngine
        $progressState = $this->routeProgressEngine->advance($assignment, $speed, $secondsElapsed);

        if (! $progressState) {
            return null;
        }

        $latitude = $progressState->currentLatitude;
        $longitude = $progressState->currentLongitude;
        $heading = $progressState->heading;

        if ($session->forced_route_deviation) {
            $latitude += 0.015;
            $longitude += 0.015;
        }

        // 5. Completion Handling
        if ($progressState->completed) {
            $session->status = TrackingSession::STATUS_COMPLETED;
            $session->ended_at = now();
            $session->save();
            $speed = 0.0;
        }

        // Diagnostic log for continuous simulation verification
        Log::info('TRACKING TICK', [
            'session' => $session->id,
            'shipment' => $shipmentId,
            'assignment' => $assignment->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $speed,
            'heading' => $heading,
            'completion_percentage' => $progressState->completionPercentage,
            'distance_travelled_m' => $progressState->distanceTravelledM,
            'completed' => $progressState->completed,
            'timestamp' => now()->toIso8601String(),
        ]);

        // 6. Persist Tracking Data and trigger updates
        $logResult = $this->createTrackingLogAction->execute([
            'shipment_id' => $shipmentId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $speed,
            'heading' => $heading,
            'accuracy' => 5.00,
            'recorded_at' => now(),
        ]);

        if (! $logResult->success) {
            return null;
        }

        return TrackingState::where('shipment_id', $shipmentId)->first();
    }
}
