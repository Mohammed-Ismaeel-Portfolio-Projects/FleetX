<?php

namespace App\Simulation\Lifecycle;

/**
 * Geofence / proximity helpers for Engine operational stages (C2.9-C2.8 Step 4).
 *
 * Detects both containment and tick-to-tick crossing so large simulation steps
 * cannot silently skip pickup/delivery milestones.
 */
final class GeofenceProximityDetector
{
    /**
     * True when current distance-to-point is inside the geofence radius.
     */
    public function isInside(float $distanceM, float $radiusM): bool
    {
        return $distanceM <= $radiusM;
    }

    /**
     * True when the previous sample was outside and the current sample is inside,
     * or the step jumped across the boundary (previous outside, current deeper inside / past).
     *
     * @param float|null $previousDistanceM Distance to the geofence center on prior tick (null = unknown)
     * @param float $currentDistanceM Distance to the geofence center now
     */
    public function crossedInto(?float $previousDistanceM, float $currentDistanceM, float $radiusM): bool
    {
        if ($this->isInside($currentDistanceM, $radiusM)) {
            if ($previousDistanceM === null) {
                return false;
            }

            return $previousDistanceM > $radiusM;
        }

        // Jump completely over the zone: was outside approaching, now on the far side.
        if ($previousDistanceM !== null && $previousDistanceM > $radiusM) {
            // For remaining-distance (delivery): remaining went from >radius to a value that
            // implies we passed the center (e.g. completed route). Callers pass 0 when completed.
            return $currentDistanceM === 0.0;
        }

        return false;
    }

    /**
     * Pickup along a route measured by distance travelled from origin.
     * Inside when still near origin; crossed-through when a tick exits the origin zone
     * without a prior arrival while still in approach stages.
     */
    public function pickupReached(
        float $distanceTravelledM,
        float $totalDistanceM,
        float $radiusM,
        ?float $previousTravelledM,
        bool $alreadyPastPickupStages,
    ): bool {
        if ($totalDistanceM <= 0) {
            return false;
        }

        if ($this->isInside($distanceTravelledM, $radiusM)) {
            return true;
        }

        // Tick jumped past the origin geofence while still approaching pickup.
        if (! $alreadyPastPickupStages && $distanceTravelledM > $radiusM) {
            if ($previousTravelledM === null || $previousTravelledM <= $radiusM) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delivery measured by remaining distance to route end.
     */
    public function deliveryReached(
        ?float $remainingDistanceM,
        bool $routeCompleted,
        float $radiusM,
        ?float $previousRemainingM,
    ): bool {
        if ($routeCompleted) {
            return true;
        }

        if ($remainingDistanceM === null) {
            return false;
        }

        if ($this->isInside($remainingDistanceM, $radiusM)) {
            return true;
        }

        return $this->crossedInto($previousRemainingM, $remainingDistanceM, $radiusM);
    }

    /**
     * Waypoint along-route (e.g. rest stop at distance offset).
     * Detects containment and tick jumps that skip the geofence disk.
     */
    public function waypointReached(
        float $positionAlongRouteM,
        float $waypointOffsetM,
        float $radiusM,
        ?float $previousPositionAlongRouteM,
    ): bool {
        $currentDist = abs($positionAlongRouteM - $waypointOffsetM);
        if ($this->isInside($currentDist, $radiusM)) {
            return true;
        }

        if ($previousPositionAlongRouteM === null) {
            return false;
        }

        $wasBefore = $previousPositionAlongRouteM < ($waypointOffsetM - $radiusM);
        $nowAfter = $positionAlongRouteM > ($waypointOffsetM + $radiusM);
        if ($wasBefore && $nowAfter) {
            return true;
        }

        $previousDist = abs($previousPositionAlongRouteM - $waypointOffsetM);

        return $this->crossedInto($previousDist, $currentDist, $radiusM);
    }
}
