<?php

namespace App\Events\Tracking;

use App\Events\BaseEvent;
use App\Models\TrackingState;

/**
 * Tracking State Updated Event
 * 
 * Domain event dispatched when a vehicle's tracking state is updated.
 * This event represents a change in vehicle position, speed, or status.
 * 
 * This is a pure domain event - it does NOT implement ShouldBroadcast.
 * Broadcasting will be added in a future sprint by creating a listener
 * that subscribes to this event and pushes updates via WebSocket/SSE.
 */
final class TrackingStateUpdated extends BaseEvent
{
    /**
     * The tracking state that was updated.
     */
    public readonly TrackingState $trackingState;

    /**
     * The vehicle ID.
     */
    public readonly string $vehicleId;

    /**
     * The tracking state ID.
     */
    public readonly string $trackingStateId;

    /**
     * The shipment ID (if applicable).
     */
    public readonly ?string $shipmentId;

    /**
     * The assignment ID (if applicable).
     */
    public readonly ?string $assignmentId;

    /**
     * The vehicle's current latitude.
     */
    public readonly float $latitude;

    /**
     * The vehicle's current longitude.
     */
    public readonly float $longitude;

    /**
     * The vehicle's current heading in degrees.
     */
    public readonly float $heading;

    /**
     * The vehicle's current speed in km/h.
     */
    public readonly float $speed;

    /**
     * The vehicle's movement status.
     */
    public readonly string $movementStatus;

    /**
     * The vehicle's online status.
     */
    public readonly string $onlineStatus;

    /**
     * The GPS accuracy in meters.
     */
    public readonly float $accuracy;

    /**
     * The signal quality.
     */
    public readonly int $signalQuality;

    /**
     * Create a new TrackingStateUpdated event.
     * 
     * @param TrackingState $trackingState The updated tracking state
     */
    public function __construct(TrackingState $trackingState)
    {
        parent::__construct();

        $this->trackingState = $trackingState;
        $this->trackingStateId = $trackingState->id;
        $this->vehicleId = $trackingState->vehicle_id;
        $this->shipmentId = $trackingState->shipment_id;
        $this->assignmentId = $trackingState->assignment_id;
        $this->latitude = (float) $trackingState->latitude;
        $this->longitude = (float) $trackingState->longitude;
        $this->heading = (float) $trackingState->heading;
        $this->speed = (float) $trackingState->speed;
        $this->movementStatus = $trackingState->movement_status;
        $this->onlineStatus = $trackingState->online_status;
        $this->accuracy = (float) $trackingState->accuracy;
        $this->signalQuality = (int) $trackingState->signal_quality;
    }

    /**
     * Get the event type.
     */
    public function getEventType(): string
    {
        return 'tracking.state.updated';
    }

    /**
     * Get event data for serialization.
     * 
     * This data can be used by listeners for logging, analytics,
     * or future broadcasting implementations.
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->getEventType(),
            'occurred_at' => $this->occurredAt->format(\DateTime::ATOM),
            'tracking_state_id' => $this->trackingStateId,
            'vehicle_id' => $this->vehicleId,
            'shipment_id' => $this->shipmentId,
            'assignment_id' => $this->assignmentId,
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'telemetry' => [
                'heading' => $this->heading,
                'speed' => $this->speed,
                'accuracy' => $this->accuracy,
                'signal_quality' => $this->signalQuality,
            ],
            'status' => [
                'movement_status' => $this->movementStatus,
                'online_status' => $this->onlineStatus,
            ],
        ];
    }

    /**
     * Get the location as a simple array.
     */
    public function getLocation(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * Get the telemetry data.
     */
    public function getTelemetry(): array
    {
        return [
            'heading' => $this->heading,
            'speed' => $this->speed,
            'accuracy' => $this->accuracy,
            'signal_quality' => $this->signalQuality,
        ];
    }

    /**
     * Get the status data.
     */
    public function getStatus(): array
    {
        return [
            'movement_status' => $this->movementStatus,
            'online_status' => $this->onlineStatus,
        ];
    }
}
