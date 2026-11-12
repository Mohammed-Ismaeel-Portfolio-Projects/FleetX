<?php

namespace App\Simulation\Journey;

use App\Simulation\Lifecycle\GeofenceProximityDetector;
use App\Simulation\Progress\RouteProgressEngine;
use Illuminate\Support\Facades\Log;

/**
 * Coordinates rest-stop planning/execution with route progress (C2.9-C2.8 Step 5).
 *
 * Ensures a stable JourneyPlan, clamps progress so rest waypoints cannot be skipped,
 * holds movement while resting, and completes rests after configured duration.
 */
final class JourneyRestEngine
{
    private float $approachDistanceM;

    private float $geofenceRadiusM;

    public function __construct(
        private readonly JourneyPlanStore $store = new JourneyPlanStore(),
        private readonly RestStopPlanner $planner = new RestStopPlanner(
            maxDrivingDistanceM: 80_000,
            restDurationSeconds: 900,
            minRemainingAfterRestM: 20_000,
            assumedCruiseSpeedKmh: 80,
        ),
        private readonly GeofenceProximityDetector $geofence = new GeofenceProximityDetector(),
        private readonly ?RouteProgressEngine $routeProgressEngine = null,
        ?float $approachDistanceM = null,
        ?float $geofenceRadiusM = null,
    ) {
        $this->approachDistanceM = $approachDistanceM
            ?? (float) config('simulation.rest.approach_distance_m', 1500);
        $this->geofenceRadiusM = $geofenceRadiusM
            ?? (float) config('simulation.rest.geofence_radius_m', 150);
    }

    public static function fromContainer(): self
    {
        return new self(
            store: app(JourneyPlanStore::class),
            planner: RestStopPlanner::fromConfig(),
            geofence: new GeofenceProximityDetector(),
            routeProgressEngine: app(RouteProgressEngine::class),
        );
    }

    public function getPlan(string $shipmentId): ?JourneyPlan
    {
        return $this->store->get($shipmentId);
    }

    public function isResting(string $shipmentId): bool
    {
        return (bool) $this->store->get($shipmentId)?->isResting;
    }

    /**
     * Create plan once for a shipment/route. Idempotent for same total distance (±1m).
     */
    public function ensurePlan(string $shipmentId, float $totalDistanceM): JourneyPlan
    {
        $existing = $this->store->get($shipmentId);
        if ($existing !== null && abs($existing->totalDistanceM - $totalDistanceM) < 1.0) {
            return $existing;
        }

        $geometry = $this->routeProgressEngine?->getGeometry($shipmentId);
        $plan = $this->planner->plan($shipmentId, $totalDistanceM, $geometry);
        $this->store->put($plan);

        Log::info('Journey rest plan created', [
            'shipment_id' => $shipmentId,
            'total_distance_m' => $totalDistanceM,
            'rest_stop_count' => count($plan->restStops),
        ]);

        return $plan;
    }

    /**
     * Max absolute travelled distance allowed this tick (clamp at next rest).
     * Null = no clamp. When resting, returns current travelled (zero net advance).
     */
    public function maxTravelledCapM(string $shipmentId, float $currentTravelledM): ?float
    {
        $plan = $this->store->get($shipmentId);
        if ($plan === null) {
            return null;
        }

        if ($plan->isResting) {
            return $currentTravelledM;
        }

        $next = $plan->nextIncompleteStop();
        if ($next === null) {
            return null;
        }

        // Cap at rest center so large ticks cannot jump past the waypoint.
        if ($currentTravelledM < $next->routeOffsetM) {
            return $next->routeOffsetM;
        }

        return null;
    }

    /**
     * Sync plan with latest progress: approach / enter rest / complete rest timer.
     *
     * @return JourneyPlan Updated plan
     */
    public function syncWithProgress(
        string $shipmentId,
        float $travelledM,
        ?float $previousTravelledM,
        string $timestamp,
        float $totalDistanceM,
    ): JourneyPlan {
        $plan = $this->ensurePlan($shipmentId, $totalDistanceM);

        if ($plan->isResting) {
            return $this->progressRestTimer($plan, $timestamp);
        }

        $next = $plan->nextIncompleteStop();
        if ($next === null) {
            $plan->drivingSinceRestM = $this->drivingSinceRest($plan, $travelledM);
            $this->store->put($plan);

            return $plan;
        }

        $reached = $this->geofence->waypointReached(
            $travelledM,
            $next->routeOffsetM,
            $this->geofenceRadiusM,
            $previousTravelledM,
        );

        $distanceToStop = abs($travelledM - $next->routeOffsetM);
        $approaching = $distanceToStop <= $this->approachDistanceM
            || ($previousTravelledM !== null
                && $previousTravelledM < $next->routeOffsetM - $this->approachDistanceM
                && $travelledM >= $next->routeOffsetM - $this->approachDistanceM);

        if ($reached) {
            return $this->beginRest($plan, $next, $timestamp, $travelledM);
        }

        if ($approaching && $next->status === RestStopStatus::Planned) {
            $next->status = RestStopStatus::Approaching;
            $this->store->put($plan);
            Log::info('Approaching rest stop', [
                'shipment_id' => $shipmentId,
                'rest_stop_id' => $next->id,
                'route_offset_m' => $next->routeOffsetM,
            ]);
        }

        $plan->drivingSinceRestM = $this->drivingSinceRest($plan, $travelledM);
        $this->store->put($plan);

        return $plan;
    }

    public function shouldForceIdle(string $shipmentId): bool
    {
        return $this->isResting($shipmentId);
    }

    private function beginRest(
        JourneyPlan $plan,
        RestStopWaypoint $stop,
        string $timestamp,
        float $travelledM,
    ): JourneyPlan {
        if ($plan->isResting && $plan->activeRestStopId === $stop->id) {
            return $plan;
        }

        $stop->status = RestStopStatus::Active;
        $stop->restStartedAt = $timestamp;
        $plan->isResting = true;
        $plan->activeRestStopId = $stop->id;
        $plan->restStartedAt = $timestamp;
        $plan->drivingSinceRestM = $this->drivingSinceRest($plan, $travelledM);
        $this->store->put($plan);

        Log::info('Rest stop started', [
            'shipment_id' => $plan->shipmentId,
            'rest_stop_id' => $stop->id,
            'duration_seconds' => $stop->durationSeconds,
        ]);

        return $plan;
    }

    private function progressRestTimer(JourneyPlan $plan, string $timestamp): JourneyPlan
    {
        $started = $plan->restStartedAt;
        $activeId = $plan->activeRestStopId;
        $stop = $activeId !== null ? $plan->findStop($activeId) : null;
        $duration = $stop?->durationSeconds ?? $plan->restDurationSeconds;

        if ($started === null || ! $this->timerElapsed($started, $timestamp, $duration)) {
            return $plan;
        }

        if ($stop !== null) {
            $stop->status = RestStopStatus::Completed;
            $stop->restCompletedAt = $timestamp;
        }

        $plan->isResting = false;
        $plan->activeRestStopId = null;
        $plan->restStartedAt = null;
        $plan->drivingSinceRestM = 0.0;
        $this->store->put($plan);

        Log::info('Rest stop completed — resume route', [
            'shipment_id' => $plan->shipmentId,
            'rest_stop_id' => $activeId,
        ]);

        return $plan;
    }

    private function drivingSinceRest(JourneyPlan $plan, float $travelledM): float
    {
        $lastCompletedOffset = 0.0;
        foreach ($plan->restStops as $stop) {
            if ($stop->status === RestStopStatus::Completed) {
                $lastCompletedOffset = max($lastCompletedOffset, $stop->routeOffsetM);
            }
        }

        return max(0.0, $travelledM - $lastCompletedOffset);
    }

    private function timerElapsed(string $startedAt, string $now, int $durationSeconds): bool
    {
        try {
            $start = new \DateTimeImmutable($startedAt);
            $end = new \DateTimeImmutable($now);
        } catch (\Throwable) {
            return false;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) >= $durationSeconds;
    }
}
