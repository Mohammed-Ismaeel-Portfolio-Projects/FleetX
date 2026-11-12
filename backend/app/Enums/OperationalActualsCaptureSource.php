<?php

namespace App\Enums;

/**
 * Source of persisted shipment operational actuals (Phase 5.5a).
 *
 * Note: simulation is persisted for preview/testing architecture only.
 * It must not be treated as invoice-grade billing truth (Phase 5.5c).
 */
enum OperationalActualsCaptureSource: string
{
    case GPS_AGGREGATION = 'gps_aggregation';
    case DRIVER_EVENTS = 'driver_events';
    case OPS_MANUAL = 'ops_manual';
    case SIMULATION = 'simulation';
}
