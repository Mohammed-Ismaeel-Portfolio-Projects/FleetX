<?php

namespace App\Domain\Events;

use App\Events\BaseEvent;

/**
 * RouteDeviationDetected
 *
 * Pure domain event fired when a vehicle leaves its planned route polyline.
 * Broadcasting and rerouting are handled by application listeners.
 */
final class RouteDeviationDetected extends BaseEvent
{
    public function __construct(
        public readonly string $vehicleId,
        public readonly string $shipmentId,
        public readonly ?string $assignmentId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $deviationDistance,
        public readonly string $detectedAt,
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'tracking.route.deviation';
    }

    public function toArray(): array
    {
        return [
            'event_type' => $this->getEventType(),
            'vehicle_id' => $this->vehicleId,
            'shipment_id' => $this->shipmentId,
            'assignment_id' => $this->assignmentId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'deviation_distance' => $this->deviationDistance,
            'detected_at' => $this->detectedAt,
            'occurred_at' => $this->occurredAt->format(\DateTime::ATOM),
        ];
    }
}
