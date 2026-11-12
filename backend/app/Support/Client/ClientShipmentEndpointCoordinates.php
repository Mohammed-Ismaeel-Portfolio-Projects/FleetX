<?php

namespace App\Support\Client;

use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;

/**
 * Client-safe hub endpoint coordinates for shipment tracking (C5.5-C2.2).
 *
 * Precedence: PlannedRouteCache (read-only) → known EuropeanFleetLocations city.
 * Never calls GetShipmentRouteAction / route planning.
 * Unknown cities fail closed (null) — does not treat Berlin default as shipment location.
 *
 * Coordinates are logistics-hub centers, not street-level address GPS.
 */
final class ClientShipmentEndpointCoordinates
{
    public function __construct(
        private readonly PlannedRouteCache $plannedRouteCache,
        private readonly EuropeanFleetLocations $fleetLocations,
    ) {}

    /**
     * @return array{
     *     origin: array{latitude: float, longitude: float}|null,
     *     destination: array{latitude: float, longitude: float}|null
     * }
     */
    public function resolveForShipment(
        string $shipmentId,
        ?string $pickupCity,
        ?string $deliveryCity
    ): array {
        $cached = $this->plannedRouteCache->get($shipmentId);

        return [
            'origin' => $this->resolveOne(
                $this->cachedCoordinate($cached, 'origin'),
                $pickupCity
            ),
            'destination' => $this->resolveOne(
                $this->cachedCoordinate($cached, 'destination'),
                $deliveryCity
            ),
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float}|null  $cached
     * @return array{latitude: float, longitude: float}|null
     */
    private function resolveOne(?array $cached, ?string $city): ?array
    {
        if ($cached !== null) {
            return $cached;
        }

        return $this->knownCityCoordinates($city);
    }

    /**
     * @param  array<string, mixed>|null  $cached
     * @return array{latitude: float, longitude: float}|null
     */
    private function cachedCoordinate(?array $cached, string $endpoint): ?array
    {
        if ($cached === null) {
            return null;
        }

        $candidates = [
            $cached[$endpoint] ?? null,
            $cached['api_payload']['route'][$endpoint] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeCoordinatePair($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Known hub only — never uses EuropeanFleetLocations::resolveCity Berlin fallback.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function knownCityCoordinates(?string $city): ?array
    {
        if ($city === null) {
            return null;
        }

        $trimmed = trim($city);
        if ($trimmed === '') {
            return null;
        }

        $key = $this->fleetLocations->normalizeCityKey($trimmed);

        foreach ($this->fleetLocations->allLocations() as $location) {
            if ($this->fleetLocations->normalizeCityKey((string) $location['city']) !== $key) {
                continue;
            }

            return $this->normalizeCoordinatePair([
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
            ]);
        }

        return null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function normalizeCoordinatePair(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (! array_key_exists('latitude', $value) || ! array_key_exists('longitude', $value)) {
            return null;
        }

        $lat = $this->normalizeFiniteCoordinate($value['latitude']);
        $lng = $this->normalizeFiniteCoordinate($value['longitude']);

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
