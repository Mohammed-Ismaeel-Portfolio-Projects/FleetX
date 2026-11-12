<?php

namespace App\Services\TrackingSimulator;

class VehicleMovementService
{
    public function __construct(
        private EuropeanFleetLocations $fleetLocations
    ) {}

    /**
     * Calculate the next GPS position for a vehicle moving toward destination.
     *
     * @return array{latitude: float, longitude: float, heading: float, arrived: bool}
     */
    public function calculateNextPosition(
        float $fromLat,
        float $fromLon,
        float $toLat,
        float $toLon,
        float $speedKmh,
        int $secondsElapsed = 60,
        float $arrivalThresholdMeters = 100.0
    ): array {
        $distanceToDestination = $this->calculateDistance($fromLat, $fromLon, $toLat, $toLon);

        if ($distanceToDestination <= $arrivalThresholdMeters) {
            $safe = $this->fleetLocations->ensureValidPosition($fromLat, $fromLon);
            return [
                'latitude' => $safe['latitude'],
                'longitude' => $safe['longitude'],
                'heading' => $this->calculateBearing($fromLat, $fromLon, $toLat, $toLon),
                'arrived' => true,
            ];
        }

        $distanceToTravel = ($speedKmh * 1000 / 3600) * $secondsElapsed;

        if ($distanceToTravel >= $distanceToDestination - $arrivalThresholdMeters) {
            $safe = $this->fleetLocations->ensureValidPosition($toLat, $toLon);
            return [
                'latitude' => $safe['latitude'],
                'longitude' => $safe['longitude'],
                'heading' => $this->calculateBearing($fromLat, $fromLon, $toLat, $toLon),
                'arrived' => true,
            ];
        }

        $bearing = $this->calculateBearing($fromLat, $fromLon, $toLat, $toLon);
        $nextPosition = $this->calculateDestinationPoint(
            $fromLat,
            $fromLon,
            $bearing,
            $distanceToTravel
        );

        $safe = $this->fleetLocations->ensureValidPosition(
            $nextPosition['latitude'],
            $nextPosition['longitude']
        );

        return [
            'latitude' => $safe['latitude'],
            'longitude' => $safe['longitude'],
            'heading' => $bearing,
            'arrived' => false,
        ];
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function calculateBearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);

        $x = sin($deltaLon) * cos($lat2Rad);
        $y = cos($lat1Rad) * sin($lat2Rad) -
             sin($lat1Rad) * cos($lat2Rad) * cos($deltaLon);

        $bearing = atan2($x, $y);
        $bearing = rad2deg($bearing);
        $bearing = ($bearing + 360) % 360;

        return $bearing;
    }

    private function calculateDestinationPoint(
        float $lat,
        float $lon,
        float $bearing,
        float $distance
    ): array {
        $earthRadius = 6371000;

        $latRad = deg2rad($lat);
        $lonRad = deg2rad($lon);
        $bearingRad = deg2rad($bearing);
        $angularDistance = $distance / $earthRadius;

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

    /**
     * Generate EU logistics coordinates for a city.
     * Never returns ocean / non-EU coordinates.
     *
     * @return array{latitude: float, longitude: float}
     */
    public function generateRandomCoordinates(string $city): array
    {
        $point = $this->fleetLocations->pickLogisticsPointForCity($city);

        return [
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
        ];
    }

    /**
     * Generate a distributed EU fleet position (country-weighted hubs).
     *
     * @return array{latitude: float, longitude: float, city: string, country: string}
     */
    public function generateDistributedCoordinates(): array
    {
        return $this->fleetLocations->pickDistributedLogisticsPoint();
    }
}
