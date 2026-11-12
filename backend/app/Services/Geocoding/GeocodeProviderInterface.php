<?php

namespace App\Services\Geocoding;

use App\Exceptions\GeocodingProviderException;
use App\Exceptions\GeocodingRateLimitException;

interface GeocodeProviderInterface
{
    /**
     * Forward-geocode a search query into location candidates.
     *
     * @return list<array{label: string|null, city: string|null, country: string|null, latitude: float, longitude: float}>
     *
     * @throws GeocodingRateLimitException
     * @throws GeocodingProviderException
     */
    public function search(string $query): array;

    /**
     * Reverse-geocode latitude and longitude coordinates into a location hint.
     *
     * @return array{label: string|null, city: string|null, country: string|null, latitude: float, longitude: float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array;
}
