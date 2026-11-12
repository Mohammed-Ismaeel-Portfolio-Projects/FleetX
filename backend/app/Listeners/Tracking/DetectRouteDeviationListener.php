<?php

namespace App\Listeners\Tracking;

use App\Domain\Events\RouteDeviationDetected;
use App\Events\Tracking\TrackingStateUpdated;
use App\Listeners\BaseListener;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Simulation\Routing\Application\Services\RouteDeviationDetector;
use App\Simulation\Routing\Enums\RouteDeviationStatus;
use Illuminate\Support\Facades\Log;

/**
 * DetectRouteDeviationListener
 *
 * Queued listener — must not block GPS TrackingStateUpdated handling.
 */
class DetectRouteDeviationListener extends BaseListener
{
    public function __construct(
        private RouteDeviationDetector $detector,
        private PlannedRouteCache $plannedRouteCache,
    ) {}

    public function handle(object $event): void
    {
        if (!$event instanceof TrackingStateUpdated) {
            return;
        }

        if (!config('routing.deviation.enabled', true)) {
            return;
        }

        if ($event->shipmentId === null || $event->shipmentId === '') {
            return;
        }

        $cached = $this->plannedRouteCache->get($event->shipmentId);
        if ($cached === null || empty($cached['coordinates']) || !is_array($cached['coordinates'])) {
            // No planned geometry yet (dispatcher has not loaded the route).
            return;
        }

        $previousStatus = (string) ($cached['route_status'] ?? RouteDeviationStatus::ON_ROUTE->value);

        $result = $this->detector->detect(
            latitude: $event->latitude,
            longitude: $event->longitude,
            routeCoordinates: $cached['coordinates'],
            speedKmh: $event->speed,
            shipmentId: $event->shipmentId,
        );

        $this->plannedRouteCache->updateDeviationStatus(
            $event->shipmentId,
            $result->routeStatus->value,
            $result->deviationDistanceMeters,
        );

        if (!$result->isDeviated) {
            return;
        }

        // Fire domain event on transition into DEVIATED to avoid alert spam.
        if ($previousStatus === RouteDeviationStatus::DEVIATED->value) {
            return;
        }

        Log::info('Route deviation detected', [
            'vehicle_id' => $event->vehicleId,
            'shipment_id' => $event->shipmentId,
            'deviation_distance' => $result->deviationDistanceMeters,
        ]);

        RouteDeviationDetected::dispatch(
            $event->vehicleId,
            $event->shipmentId,
            $event->assignmentId,
            $event->latitude,
            $event->longitude,
            $result->deviationDistanceMeters,
            now()->toIso8601String(),
        );
    }
}
