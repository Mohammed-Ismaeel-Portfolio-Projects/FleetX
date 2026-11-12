<?php

namespace App\Simulation\Domain\Telemetry\DTOs;

/**
 * TelemetryPacket DTO.
 * Encoded telemetry data ready for publishing.
 * Contains the encoded payload and metadata.
 */
readonly class TelemetryPacket
{
    public function __construct(
        public string $vehicleId,
        public string $encodedData,
        public string $encodingFormat,
        public \DateTime $timestamp,
        public int $packetId,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'encoded_data' => $this->encodedData,
            'encoding_format' => $this->encodingFormat,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'packet_id' => $this->packetId,
        ];
    }
}
