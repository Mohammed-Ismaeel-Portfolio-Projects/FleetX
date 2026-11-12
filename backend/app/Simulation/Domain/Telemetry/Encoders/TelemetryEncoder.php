<?php

namespace App\Simulation\Domain\Telemetry\Encoders;

use App\Simulation\Domain\Telemetry\DTOs\TelemetrySignalCollection;
use App\Simulation\Domain\Telemetry\DTOs\TelemetryPacket;

/**
 * TelemetryEncoder Interface.
 * Defines contract for encoding telemetry signal collections into packets.
 */
interface TelemetryEncoder
{
    /**
     * Encode telemetry signal collection into telemetry packet.
     */
    public function encode(TelemetrySignalCollection $collection): TelemetryPacket;

    /**
     * Get encoding format name.
     */
    public function getFormat(): string;
}
