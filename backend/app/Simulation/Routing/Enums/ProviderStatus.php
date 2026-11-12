<?php

namespace App\Simulation\Routing\Enums;

/**
 * ProviderStatus
 *
 * Availability status for routing providers.
 */
enum ProviderStatus: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case DEGRADED = 'degraded';
}
