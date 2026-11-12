<?php

namespace App\Simulation\Journey;

/**
 * One planned rest stop along a route (distance offset + optional coordinates).
 */
final class RestStopWaypoint
{
    public function __construct(
        public readonly string $id,
        public readonly int $sequence,
        public readonly float $routeOffsetM,
        public readonly int $durationSeconds,
        public readonly string $reason,
        public RestStopStatus $status = RestStopStatus::Planned,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $restStartedAt = null,
        public ?string $restCompletedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'route_offset_m' => $this->routeOffsetM,
            'duration_seconds' => $this->durationSeconds,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rest_started_at' => $this->restStartedAt,
            'rest_completed_at' => $this->restCompletedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            sequence: (int) ($data['sequence'] ?? 0),
            routeOffsetM: (float) ($data['route_offset_m'] ?? 0),
            durationSeconds: (int) ($data['duration_seconds'] ?? 0),
            reason: (string) ($data['reason'] ?? 'max_driving_distance'),
            status: RestStopStatus::tryFrom((string) ($data['status'] ?? 'planned'))
                ?? RestStopStatus::Planned,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            restStartedAt: isset($data['rest_started_at']) ? (string) $data['rest_started_at'] : null,
            restCompletedAt: isset($data['rest_completed_at']) ? (string) $data['rest_completed_at'] : null,
        );
    }
}
