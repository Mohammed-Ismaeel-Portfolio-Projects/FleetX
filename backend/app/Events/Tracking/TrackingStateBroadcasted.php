<?php

namespace App\Events\Tracking;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tracking State Broadcasted Event
 * 
 * This event is responsible ONLY for broadcasting tracking state updates to clients.
 * It implements ShouldBroadcastNow so Reverb receives updates without a queue worker.
 * 
 * This is a SEPARATE event from TrackingStateUpdated (domain event).
 * The domain event remains pure and independent of broadcasting concerns.
 * This event is created by the BroadcastTrackingStateListener when the domain event is dispatched.
 */
final class TrackingStateBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The vehicle ID.
     */
    public readonly string $vehicleId;

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
     * The timestamp of the tracking update.
     */
    public readonly string $timestamp;

    /**
     * Create a new TrackingStateBroadcasted event.
     * 
     * @param string $vehicleId The vehicle ID
     * @param string|null $shipmentId The shipment ID
     * @param string|null $assignmentId The assignment ID
     * @param float $latitude The vehicle's latitude
     * @param float $longitude The vehicle's longitude
     * @param float $heading The vehicle's heading
     * @param float $speed The vehicle's speed
     * @param string $movementStatus The movement status
     * @param string $onlineStatus The online status
     * @param float $accuracy The GPS accuracy
     * @param int $signalQuality The signal quality
     * @param string $timestamp The timestamp of the update
     */
    public function __construct(
        string $vehicleId,
        ?string $shipmentId,
        ?string $assignmentId,
        float $latitude,
        float $longitude,
        float $heading,
        float $speed,
        string $movementStatus,
        string $onlineStatus,
        float $accuracy,
        int $signalQuality,
        string $timestamp
    ) {
        $this->vehicleId = $vehicleId;
        $this->shipmentId = $shipmentId;
        $this->assignmentId = $assignmentId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->heading = $heading;
        $this->speed = $speed;
        $this->movementStatus = $movementStatus;
        $this->onlineStatus = $onlineStatus;
        $this->accuracy = $accuracy;
        $this->signalQuality = $signalQuality;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the channels the event should broadcast on.
     * 
     * We broadcast to multiple channels to support different use cases:
     * - Vehicle-specific channel: For vehicle detail views
     * - Shipment-specific channel: For shipment tracking views
     * - Fleet-wide channel: For admin dashboard fleet overview
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Vehicle-specific channel (private)
        $channels[] = new PrivateChannel('tracking.vehicle.' . $this->vehicleId);

        // Shipment-specific channel (private) - only if shipment exists
        if ($this->shipmentId) {
            $channels[] = new PrivateChannel('tracking.shipment.' . $this->shipmentId);
        }

        // Fleet-wide channel (private) - for admin dashboard
        $channels[] = new PrivateChannel('tracking.fleet');

        return $channels;
    }

    /**
     * The event's broadcast name.
     * 
     * This is the event name that clients will listen for.
     */
    public function broadcastAs(): string
    {
        return 'tracking.state.updated';
    }

    /**
     * Get the data to broadcast.
     * 
     * This payload contains ONLY the data required by clients.
     * Internal models and sensitive data are NOT exposed.
     */
    public function broadcastWith(): array
    {
        return [
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
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Determine if the event should be broadcast synchronously.
     * 
     * For tracking updates, we want near-realtime delivery.
     * Using ShouldBroadcastNow ensures immediate dispatch without queueing.
     */
    public function broadcastNow(): bool
    {
        return true;
    }
}
