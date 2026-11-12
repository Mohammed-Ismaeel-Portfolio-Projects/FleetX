<?php

namespace App\Simulation\Journey;

/**
 * Deterministic route intelligence plan for one shipment (cache-persisted).
 */
final class JourneyPlan
{
    /**
     * @param list<RestStopWaypoint> $restStops
     */
    public function __construct(
        public readonly string $shipmentId,
        public readonly float $totalDistanceM,
        public readonly float $maxDrivingDistanceM,
        public readonly int $restDurationSeconds,
        public readonly float $estimatedTravelSeconds,
        public array $restStops = [],
        public bool $isResting = false,
        public ?string $activeRestStopId = null,
        public ?string $restStartedAt = null,
        public float $drivingSinceRestM = 0.0,
        public ?string $plannedAt = null,
    ) {}

    public function nextIncompleteStop(): ?RestStopWaypoint
    {
        foreach ($this->restStops as $stop) {
            if ($stop->status !== RestStopStatus::Completed) {
                return $stop;
            }
        }

        return null;
    }

    public function findStop(string $id): ?RestStopWaypoint
    {
        foreach ($this->restStops as $stop) {
            if ($stop->id === $id) {
                return $stop;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shipment_id' => $this->shipmentId,
            'total_distance_m' => $this->totalDistanceM,
            'max_driving_distance_m' => $this->maxDrivingDistanceM,
            'rest_duration_seconds' => $this->restDurationSeconds,
            'estimated_travel_seconds' => $this->estimatedTravelSeconds,
            'rest_stops' => array_map(static fn (RestStopWaypoint $s) => $s->toArray(), $this->restStops),
            'is_resting' => $this->isResting,
            'active_rest_stop_id' => $this->activeRestStopId,
            'rest_started_at' => $this->restStartedAt,
            'driving_since_rest_m' => $this->drivingSinceRestM,
            'planned_at' => $this->plannedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $stops = [];
        foreach ($data['rest_stops'] ?? [] as $row) {
            if (is_array($row)) {
                $stops[] = RestStopWaypoint::fromArray($row);
            }
        }

        return new self(
            shipmentId: (string) ($data['shipment_id'] ?? ''),
            totalDistanceM: (float) ($data['total_distance_m'] ?? 0),
            maxDrivingDistanceM: (float) ($data['max_driving_distance_m'] ?? 0),
            restDurationSeconds: (int) ($data['rest_duration_seconds'] ?? 0),
            estimatedTravelSeconds: (float) ($data['estimated_travel_seconds'] ?? 0),
            restStops: $stops,
            isResting: (bool) ($data['is_resting'] ?? false),
            activeRestStopId: isset($data['active_rest_stop_id']) ? (string) $data['active_rest_stop_id'] : null,
            restStartedAt: isset($data['rest_started_at']) ? (string) $data['rest_started_at'] : null,
            drivingSinceRestM: (float) ($data['driving_since_rest_m'] ?? 0),
            plannedAt: isset($data['planned_at']) ? (string) $data['planned_at'] : null,
        );
    }
}
