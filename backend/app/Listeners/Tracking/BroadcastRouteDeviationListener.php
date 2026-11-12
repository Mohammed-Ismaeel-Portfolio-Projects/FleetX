<?php

namespace App\Listeners\Tracking;

use App\Domain\Events\RouteDeviationDetected;
use App\Events\Tracking\RouteDeviationBroadcasted;

/**
 * Bridges pure RouteDeviationDetected domain event to Laravel Broadcasting.
 */
class BroadcastRouteDeviationListener
{
    public function handle(RouteDeviationDetected $event): void
    {
        RouteDeviationBroadcasted::dispatch(
            $event->vehicleId,
            $event->shipmentId,
            $event->assignmentId,
            $event->latitude,
            $event->longitude,
            $event->deviationDistance,
            $event->detectedAt,
        );
    }
}
