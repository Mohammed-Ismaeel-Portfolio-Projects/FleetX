<?php

namespace App\Simulation\Journey;

/**
 * Lifecycle of a single planned rest waypoint (operational — not a Shipment status).
 */
enum RestStopStatus: string
{
    case Planned = 'planned';
    case Approaching = 'approaching';
    case Active = 'active';
    case Completed = 'completed';
}
