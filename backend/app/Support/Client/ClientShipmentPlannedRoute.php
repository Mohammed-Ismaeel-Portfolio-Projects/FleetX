<?php

namespace App\Support\Client;

use App\Simulation\Routing\Application\Services\PlannedRouteCache;

/**
 * Client-safe planned route geometry projection for shipment tracking (C5.5-C2.4).
 *
 * Read-only PlannedRouteCache → validate → sanitize → project coordinates only.
 * Never calls GetShipmentRouteAction / planners. Cache miss or unusable geometry → null.
 */
final class ClientShipmentPlannedRoute
{
    public function __construct(
        private readonly PlannedRouteCache $plannedRouteCache,
    ) {}

    /**
     * @return array{coordinates: list<array{latitude: float, longitude: float}>}|null
     */
    public function resolveForShipment(string $shipmentId): ?array
    {
        return $this->project($this->plannedRouteCache->get($shipmentId));
    }

    /**
     * @param  array<string, mixed>|null  $cached
     * @return array{coordinates: list<array{latitude: float, longitude: float}>}|null
     */
    public function project(?array $cached): ?array
    {
        if ($cached === null) {
            return null;
        }

        $raw = $this->extractRawCoordinates($cached);
        if ($raw === null) {
            return null;
        }

        $normalized = $this->normalizeCoordinateList($raw);
        if ($normalized === null) {
            return null;
        }

        return ['coordinates' => $normalized];
    }

    /**
     * Prefer top-level coordinates; fall back to nested api_payload geometry.
     *
     * @param  array<string, mixed>  $cached
     * @return list<mixed>|null
     */
    private function extractRawCoordinates(array $cached): ?array
    {
        $coords = $cached['coordinates'] ?? null;
        if (is_array($coords) && count($coords) >= 2) {
            return array_values($coords);
        }

        $nested = $cached['api_payload']['route']['geometry']['coordinates'] ?? null;
        if (is_array($nested) && count($nested) >= 2) {
            return array_values($nested);
        }

        return null;
    }

    /**
     * Fail-closed: every point must be valid; do not drop bad points and expose a shortened path.
     *
     * @param  list<mixed>  $coords
     * @return list<array{latitude: float, longitude: float}>|null
     */
    private function normalizeCoordinateList(array $coords): ?array
    {
        if (count($coords) < 2) {
            return null;
        }

        $normalized = [];

        foreach ($coords as $coord) {
            $pair = $this->normalizeCoordinatePair($coord);
            if ($pair === null) {
                return null;
            }

            $normalized[] = $pair;
        }

        if (count($normalized) < 2) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function normalizeCoordinatePair(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $latRaw = $value['latitude'] ?? $value['lat'] ?? null;
        $lngRaw = $value['longitude'] ?? $value['lon'] ?? $value['lng'] ?? null;

        $lat = $this->normalizeFiniteCoordinate($latRaw);
        $lng = $this->normalizeFiniteCoordinate($lngRaw);

        if ($lat === null || $lng === null) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    private function normalizeFiniteCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        if (! is_finite($float)) {
            return null;
        }

        return $float;
    }
}
