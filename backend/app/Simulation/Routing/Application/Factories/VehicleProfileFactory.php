<?php

namespace App\Simulation\Routing\Application\Factories;

use App\Models\Assignment;
use App\Models\Vehicle;
use App\Simulation\Routing\Application\Models\VehicleProfile;

/**
 * Builds routing VehicleProfile from existing domain Assignment/Vehicle data.
 */
class VehicleProfileFactory
{
    /**
     * Default EU heavy articulated freight envelope used when no vehicle is assigned.
     */
    public static function defaultTruck(): VehicleProfile
    {
        return VehicleProfile::truck(
            height: 4.0,
            width: 2.55,
            length: 16.5,
            weight: 40000,
            maxSpeed: 90,
            hasTrailer: true,
            freightClass: VehicleProfile::FREIGHT_HEAVY_TRUCK,
        );
    }

    /**
     * Resolve profile from an assignment (vehicle + optional trailer envelope).
     */
    public static function fromAssignment(?Assignment $assignment): VehicleProfile
    {
        if ($assignment === null || $assignment->vehicle === null) {
            return self::defaultTruck();
        }

        $vehicle = $assignment->vehicle;
        $trailer = $assignment->trailer;
        $hasTrailer = $trailer !== null;

        $freightClass = self::normalizeFreightClass(
            $vehicle->routing_class,
            (int) ($vehicle->gross_weight_kg ?? 0)
        );

        $defaults = self::defaultsForFreightClass($freightClass);

        $height = (float) ($vehicle->height_m ?? $defaults['height']);
        $width = (float) ($vehicle->width_m ?? $defaults['width']);
        $length = (float) ($vehicle->length_m ?? $defaults['length']);
        $weight = (float) ($vehicle->gross_weight_kg ?? $defaults['weight']);

        if ($trailer !== null) {
            $height = max($height, (float) ($trailer->height_m ?? 0));
            $width = max($width, (float) ($trailer->width_m ?? 0));
            if ($length <= 0 && $trailer->length_m) {
                $length = (float) $trailer->length_m;
            }
        }

        return VehicleProfile::truck(
            height: $height > 0 ? $height : $defaults['height'],
            width: $width > 0 ? $width : $defaults['width'],
            length: $length > 0 ? $length : $defaults['length'],
            weight: $weight > 0 ? $weight : $defaults['weight'],
            maxSpeed: 90,
            hasTrailer: $hasTrailer,
            freightClass: $freightClass,
        );
    }

    /**
     * Map Eloquent Vehicle to routing VehicleProfile.
     */
    public static function fromVehicle(Vehicle $vehicle, bool $hasTrailer = false): VehicleProfile
    {
        $freightClass = self::normalizeFreightClass(
            $vehicle->routing_class,
            (int) ($vehicle->gross_weight_kg ?? 0)
        );

        $defaults = self::defaultsForFreightClass($freightClass);

        return VehicleProfile::truck(
            height: (float) ($vehicle->height_m ?? $defaults['height']),
            width: (float) ($vehicle->width_m ?? $defaults['width']),
            length: (float) ($vehicle->length_m ?? $defaults['length']),
            weight: (float) ($vehicle->gross_weight_kg ?? $defaults['weight']),
            maxSpeed: 90,
            hasTrailer: $hasTrailer,
            freightClass: $freightClass,
        );
    }

    public static function normalizeFreightClass(?string $routingClass, int $weightKg = 0): string
    {
        $normalized = strtolower(trim((string) $routingClass));

        if (in_array($normalized, [
            VehicleProfile::FREIGHT_VAN,
            VehicleProfile::FREIGHT_LIGHT_TRUCK,
            VehicleProfile::FREIGHT_HEAVY_TRUCK,
        ], true)) {
            return $normalized;
        }

        if ($weightKg > 0 && $weightKg <= 3500) {
            return VehicleProfile::FREIGHT_VAN;
        }

        if ($weightKg > 3500 && $weightKg <= 12000) {
            return VehicleProfile::FREIGHT_LIGHT_TRUCK;
        }

        return VehicleProfile::FREIGHT_HEAVY_TRUCK;
    }

    /**
     * @return array{height: float, width: float, length: float, weight: float}
     */
    private static function defaultsForFreightClass(string $freightClass): array
    {
        return match ($freightClass) {
            VehicleProfile::FREIGHT_VAN => [
                'height' => 2.6,
                'width' => 2.1,
                'length' => 5.8,
                'weight' => 3200,
            ],
            VehicleProfile::FREIGHT_LIGHT_TRUCK => [
                'height' => 3.0,
                'width' => 2.35,
                'length' => 8.2,
                'weight' => 7500,
            ],
            default => [
                'height' => 4.0,
                'width' => 2.55,
                'length' => 16.5,
                'weight' => 40000,
            ],
        };
    }
}
