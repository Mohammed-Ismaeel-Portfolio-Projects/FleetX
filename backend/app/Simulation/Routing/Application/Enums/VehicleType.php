<?php

namespace App\Simulation\Routing\Application\Enums;

/**
 * VehicleType Enum.
 * Represents vehicle types.
 */
enum VehicleType: string
{
    case CAR = 'car';
    case TRUCK = 'truck';
    case MOTORCYCLE = 'motorcycle';
    case BICYCLE = 'bicycle';
    case PEDESTRIAN = 'pedestrian';
}
