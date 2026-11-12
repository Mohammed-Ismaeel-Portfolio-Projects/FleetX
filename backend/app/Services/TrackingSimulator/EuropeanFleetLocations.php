<?php

namespace App\Services\TrackingSimulator;

/**
 * EuropeanFleetLocations
 *
 * Centralized EU logistics hub registry + land/EU position validation.
 * Simulation and route geocoding must use this service — never random global coords.
 */
class EuropeanFleetLocations
{
    /**
     * @return array<int, array{country: string, city: string, latitude: float, longitude: float}>
     */
    public function allLocations(): array
    {
        return config('european_fleet.locations', []);
    }

    /**
     * @return array<int, string>
     */
    public function cityNames(): array
    {
        return array_values(array_unique(array_map(
            fn (array $location) => $location['city'],
            $this->allLocations()
        )));
    }

    public function normalizeCityKey(string $city): string
    {
        $city = strtolower(trim($city));
        // Support "Berlin, DE" / "Berlin DE" style labels
        if (str_contains($city, ',')) {
            $city = trim(explode(',', $city, 2)[0]);
        }
        $city = str_replace([' ', '-'], '_', $city);

        // Common aliases
        $aliases = [
            'kraków' => 'krakow',
            'cracow' => 'krakow',
            'wrocław' => 'wroclaw',
            'dusseldorf' => 'düsseldorf',
            'duesseldorf' => 'düsseldorf',
            'malmö' => 'malmo',
            'köln' => 'cologne',
            'koln' => 'cologne',
        ];

        return $aliases[$city] ?? $city;
    }

    /**
     * Resolve a city name to a location entry (defaults to Berlin).
     *
     * @return array{country: string, city: string, latitude: float, longitude: float}
     */
    public function resolveCity(string $city): array
    {
        $key = $this->normalizeCityKey($city);

        foreach ($this->allLocations() as $location) {
            if ($this->normalizeCityKey($location['city']) === $key) {
                return $location;
            }
        }

        return $this->defaultLocation();
    }

    /**
     * @return array{country: string, city: string, latitude: float, longitude: float}
     */
    public function defaultLocation(): array
    {
        return $this->resolveCity('Damascus');
    }

    /**
     * Pick a logistics facility coordinate for a city (land-only whitelist).
     *
     * @return array{latitude: float, longitude: float, city: string, country: string}
     */
    public function pickLogisticsPointForCity(string $city): array
    {
        $location = $this->resolveCity($city);
        $offsets = config('european_fleet.facility_offsets', [['lat' => 0.0, 'lng' => 0.0]]);
        $offset = $offsets[array_rand($offsets)];

        $point = [
            'latitude' => round($location['latitude'] + (float) $offset['lat'], 7),
            'longitude' => round($location['longitude'] + (float) $offset['lng'], 7),
            'city' => $location['city'],
            'country' => $location['country'],
        ];

        // Facility offsets are tiny and hub-centered; return directly (no recursion risk)
        return $point;
    }

    /**
     * Pick a hub using configured country distribution weights.
     *
     * @return array{latitude: float, longitude: float, city: string, country: string}
     */
    public function pickDistributedLogisticsPoint(): array
    {
        $country = $this->pickWeightedCountry();
        $cities = array_values(array_filter(
            $this->allLocations(),
            fn (array $location) => $location['country'] === $country
        ));

        if (empty($cities)) {
            $cities = $this->allLocations();
        }

        $hub = $cities[array_rand($cities)];

        return $this->pickLogisticsPointForCity($hub['city']);
    }

    public function pickWeightedCountry(): string
    {
        $weights = config('european_fleet.country_weights', ['Germany' => 100]);
        $total = array_sum($weights);
        $roll = mt_rand(1, max(1, (int) $total));
        $cumulative = 0;

        foreach ($weights as $country => $weight) {
            $cumulative += (int) $weight;
            if ($roll <= $cumulative) {
                return $country;
            }
        }

        return array_key_first($weights) ?: 'Germany';
    }

    /**
     * Pick a different nearby/reachable EU hub for idle fleet repositioning.
     *
     * @return array{latitude: float, longitude: float, city: string, country: string}
     */
    public function pickNearbyDestination(string $fromCity): array
    {
        $fromKey = $this->normalizeCityKey($fromCity);
        $neighbors = config('european_fleet.land_neighbors.' . $fromKey, []);

        if (!empty($neighbors)) {
            $targetKey = $neighbors[array_rand($neighbors)];
            foreach ($this->allLocations() as $location) {
                if ($this->normalizeCityKey($location['city']) === $targetKey) {
                    return $this->pickLogisticsPointForCity($location['city']);
                }
            }
        }

        return $this->pickDistributedLogisticsPoint();
    }

    /**
     * Build a land-corridor waypoint path between two cities (BFS on neighbor graph).
     *
     * @return array<int, array{latitude: float, longitude: float, city: string, country: string}>
     */
    public function buildLandPath(string $fromCity, string $toCity): array
    {
        $fromKey = $this->normalizeCityKey($fromCity);
        $toKey = $this->normalizeCityKey($toCity);

        if ($fromKey === $toKey) {
            $point = $this->pickLogisticsPointForCity($fromCity);
            return [$point];
        }

        $neighbors = config('european_fleet.land_neighbors', []);
        $queue = [$fromKey];
        $visited = [$fromKey => true];
        $parent = [$fromKey => null];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current === $toKey) {
                break;
            }

            foreach ($neighbors[$current] ?? [] as $next) {
                if (isset($visited[$next])) {
                    continue;
                }
                // Only walk to known hubs
                if (!$this->hasCityKey($next)) {
                    continue;
                }
                $visited[$next] = true;
                $parent[$next] = $current;
                $queue[] = $next;
            }
        }

        if (!isset($parent[$toKey]) && $fromKey !== $toKey) {
            // No graph path — fall back to from → to direct hubs (validation will snap)
            return [
                $this->pickLogisticsPointForCity($fromCity),
                $this->pickLogisticsPointForCity($toCity),
            ];
        }

        $keys = [];
        $cursor = $toKey;
        while ($cursor !== null) {
            array_unshift($keys, $cursor);
            $cursor = $parent[$cursor] ?? null;
            if ($cursor === null && ($keys[0] ?? null) !== $fromKey) {
                break;
            }
        }

        if (($keys[0] ?? null) !== $fromKey) {
            $keys = [$fromKey, $toKey];
        }

        $path = [];
        foreach ($keys as $key) {
            $path[] = $this->pickLogisticsPointForCity($key);
        }

        return $path;
    }

    public function hasCityKey(string $cityKey): bool
    {
        foreach ($this->allLocations() as $location) {
            if ($this->normalizeCityKey($location['city']) === $cityKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that a coordinate is inside EU operational land territory.
     * Proximity to a known logistics hub is the primary land guarantee.
     */
    public function validateVehicleLocation(float $latitude, float $longitude): bool
    {
        $nearest = $this->nearestHub($latitude, $longitude);
        $maxKm = (float) config('european_fleet.max_hub_distance_km', 45);

        // Primary rule: near a logistics hub = land/EU ops territory
        if ($nearest['distance_km'] <= $maxKm) {
            return true;
        }

        $bounds = config('european_fleet.eu_bounds');
        if (
            $latitude < $bounds['min_lat'] || $latitude > $bounds['max_lat'] ||
            $longitude < $bounds['min_lng'] || $longitude > $bounds['max_lng']
        ) {
            return false;
        }

        foreach (config('european_fleet.water_exclusion_zones', []) as $zone) {
            if (
                $latitude >= $zone['min_lat'] && $latitude <= $zone['max_lat'] &&
                $longitude >= $zone['min_lng'] && $longitude <= $zone['max_lng']
            ) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return array{latitude: float, longitude: float, city: string, country: string}
     */
    public function ensureValidPosition(float $latitude, float $longitude, ?string $preferredCity = null): array
    {
        if ($this->validateVehicleLocation($latitude, $longitude)) {
            $hub = $this->nearestHub($latitude, $longitude);
            return [
                'latitude' => round($latitude, 7),
                'longitude' => round($longitude, 7),
                'city' => $hub['city'],
                'country' => $hub['country'],
            ];
        }

        // Snap to hub center — do NOT call pickLogisticsPointForCity (avoids recursion)
        if ($preferredCity) {
            $hub = $this->resolveCity($preferredCity);
        } else {
            $nearest = $this->nearestHub($latitude, $longitude);
            $hub = [
                'city' => $nearest['city'],
                'country' => $nearest['country'],
                'latitude' => $nearest['latitude'],
                'longitude' => $nearest['longitude'],
            ];
        }

        return [
            'latitude' => round((float) $hub['latitude'], 7),
            'longitude' => round((float) $hub['longitude'], 7),
            'city' => $hub['city'],
            'country' => $hub['country'],
        ];
    }

    /**
     * @return array{city: string, country: string, latitude: float, longitude: float, distance_km: float}
     */
    public function nearestHub(float $latitude, float $longitude): array
    {
        $best = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($this->allLocations() as $location) {
            $distance = $this->haversineKm(
                $latitude,
                $longitude,
                (float) $location['latitude'],
                (float) $location['longitude']
            );

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $location;
            }
        }

        $best ??= $this->defaultLocation();

        return [
            'city' => $best['city'],
            'country' => $best['country'],
            'latitude' => (float) $best['latitude'],
            'longitude' => (float) $best['longitude'],
            'distance_km' => $bestDistance === PHP_FLOAT_MAX ? 0.0 : $bestDistance,
        ];
    }

    /**
     * Interpolate a position along a land path (hub-to-hub), keeping results on land.
     *
     * @param array<int, array{latitude: float, longitude: float}> $path
     * @return array{latitude: float, longitude: float, heading: float, arrived: bool, path_index: int}
     */
    public function advanceAlongLandPath(
        array $path,
        float $currentLat,
        float $currentLon,
        float $speedKmh,
        int $secondsElapsed,
        int $pathIndex = 0,
        float $arrivalThresholdMeters = 100.0
    ): array {
        if (count($path) < 1) {
            $fallback = $this->defaultLocation();
            return [
                'latitude' => $fallback['latitude'],
                'longitude' => $fallback['longitude'],
                'heading' => 0.0,
                'arrived' => true,
                'path_index' => 0,
            ];
        }

        $pathIndex = max(0, min($pathIndex, count($path) - 1));
        $target = $path[$pathIndex];
        $toLat = (float) $target['latitude'];
        $toLon = (float) $target['longitude'];

        $distanceMeters = $this->haversineKm($currentLat, $currentLon, $toLat, $toLon) * 1000;
        $bearing = $this->bearingDegrees($currentLat, $currentLon, $toLat, $toLon);

        if ($distanceMeters <= $arrivalThresholdMeters) {
            $valid = $this->ensureValidPosition($currentLat, $currentLon, $target['city'] ?? null);
            if ($pathIndex >= count($path) - 1) {
                return [
                    'latitude' => $valid['latitude'],
                    'longitude' => $valid['longitude'],
                    'heading' => $bearing,
                    'arrived' => true,
                    'path_index' => $pathIndex,
                ];
            }

            return [
                'latitude' => $valid['latitude'],
                'longitude' => $valid['longitude'],
                'heading' => $bearing,
                'arrived' => false,
                'path_index' => $pathIndex + 1,
            ];
        }

        $distanceToTravel = ($speedKmh * 1000 / 3600) * $secondsElapsed;
        if ($distanceToTravel >= $distanceMeters - $arrivalThresholdMeters) {
            $valid = $this->ensureValidPosition($toLat, $toLon, $target['city'] ?? null);
            $arrived = $pathIndex >= count($path) - 1;
            return [
                'latitude' => $valid['latitude'],
                'longitude' => $valid['longitude'],
                'heading' => $bearing,
                'arrived' => $arrived,
                'path_index' => $arrived ? $pathIndex : $pathIndex + 1,
            ];
        }

        $next = $this->destinationPoint($currentLat, $currentLon, $bearing, $distanceToTravel);
        $valid = $this->ensureValidPosition($next['latitude'], $next['longitude'], $target['city'] ?? null);

        return [
            'latitude' => $valid['latitude'],
            'longitude' => $valid['longitude'],
            'heading' => $bearing,
            'arrived' => false,
            'path_index' => $pathIndex,
        ];
    }

    private function bearingDegrees(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);
        $x = sin($deltaLon) * cos($lat2Rad);
        $y = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($deltaLon);
        return fmod(rad2deg(atan2($x, $y)) + 360, 360);
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private function destinationPoint(float $lat, float $lon, float $bearing, float $distanceMeters): array
    {
        $earthRadius = 6371000;
        $latRad = deg2rad($lat);
        $lonRad = deg2rad($lon);
        $bearingRad = deg2rad($bearing);
        $angularDistance = $distanceMeters / $earthRadius;

        $lat2Rad = asin(
            sin($latRad) * cos($angularDistance) +
            cos($latRad) * sin($angularDistance) * cos($bearingRad)
        );
        $lon2Rad = $lonRad + atan2(
            sin($bearingRad) * sin($angularDistance) * cos($latRad),
            cos($angularDistance) - sin($latRad) * sin($lat2Rad)
        );

        return [
            'latitude' => rad2deg($lat2Rad),
            'longitude' => rad2deg($lon2Rad),
        ];
    }

    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Pair of distinct EU cities for a realistic shipment route.
     *
     * @return array{pickup_city: string, pickup_country: string, delivery_city: string, delivery_country: string}
     */
    public function randomShipmentPair(): array
    {
        $origin = $this->pickDistributedLogisticsPoint();
        $destination = $this->pickNearbyDestination($origin['city']);

        // Prefer a longer corridor sometimes for variety
        if (mt_rand(0, 100) < 40) {
            $destination = $this->pickDistributedLogisticsPoint();
            if ($destination['city'] === $origin['city']) {
                $destination = $this->pickNearbyDestination($origin['city']);
            }
        }

        return [
            'pickup_city' => $origin['city'],
            'pickup_country' => $origin['country'],
            'delivery_city' => $destination['city'],
            'delivery_country' => $destination['country'],
        ];
    }
}
