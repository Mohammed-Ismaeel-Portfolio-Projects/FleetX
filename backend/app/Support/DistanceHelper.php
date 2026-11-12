<?php

namespace App\Support;

/**
 * Distance Helper
 * 
 * Provides distance formatting and conversion utilities.
 */
class DistanceHelper
{
    /**
     * Format distance to display string.
     */
    public static function format(float $kilometers, string $unit = 'km'): string
    {
        return number_format($kilometers, 2) . ' ' . $unit;
    }

    /**
     * Convert kilometers to miles.
     */
    public static function kmToMiles(float $kilometers): float
    {
        return $kilometers * 0.621371;
    }

    /**
     * Convert miles to kilometers.
     */
    public static function milesToKm(float $miles): float
    {
        return $miles * 1.60934;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
