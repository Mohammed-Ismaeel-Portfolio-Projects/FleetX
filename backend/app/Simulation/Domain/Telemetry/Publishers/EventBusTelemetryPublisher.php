<?php

namespace App\Simulation\Domain\Telemetry\Publishers;

use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\Telemetry\DTOs\TelemetryPacket;

/**
 * EventBusTelemetryPublisher.
 * Publishes telemetry packets through the EventBus.
 */
class EventBusTelemetryPublisher implements TelemetryPublisher
{
    public function __construct(
        private EventBusInterface $eventBus,
    ) {}

    public function publish(TelemetryPacket $packet): bool
    {
        try {
            $this->eventBus->publish('telemetry.published', $packet->toArray());
            return true;
        } catch (\Exception $e) {
            // Log error, return false
            return false;
        }
    }

    public function getName(): string
    {
        return 'event_bus_publisher';
    }
}
