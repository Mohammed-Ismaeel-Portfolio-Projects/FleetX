<?php

namespace App\Simulation\Domain\Telemetry\Events;

/**
 * TelemetryPublished Event.
 * Published when telemetry data is successfully published.
 */
readonly class TelemetryPublished
{
    public function __construct(
        public string $vehicleId,
        public string $encodingFormat,
        public \DateTime $timestamp,
        public int $packetId,
    ) {}

    /**
     * Create from telemetry packet.
     */
    public static function fromPacket(array $packetData): self
    {
        return new self(
            vehicleId: $packetData['vehicle_id'],
            encodingFormat: $packetData['encoding_format'],
            timestamp: new \DateTime($packetData['timestamp']),
            packetId: $packetData['packet_id'],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'encoding_format' => $this->encodingFormat,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'packet_id' => $this->packetId,
        ];
    }
}
