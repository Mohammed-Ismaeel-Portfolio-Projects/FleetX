<?php

namespace App\Simulation\Lifecycle;

/**
 * ShipmentLifecycleSnapshot
 *
 * Immutable lifecycle state for one shipment / tick.
 */
readonly class ShipmentLifecycleSnapshot
{
    public function __construct(
        public string $shipmentId,
        public string $currentStage,
        public ?string $previousStage,
        public ?string $transitionReason,
        public ?string $arrivalTime,
        public ?string $loadingStarted,
        public ?string $loadingCompleted,
        public ?string $departureTime,
        public ?string $deliveryTime,
        public ?string $completionTime,
        public string $timestamp = '',
        public bool $stageChangedThisTick = false,
        public ?float $lastTravelledM = null,
        public ?float $lastRemainingDistanceM = null,
        public bool $pickupGeofenceReached = false,
        public bool $deliveryGeofenceReached = false,
        public bool $isResting = false,
        public ?string $activeRestStopId = null,
        public ?float $nextRestOffsetM = null,
        public ?float $drivingSinceRestM = null,
    ) {}

    public function stage(): ShipmentLifecycleStage
    {
        return ShipmentLifecycleStage::tryFrom($this->currentStage)
            ?? ShipmentLifecycleStage::Assigned;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shipment_id' => $this->shipmentId,
            'current_stage' => $this->currentStage,
            'previous_stage' => $this->previousStage,
            'transition_reason' => $this->transitionReason,
            'arrival_time' => $this->arrivalTime,
            'loading_started' => $this->loadingStarted,
            'loading_completed' => $this->loadingCompleted,
            'departure_time' => $this->departureTime,
            'delivery_time' => $this->deliveryTime,
            'completion_time' => $this->completionTime,
            'timestamp' => $this->timestamp,
            'stage_changed_this_tick' => $this->stageChangedThisTick,
            'last_travelled_m' => $this->lastTravelledM,
            'last_remaining_distance_m' => $this->lastRemainingDistanceM,
            'pickup_geofence_reached' => $this->pickupGeofenceReached,
            'delivery_geofence_reached' => $this->deliveryGeofenceReached,
            'is_resting' => $this->isResting,
            'active_rest_stop_id' => $this->activeRestStopId,
            'next_rest_offset_m' => $this->nextRestOffsetM,
            'driving_since_rest_m' => $this->drivingSinceRestM,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            shipmentId: (string) ($data['shipment_id'] ?? ''),
            currentStage: (string) ($data['current_stage'] ?? ShipmentLifecycleStage::Assigned->value),
            previousStage: isset($data['previous_stage']) ? (string) $data['previous_stage'] : null,
            transitionReason: isset($data['transition_reason']) ? (string) $data['transition_reason'] : null,
            arrivalTime: isset($data['arrival_time']) ? (string) $data['arrival_time'] : null,
            loadingStarted: isset($data['loading_started']) ? (string) $data['loading_started'] : null,
            loadingCompleted: isset($data['loading_completed']) ? (string) $data['loading_completed'] : null,
            departureTime: isset($data['departure_time']) ? (string) $data['departure_time'] : null,
            deliveryTime: isset($data['delivery_time']) ? (string) $data['delivery_time'] : null,
            completionTime: isset($data['completion_time']) ? (string) $data['completion_time'] : null,
            timestamp: (string) ($data['timestamp'] ?? ''),
            stageChangedThisTick: (bool) ($data['stage_changed_this_tick'] ?? false),
            lastTravelledM: isset($data['last_travelled_m']) ? (float) $data['last_travelled_m'] : null,
            lastRemainingDistanceM: isset($data['last_remaining_distance_m'])
                ? (float) $data['last_remaining_distance_m']
                : null,
            pickupGeofenceReached: (bool) ($data['pickup_geofence_reached'] ?? false),
            deliveryGeofenceReached: (bool) ($data['delivery_geofence_reached'] ?? false),
            isResting: (bool) ($data['is_resting'] ?? false),
            activeRestStopId: isset($data['active_rest_stop_id']) ? (string) $data['active_rest_stop_id'] : null,
            nextRestOffsetM: isset($data['next_rest_offset_m']) ? (float) $data['next_rest_offset_m'] : null,
            drivingSinceRestM: isset($data['driving_since_rest_m']) ? (float) $data['driving_since_rest_m'] : null,
        );
    }
}
