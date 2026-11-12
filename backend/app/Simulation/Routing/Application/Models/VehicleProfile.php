<?php

namespace App\Simulation\Routing\Application\Models;

use App\Simulation\Routing\Application\Enums\VehicleType;
use App\Simulation\Routing\Enums\TravelMode;

/**
 * VehicleProfile Model.
 * Represents vehicle characteristics for routing.
 */
readonly class VehicleProfile
{
    public const FREIGHT_VAN = 'van';
    public const FREIGHT_LIGHT_TRUCK = 'light_truck';
    public const FREIGHT_HEAVY_TRUCK = 'heavy_truck';

    public function __construct(
        public VehicleType $vehicleType,
        public ?float $height, // meters
        public ?float $width, // meters
        public ?float $length, // meters
        public ?float $weight, // kilograms
        public ?float $maxSpeed, // km/h
        public ?string $fuelType,
        public bool $hasTrailer,
        public ?string $freightClass = null, // van|light_truck|heavy_truck
    ) {}

    /**
     * Create car profile.
     */
    public static function car(
        ?float $maxSpeed = null,
        ?string $fuelType = null,
    ): self {
        return new self(
            vehicleType: VehicleType::CAR,
            height: 1.5,
            width: 1.8,
            length: 4.5,
            weight: 1500,
            maxSpeed: $maxSpeed,
            fuelType: $fuelType,
            hasTrailer: false,
            freightClass: null,
        );
    }

    /**
     * Create truck profile.
     */
    public static function truck(
        float $height,
        float $width,
        float $length,
        float $weight,
        ?float $maxSpeed = null,
        bool $hasTrailer = false,
        ?string $freightClass = self::FREIGHT_HEAVY_TRUCK,
    ): self {
        return new self(
            vehicleType: VehicleType::TRUCK,
            height: $height,
            width: $width,
            length: $length,
            weight: $weight,
            maxSpeed: $maxSpeed,
            fuelType: 'diesel',
            hasTrailer: $hasTrailer,
            freightClass: $freightClass ?? self::FREIGHT_HEAVY_TRUCK,
        );
    }

    /**
     * Create motorcycle profile.
     */
    public static function motorcycle(
        ?float $maxSpeed = null,
        ?string $fuelType = null,
    ): self {
        return new self(
            vehicleType: VehicleType::MOTORCYCLE,
            height: 1.2,
            width: 0.8,
            length: 2.2,
            weight: 200,
            maxSpeed: $maxSpeed,
            fuelType: $fuelType,
            hasTrailer: false,
            freightClass: null,
        );
    }

    /**
     * Create bicycle profile.
     */
    public static function bicycle(): self
    {
        return new self(
            vehicleType: VehicleType::BICYCLE,
            height: 1.0,
            width: 0.6,
            length: 1.8,
            weight: 15,
            maxSpeed: 25,
            fuelType: null,
            hasTrailer: false,
            freightClass: null,
        );
    }

    /**
     * Create pedestrian profile.
     */
    public static function pedestrian(): self
    {
        return new self(
            vehicleType: VehicleType::PEDESTRIAN,
            height: 1.7,
            width: 0.6,
            length: 0.3,
            weight: 70,
            maxSpeed: 5,
            fuelType: null,
            hasTrailer: false,
            freightClass: null,
        );
    }

    /**
     * Get recommended travel mode.
     */
    public function getRecommendedTravelMode(): TravelMode
    {
        return match ($this->vehicleType) {
            VehicleType::CAR, VehicleType::TRUCK => TravelMode::DRIVING,
            VehicleType::MOTORCYCLE => TravelMode::DRIVING,
            VehicleType::BICYCLE => TravelMode::CYCLING,
            VehicleType::PEDESTRIAN => TravelMode::WALKING,
            default => TravelMode::DRIVING,
        };
    }

    /**
     * Check if vehicle is large (requires special routing).
     */
    public function isLargeVehicle(): bool
    {
        return $this->vehicleType === VehicleType::TRUCK
            || ($this->height !== null && $this->height > 3.0)
            || ($this->weight !== null && $this->weight > 3500);
    }

    /**
     * Check if vehicle has hazardous cargo (placeholder).
     */
    public function hasHazardousCargo(): bool
    {
        return false;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'vehicle_type' => $this->vehicleType->value,
            'freight_class' => $this->freightClass,
            'type' => $this->freightClass ?? $this->vehicleType->value,
            'height' => $this->height,
            'width' => $this->width,
            'length' => $this->length,
            'weight' => $this->weight,
            'max_speed' => $this->maxSpeed,
            'fuel_type' => $this->fuelType,
            'has_trailer' => $this->hasTrailer,
        ];
    }
}
