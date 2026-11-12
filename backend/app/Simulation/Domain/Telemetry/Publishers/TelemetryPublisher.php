<?php

namespace App\Simulation\Domain\Telemetry\Publishers;

use App\Simulation\Domain\Telemetry\DTOs\TelemetryPacket;

/**
 * TelemetryPublisher Interface.
 * Defines contract for publishing telemetry packets.
 */
interface TelemetryPublisher
{
    /**
     * Publish telemetry packet.
     */
    public function publish(TelemetryPacket $packet): bool;

    /**
     * Get publisher name.
     */
    public function getName(): string;
}
