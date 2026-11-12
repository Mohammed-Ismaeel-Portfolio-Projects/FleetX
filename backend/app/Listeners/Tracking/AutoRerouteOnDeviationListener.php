<?php

namespace App\Listeners\Tracking;

use App\Actions\Shipment\RerouteShipmentAction;
use App\Domain\Events\RouteDeviationDetected;
use App\Listeners\BaseListener;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\Log;

/**
 * Auto-reroutes a shipment via the existing RoutePlanner when deviation is detected.
 */
class AutoRerouteOnDeviationListener extends BaseListener
{
    public function __construct(
        private RerouteShipmentAction $rerouteShipmentAction,
        private PlannedRouteCache $plannedRouteCache,
    ) {}

    public function handle(object $event): void
    {
        if (!$event instanceof RouteDeviationDetected) {
            return;
        }

        if (!config('routing.deviation.auto_reroute', true)) {
            return;
        }

        if (!$this->plannedRouteCache->canAutoReroute($event->shipmentId)) {
            Log::info('Skipping auto-reroute due to cooldown', [
                'shipment_id' => $event->shipmentId,
            ]);
            return;
        }

        $result = $this->rerouteShipmentAction->execute(
            shipmentId: $event->shipmentId,
            latitude: $event->latitude,
            longitude: $event->longitude,
        );

        if (!$result->success) {
            Log::warning('Auto-reroute failed', [
                'shipment_id' => $event->shipmentId,
                'error' => $result->message ?: 'unknown',
            ]);
        }
    }
}
