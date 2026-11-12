<?php

namespace App\Simulation\Routing\Enums;

/**
 * Route adherence status relative to the planned polyline.
 */
enum RouteDeviationStatus: string
{
    case ON_ROUTE = 'ON_ROUTE';
    case DEVIATED = 'DEVIATED';
}
