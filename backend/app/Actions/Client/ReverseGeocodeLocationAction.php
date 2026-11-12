<?php

namespace App\Actions\Client;

use App\Core\ApiResult;
use App\Services\Geocoding\GraphHopperGeocodingClient;

/**
 * Reverse-geocode a confirmed map coordinate. Failure still returns the coordinate.
 */
class ReverseGeocodeLocationAction
{
    public function __construct(private GraphHopperGeocodingClient $geocoding)
    {
    }

    public function execute(float $latitude, float $longitude): ApiResult
    {
        $result = $this->geocoding->reverse($latitude, $longitude) ?? [
            'label' => null,
            'city' => null,
            'country' => null,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        return new ApiResult(true, $result, 'Location resolved');
    }
}
