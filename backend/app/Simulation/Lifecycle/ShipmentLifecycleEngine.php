<?php

namespace App\Simulation\Lifecycle;

use App\Models\Shipment;
use App\Simulation\Journey\JourneyPlan;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Journey\RestStopStatus;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ShipmentLifecycleEngine
 *
 * Observes telemetry, route progress, journey rest state, and vehicle state to advance
 * operational shipment lifecycle stages (geofence + rest-aware — C2.9-C2.8 Step 4/5).
 *
 * Canonical Shipment.status_id writes go through ShipmentLifecycleTransitionAdapter
 * → TransitionShipmentStatusAction. This engine does not mutate status_id.
 * Rest stages remain ops-only; shipment stays In Transit while resting.
 */
class ShipmentLifecycleEngine
{
    public const CACHE_PREFIX = 'simulation:lifecycle:';

    private const CACHE_TTL_SECONDS = 86400;

    private float $arrivalDistanceM;

    private float $arrivalSpeedKmh;

    private int $loadingDurationSeconds;

    private int $unloadingDurationSeconds;

    private ShipmentLifecycleTransitionAdapter $transitionAdapter;

    private GeofenceProximityDetector $geofence;

    private JourneyRestEngine $journeyRest;

    public function __construct(
        ?float $arrivalDistanceM = null,
        ?float $arrivalSpeedKmh = null,
        ?int $loadingDurationSeconds = null,
        ?int $unloadingDurationSeconds = null,
        ?ShipmentLifecycleTransitionAdapter $transitionAdapter = null,
        ?GeofenceProximityDetector $geofence = null,
        ?JourneyRestEngine $journeyRest = null,
    ) {
        $this->arrivalDistanceM = $arrivalDistanceM ?? (float) config('simulation.lifecycle.arrival_distance_m', 150);
        $this->arrivalSpeedKmh = $arrivalSpeedKmh ?? (float) config('simulation.lifecycle.arrival_speed_kmh', 8);
        $this->loadingDurationSeconds = $loadingDurationSeconds ?? (int) config('simulation.lifecycle.loading_duration_seconds', 180);
        $this->unloadingDurationSeconds = $unloadingDurationSeconds ?? (int) config('simulation.lifecycle.unloading_duration_seconds', 180);
        $this->transitionAdapter = $transitionAdapter ?? app(ShipmentLifecycleTransitionAdapter::class);
        $this->geofence = $geofence ?? new GeofenceProximityDetector();
        $this->journeyRest = $journeyRest ?? JourneyRestEngine::fromContainer();
    }

    /**
     * @param 'moving'|'idle'|'stopped'|'offline' $vehicleState
     */
    public function evaluate(
        string $shipmentId,
        string $vehicleState,
        ?TelemetrySnapshot $telemetry,
        ?RouteProgressState $progress,
        ?string $timestamp = null,
    ): ShipmentLifecycleSnapshot {
        $timestamp ??= now()->toIso8601String();
        $previous = $this->load($shipmentId);
        $current = $previous?->stage() ?? $this->bootstrapStage($shipmentId, $vehicleState, $progress);

        $speed = $telemetry?->currentSpeedKmh ?? 0.0;
        $remaining = $progress?->remainingDistanceM;
        $travelled = $progress?->distanceTravelledM ?? 0.0;
        $total = $progress?->totalDistanceM ?? 0.0;
        $completed = (bool) ($progress?->completed ?? false);

        $pastPickupStages = in_array($current, [
            ShipmentLifecycleStage::ArrivedPickup,
            ShipmentLifecycleStage::Loading,
            ShipmentLifecycleStage::Loaded,
            ShipmentLifecycleStage::Departed,
            ShipmentLifecycleStage::EnRouteDelivery,
            ShipmentLifecycleStage::ApproachingRestStop,
            ShipmentLifecycleStage::Resting,
            ShipmentLifecycleStage::ArrivedDelivery,
            ShipmentLifecycleStage::Unloading,
            ShipmentLifecycleStage::Delivered,
            ShipmentLifecycleStage::Completed,
        ], true);

        $pickupReached = $this->geofence->pickupReached(
            $travelled,
            $total,
            $this->arrivalDistanceM,
            $previous?->lastTravelledM,
            $pastPickupStages,
        ) || ($previous?->pickupGeofenceReached ?? false);

        $deliveryReached = $this->geofence->deliveryReached(
            $remaining,
            $completed,
            $this->arrivalDistanceM,
            $previous?->lastRemainingDistanceM,
        ) || ($previous?->deliveryGeofenceReached ?? false);

        $journey = null;
        if ($total > 0 && in_array($current, [
            ShipmentLifecycleStage::Departed,
            ShipmentLifecycleStage::EnRouteDelivery,
            ShipmentLifecycleStage::ApproachingRestStop,
            ShipmentLifecycleStage::Resting,
        ], true)) {
            $journey = $this->journeyRest->syncWithProgress(
                $shipmentId,
                $travelled,
                $previous?->lastTravelledM,
                $timestamp,
                $total,
            );
        } else {
            $journey = $this->journeyRest->getPlan($shipmentId);
        }

        $slowEnough = $speed <= $this->arrivalSpeedKmh;
        $moving = $vehicleState === 'moving';
        $idleLike = in_array($vehicleState, ['idle', 'stopped'], true);

        $arrivalTime = $previous?->arrivalTime;
        $loadingStarted = $previous?->loadingStarted;
        $loadingCompleted = $previous?->loadingCompleted;
        $departureTime = $previous?->departureTime;
        $deliveryTime = $previous?->deliveryTime;
        $completionTime = $previous?->completionTime;

        [$next, $reason, $arrivalTime, $loadingStarted, $loadingCompleted, $departureTime, $deliveryTime, $completionTime] =
            $this->applyRules(
                $current,
                $moving,
                $idleLike,
                $pickupReached,
                $deliveryReached,
                $slowEnough,
                $timestamp,
                $arrivalTime,
                $loadingStarted,
                $loadingCompleted,
                $departureTime,
                $deliveryTime,
                $completionTime,
                $previous,
                $journey,
            );

        $changed = $next !== $current;

        $snapshot = new ShipmentLifecycleSnapshot(
            shipmentId: $shipmentId,
            currentStage: $next->value,
            previousStage: $changed ? $current->value : ($previous?->previousStage),
            transitionReason: $changed ? $reason : ($previous?->transitionReason),
            arrivalTime: $arrivalTime,
            loadingStarted: $loadingStarted,
            loadingCompleted: $loadingCompleted,
            departureTime: $departureTime,
            deliveryTime: $deliveryTime,
            completionTime: $completionTime,
            timestamp: $timestamp,
            stageChangedThisTick: $changed,
            lastTravelledM: $travelled,
            lastRemainingDistanceM: $remaining,
            pickupGeofenceReached: $pickupReached
                || in_array($next, [
                    ShipmentLifecycleStage::ArrivedPickup,
                    ShipmentLifecycleStage::Loading,
                    ShipmentLifecycleStage::Loaded,
                    ShipmentLifecycleStage::Departed,
                    ShipmentLifecycleStage::EnRouteDelivery,
                    ShipmentLifecycleStage::ApproachingRestStop,
                    ShipmentLifecycleStage::Resting,
                ], true),
            deliveryGeofenceReached: $deliveryReached
                || in_array($next, [
                    ShipmentLifecycleStage::ArrivedDelivery,
                    ShipmentLifecycleStage::Unloading,
                    ShipmentLifecycleStage::Delivered,
                    ShipmentLifecycleStage::Completed,
                ], true),
            isResting: (bool) ($journey?->isResting ?? false),
            activeRestStopId: $journey?->activeRestStopId,
            nextRestOffsetM: $journey?->nextIncompleteStop()?->routeOffsetM,
            drivingSinceRestM: $journey?->drivingSinceRestM,
        );

        $this->store($snapshot);

        if ($changed) {
            Log::info('Shipment lifecycle transition', [
                'shipment_id' => $shipmentId,
                'previous_stage' => $current->value,
                'next_stage' => $next->value,
                'reason' => $reason,
                'pickup_geofence_reached' => $snapshot->pickupGeofenceReached,
                'delivery_geofence_reached' => $snapshot->deliveryGeofenceReached,
                'is_resting' => $snapshot->isResting,
            ]);
            $this->transitionAdapter->applyOperationalStage($shipmentId, $next);
        }

        return $snapshot;
    }

    public function load(string $shipmentId): ?ShipmentLifecycleSnapshot
    {
        $data = Cache::get(self::CACHE_PREFIX . $shipmentId);
        if (! is_array($data)) {
            return null;
        }

        return ShipmentLifecycleSnapshot::fromArray($data);
    }

    public function store(ShipmentLifecycleSnapshot $snapshot): void
    {
        Cache::put(
            self::CACHE_PREFIX . $snapshot->shipmentId,
            $snapshot->toArray(),
            self::CACHE_TTL_SECONDS
        );
    }

    /**
     * @return array{
     *     0: ShipmentLifecycleStage,
     *     1: ?string,
     *     2: ?string,
     *     3: ?string,
     *     4: ?string,
     *     5: ?string,
     *     6: ?string,
     *     7: ?string
     * }
     */
    private function applyRules(
        ShipmentLifecycleStage $current,
        bool $moving,
        bool $idleLike,
        bool $pickupReached,
        bool $deliveryReached,
        bool $slowEnough,
        string $timestamp,
        ?string $arrivalTime,
        ?string $loadingStarted,
        ?string $loadingCompleted,
        ?string $departureTime,
        ?string $deliveryTime,
        ?string $completionTime,
        ?ShipmentLifecycleSnapshot $previous,
        ?JourneyPlan $journey,
    ): array {
        $next = $current;
        $reason = null;

        switch ($current) {
            case ShipmentLifecycleStage::Created:
            case ShipmentLifecycleStage::Assigned:
                if ($moving || $idleLike) {
                    $next = ShipmentLifecycleStage::Accepted;
                    $reason = 'Assignment accepted for simulation run';
                }
                break;

            case ShipmentLifecycleStage::Accepted:
                if ($pickupReached) {
                    $next = ShipmentLifecycleStage::ArrivedPickup;
                    $reason = 'Arrived at pickup geofence (containment or crossing)';
                    $arrivalTime = $arrivalTime ?? $timestamp;
                } elseif ($moving) {
                    $next = ShipmentLifecycleStage::EnRoutePickup;
                    $reason = 'Approaching pickup';
                }
                break;

            case ShipmentLifecycleStage::EnRoutePickup:
                if ($pickupReached) {
                    $next = ShipmentLifecycleStage::ArrivedPickup;
                    $reason = 'Arrived at pickup geofence (containment or crossing)';
                    $arrivalTime = $arrivalTime ?? $timestamp;
                }
                break;

            case ShipmentLifecycleStage::ArrivedPickup:
                if ($idleLike || $slowEnough) {
                    $next = ShipmentLifecycleStage::Loading;
                    $reason = 'Loading started (vehicle idle/slow at pickup)';
                    $loadingStarted = $loadingStarted ?? $timestamp;
                } elseif ($moving && ! $pickupReached) {
                    $next = ShipmentLifecycleStage::EnRoutePickup;
                    $reason = 'Left pickup zone before loading';
                }
                break;

            case ShipmentLifecycleStage::Loading:
                $loadingStarted = $loadingStarted ?? $timestamp;
                if ($this->timerElapsed($loadingStarted, $timestamp, $this->loadingDurationSeconds)) {
                    $next = ShipmentLifecycleStage::Loaded;
                    $reason = 'Loading timer completed';
                    $loadingCompleted = $timestamp;
                }
                break;

            case ShipmentLifecycleStage::Loaded:
                if ($moving) {
                    $next = ShipmentLifecycleStage::Departed;
                    $reason = 'Departed pickup after loading';
                    $departureTime = $timestamp;
                }
                break;

            case ShipmentLifecycleStage::Departed:
                if ($moving || ! $pickupReached) {
                    $next = ShipmentLifecycleStage::EnRouteDelivery;
                    $reason = 'En route to delivery';
                }
                break;

            case ShipmentLifecycleStage::EnRouteDelivery:
                if ($deliveryReached) {
                    $next = ShipmentLifecycleStage::ArrivedDelivery;
                    $reason = 'Arrived at delivery geofence (containment or crossing)';
                    $arrivalTime = $timestamp;
                } elseif ($journey?->isResting) {
                    $next = ShipmentLifecycleStage::Resting;
                    $reason = 'Entered rest stop — driving limit reached';
                } elseif ($this->isApproachingRest($journey)) {
                    $next = ShipmentLifecycleStage::ApproachingRestStop;
                    $reason = 'Approaching planned rest stop';
                }
                break;

            case ShipmentLifecycleStage::ApproachingRestStop:
                if ($deliveryReached) {
                    $next = ShipmentLifecycleStage::ArrivedDelivery;
                    $reason = 'Arrived at delivery geofence during rest approach';
                    $arrivalTime = $timestamp;
                } elseif ($journey?->isResting) {
                    $next = ShipmentLifecycleStage::Resting;
                    $reason = 'At rest stop geofence — resting';
                } elseif (! $this->isApproachingRest($journey) && ! $journey?->isResting) {
                    // Rest cancelled / already completed without resting stage
                    $next = ShipmentLifecycleStage::EnRouteDelivery;
                    $reason = 'Resumed en route after rest approach cleared';
                }
                break;

            case ShipmentLifecycleStage::Resting:
                if ($deliveryReached) {
                    $next = ShipmentLifecycleStage::ArrivedDelivery;
                    $reason = 'Arrived at delivery after rest';
                    $arrivalTime = $timestamp;
                } elseif ($journey !== null && ! $journey->isResting) {
                    $next = ShipmentLifecycleStage::EnRouteDelivery;
                    $reason = 'Rest duration completed — resume delivery route';
                }
                break;

            case ShipmentLifecycleStage::ArrivedDelivery:
                if ($idleLike || $slowEnough) {
                    $next = ShipmentLifecycleStage::Unloading;
                    $reason = 'Unloading started (vehicle idle/slow at delivery)';
                    $deliveryTime = $deliveryTime ?? $timestamp;
                }
                break;

            case ShipmentLifecycleStage::Unloading:
                $unloadStarted = $deliveryTime ?? $previous?->timestamp ?? $timestamp;
                if ($this->timerElapsed($unloadStarted, $timestamp, $this->unloadingDurationSeconds)) {
                    $next = ShipmentLifecycleStage::Delivered;
                    $reason = 'Unload timer completed — delivery confirmed';
                    $deliveryTime = $timestamp;
                }
                break;

            case ShipmentLifecycleStage::Delivered:
                $next = ShipmentLifecycleStage::Completed;
                $reason = 'Delivery confirmed — shipment completed';
                $completionTime = $timestamp;
                break;

            case ShipmentLifecycleStage::Completed:
                break;
        }

        return [$next, $reason, $arrivalTime, $loadingStarted, $loadingCompleted, $departureTime, $deliveryTime, $completionTime];
    }

    private function isApproachingRest(?JourneyPlan $journey): bool
    {
        if ($journey === null) {
            return false;
        }

        $next = $journey->nextIncompleteStop();
        if ($next === null) {
            return false;
        }

        return in_array($next->status, [RestStopStatus::Approaching, RestStopStatus::Active], true)
            || $journey->isResting;
    }

    private function bootstrapStage(
        string $shipmentId,
        string $vehicleState,
        ?RouteProgressState $progress,
    ): ShipmentLifecycleStage {
        $name = null;
        try {
            $shipment = Shipment::with('status')->find($shipmentId);
            $name = $shipment?->status?->name;
        } catch (\Throwable) {
            $name = null;
        }

        return match ($name) {
            'Draft' => ShipmentLifecycleStage::Created,
            'Assigned', 'Waiting Assignment' => ShipmentLifecycleStage::Assigned,
            'Ready For Pickup' => ShipmentLifecycleStage::Accepted,
            'Picked Up' => ShipmentLifecycleStage::Loaded,
            'In Transit', 'Out For Delivery' => ShipmentLifecycleStage::EnRouteDelivery,
            'Delivered' => ShipmentLifecycleStage::Delivered,
            'Completed' => ShipmentLifecycleStage::Completed,
            default => ShipmentLifecycleStage::Assigned,
        };
    }

    private function timerElapsed(?string $startedAt, string $now, int $durationSeconds): bool
    {
        if ($startedAt === null || $startedAt === '') {
            return false;
        }

        try {
            $start = new \DateTimeImmutable($startedAt);
            $end = new \DateTimeImmutable($now);
        } catch (\Throwable) {
            return false;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) >= $durationSeconds;
    }
}
