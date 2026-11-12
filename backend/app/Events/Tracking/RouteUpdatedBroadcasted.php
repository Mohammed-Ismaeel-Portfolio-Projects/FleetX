<?php

namespace App\Events\Tracking;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast-only event dispatched when an active shipment route is dynamically recalculating / rerouted.
 */
final class RouteUpdatedBroadcasted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $shipmentId,
        public readonly ?string $vehicleId,
        public readonly ?string $assignmentId,
        public readonly array $routePayload,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('tracking.shipment.' . $this->shipmentId),
            new PrivateChannel('tracking.fleet'),
        ];

        if ($this->vehicleId !== null && $this->vehicleId !== '') {
            $channels[] = new PrivateChannel('tracking.vehicle.' . $this->vehicleId);
        }

        if ($this->assignmentId !== null && $this->assignmentId !== '') {
            $channels[] = new PrivateChannel('driver.assignment.' . $this->assignmentId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'tracking.route.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'shipment_id' => $this->shipmentId,
            'vehicle_id' => $this->vehicleId,
            'assignment_id' => $this->assignmentId,
            'route' => $this->routePayload['route'] ?? null,
            'metadata' => array_merge(
                is_array($this->routePayload['metadata'] ?? null) ? $this->routePayload['metadata'] : [],
                [
                    'is_reroute' => true,
                    'broadcast_at' => $this->timestamp,
                ]
            ),
        ];
    }

    public function broadcastNow(): bool
    {
        return true;
    }
}
