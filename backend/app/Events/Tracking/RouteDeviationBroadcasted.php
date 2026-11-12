<?php

namespace App\Events\Tracking;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast-only event for route deviation alerts.
 */
final class RouteDeviationBroadcasted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $vehicleId,
        public readonly string $shipmentId,
        public readonly ?string $assignmentId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $deviationDistance,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tracking.vehicle.' . $this->vehicleId),
            new PrivateChannel('tracking.shipment.' . $this->shipmentId),
            new PrivateChannel('tracking.fleet'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tracking.route.deviation';
    }

    public function broadcastWith(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'shipment_id' => $this->shipmentId,
            'assignment_id' => $this->assignmentId,
            'deviation_distance' => round($this->deviationDistance, 2),
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'timestamp' => $this->timestamp,
        ];
    }

    public function broadcastNow(): bool
    {
        return true;
    }
}
