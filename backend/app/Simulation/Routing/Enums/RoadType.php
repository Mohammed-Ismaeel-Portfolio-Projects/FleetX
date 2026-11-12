<?php

namespace App\Simulation\Routing\Enums;

/**
 * RoadType Enum.
 * Represents the type of road.
 */
enum RoadType: string
{
    case MOTORWAY = 'motorway';
    case TRUNK = 'trunk';
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case TERTIARY = 'tertiary';
    case RESIDENTIAL = 'residential';
    case SERVICE = 'service';
    case UNPAVED = 'unpaved';
    case FERRY = 'ferry';
    case UNKNOWN = 'unknown';
}
