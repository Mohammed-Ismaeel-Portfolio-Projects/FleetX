<?php

namespace App\Simulation\Routing\Enums;

/**
 * TravelMode Enum.
 * Represents the mode of travel.
 */
enum TravelMode: string
{
    case DRIVING = 'driving';
    case CYCLING = 'cycling';
    case WALKING = 'walking';
    case TRANSIT = 'transit';
    case TRUCKING = 'trucking';
}
