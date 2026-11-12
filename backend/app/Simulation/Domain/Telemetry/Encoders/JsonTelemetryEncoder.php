<?php

namespace App\Simulation\Domain\Telemetry\Encoders;

use App\Simulation\Domain\Telemetry\DTOs\TelemetrySignalCollection;
use App\Simulation\Domain\Telemetry\DTOs\TelemetryPacket;

/**
 * JsonTelemetryEncoder.
 * Encodes telemetry signal collections into JSON format packets.
 */
class JsonTelemetryEncoder implements TelemetryEncoder
{
    private static int $packetCounter = 0;

    public function encode(TelemetrySignalCollection $collection): TelemetryPacket
    {
        $encodedData = json_encode($collection->toArray());
        
        return new TelemetryPacket(
            vehicleId: $collection->vehicleId,
            encodedData: $encodedData,
            encodingFormat: $this->getFormat(),
            timestamp: new \DateTime(),
            packetId: ++self::$packetCounter,
        );
    }

    public function getFormat(): string
    {
        return 'json';
    }
}
