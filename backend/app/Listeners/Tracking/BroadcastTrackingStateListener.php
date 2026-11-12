<?php

namespace App\Listeners\Tracking;

use App\Events\Tracking\TrackingStateBroadcasted;
use App\Events\Tracking\TrackingStateUpdated;

/**
 * Broadcast Tracking State Listener
 * 
 * This listener subscribes to the TrackingStateUpdated domain event
 * and dispatches the TrackingStateBroadcasted event for broadcasting.
 * 
 * This separation ensures:
 * - Domain events remain pure and independent of broadcasting
 * - Broadcasting logic is isolated in its own layer
 * - Multiple listeners can subscribe to the same domain event
 * - Broadcasting can be enabled/disabled without affecting domain logic
 */
class BroadcastTrackingStateListener
{
    /**
     * Handle the TrackingStateUpdated event.
     * 
     * @param TrackingStateUpdated $event The domain event
     * @return void
     */
    public function handle(TrackingStateUpdated $event): void
    {
        // Positional args: Dispatchable::dispatch() uses func_get_args() and rejects named params.
        TrackingStateBroadcasted::dispatch(
            $event->vehicleId,
            $event->shipmentId,
            $event->assignmentId,
            $event->latitude,
            $event->longitude,
            $event->heading,
            $event->speed,
            $event->movementStatus,
            $event->onlineStatus,
            $event->accuracy,
            $event->signalQuality,
            $event->trackingState->last_update_at?->format(\DateTime::ATOM) ?? now()->format(\DateTime::ATOM)
        );
    }
}
