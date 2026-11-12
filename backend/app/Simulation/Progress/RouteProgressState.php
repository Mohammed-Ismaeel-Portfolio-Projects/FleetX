<?php

namespace App\Simulation\Progress;

/**
 * RouteProgressState
 *
 * Runtime/cache progress along a planned GraphHopper (or fallback) polyline.
 * Not a database model — serialized into cache between artisan ticks.
 */
class RouteProgressState
{
    public function __construct(
        public string $routeId,
        public string $shipmentId,
        public string $vehicleId,
        public int $polylineIndex,
        public int $segmentIndex,
        public float $distanceTravelledM,
        public float $remainingDistanceM,
        public float $completionPercentage,
        public float $currentLatitude,
        public float $currentLongitude,
        public float $heading,
        public bool $completed,
        public string $lastTick,
        public float $totalDistanceM,
        public string $provider = 'graphhopper',
    ) {}

    /**
     * @return array{
     *     latitude: float,
     *     longitude: float
     * }
     */
    public function currentCoordinate(): array
    {
        return [
            'latitude' => $this->currentLatitude,
            'longitude' => $this->currentLongitude,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'route_id' => $this->routeId,
            'shipment_id' => $this->shipmentId,
            'vehicle_id' => $this->vehicleId,
            'polyline_index' => $this->polylineIndex,
            'segment_index' => $this->segmentIndex,
            'distance_travelled_m' => $this->distanceTravelledM,
            'remaining_distance_m' => $this->remainingDistanceM,
            'completion_percentage' => $this->completionPercentage,
            'current_coordinate' => $this->currentCoordinate(),
            'heading' => $this->heading,
            'completed' => $this->completed,
            'last_tick' => $this->lastTick,
            'total_distance_m' => $this->totalDistanceM,
            'provider' => $this->provider,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $coord = is_array($data['current_coordinate'] ?? null) ? $data['current_coordinate'] : [];

        return new self(
            routeId: (string) ($data['route_id'] ?? ''),
            shipmentId: (string) ($data['shipment_id'] ?? ''),
            vehicleId: (string) ($data['vehicle_id'] ?? ''),
            polylineIndex: (int) ($data['polyline_index'] ?? 0),
            segmentIndex: (int) ($data['segment_index'] ?? 0),
            distanceTravelledM: (float) ($data['distance_travelled_m'] ?? 0),
            remainingDistanceM: (float) ($data['remaining_distance_m'] ?? 0),
            completionPercentage: (float) ($data['completion_percentage'] ?? 0),
            currentLatitude: (float) ($coord['latitude'] ?? $data['current_latitude'] ?? 0),
            currentLongitude: (float) ($coord['longitude'] ?? $data['current_longitude'] ?? 0),
            heading: (float) ($data['heading'] ?? 0),
            completed: (bool) ($data['completed'] ?? false),
            lastTick: (string) ($data['last_tick'] ?? now()->toIso8601String()),
            totalDistanceM: (float) ($data['total_distance_m'] ?? 0),
            provider: (string) ($data['provider'] ?? 'graphhopper'),
        );
    }
}
