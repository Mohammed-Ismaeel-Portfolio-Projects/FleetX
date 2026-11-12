import type {
  OperationalAnalyticsViewModel,
  OperationalAnalyticsKpi,
  FleetPerformanceMetric,
  DriverAvailabilityMetric,
  ShipmentStatusMetric,
  OperationalAnalyticsSectionErrors,
} from '../types/operationalAnalytics.types';
import type {
  DashboardKPIs,
  FleetKPIs,
  ShipmentKPIs,
  DriverKPIs,
} from '../services/analyticsApi.service';
import type { DashboardPerformance } from '../../dashboard/services/dashboard.service';

/**
 * Presentation adapters — pass-through of real endpoint fields only.
 * Null source → Unavailable. Never invent zeros, trends, or targets.
 */

function mapOnTimeDeliveryKpi(
  performance: DashboardPerformance | null
): OperationalAnalyticsKpi {
  const raw = performance?.on_time_delivery_percentage;

  if (raw === null || raw === undefined || !Number.isFinite(raw)) {
    return {
      id: 'on-time-delivery',
      label: 'On-Time Delivery',
      value: 'Unavailable',
      detail: 'Share of completed shipments delivered on or before planned date',
    };
  }

  return {
    id: 'on-time-delivery',
    label: 'On-Time Delivery',
    value: `${raw}%`,
    detail: 'Share of completed shipments delivered on or before planned date',
  };
}

function mapDashboardCountKpi(
  id: string,
  label: string,
  value: number | null | undefined,
  detail: string
): OperationalAnalyticsKpi {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return {
      id,
      label,
      value: 'Unavailable',
      detail,
    };
  }

  return {
    id,
    label,
    value: String(value),
    detail,
  };
}

export function mapToExecutiveKPIs(
  dashboardKPIs: DashboardKPIs | null,
  fleetKPIs: FleetKPIs | null,
  driverKPIs: DriverKPIs | null,
  performance: DashboardPerformance | null
): OperationalAnalyticsKpi[] {
  return [
    mapDashboardCountKpi(
      'critical-alerts',
      'Critical Alerts',
      dashboardKPIs?.critical_alerts,
      'Open issues typed critical or emergency'
    ),
    mapDashboardCountKpi(
      'delayed-shipments',
      'Delayed Shipments',
      dashboardKPIs?.delayed_shipments,
      'No in-flight delay SSOT when Unavailable'
    ),
    mapDashboardCountKpi(
      'pending-assignments',
      'Pending Assignments',
      dashboardKPIs?.pending_assignments,
      'Assignments in Draft, Assigned, or Ready'
    ),
    mapDashboardCountKpi(
      'active-shipments',
      'Active Shipments',
      dashboardKPIs?.active_shipments,
      'Shipments not Completed or Cancelled'
    ),
    mapDashboardCountKpi(
      'vehicles-in-transit',
      'Vehicles In Transit',
      dashboardKPIs?.vehicles_in_transit,
      'Distinct active vehicles on active assignments'
    ),
    {
      id: 'fleet-utilization',
      label: 'Fleet Utilization',
      value:
        fleetKPIs != null && Number.isFinite(fleetKPIs.utilization)
          ? `${fleetKPIs.utilization}%`
          : 'Unavailable',
      detail: 'Assigned (busy) vehicles as % of active fleet',
    },
    {
      id: 'available-drivers',
      label: 'Available Drivers',
      value:
        driverKPIs != null && Number.isFinite(driverKPIs.available)
          ? String(driverKPIs.available)
          : 'Unavailable',
      detail: 'Drivers available for dispatch (count)',
    },
    mapOnTimeDeliveryKpi(performance),
    mapCompletedTodayKpi(performance),
    mapAverageDispatchTimeKpi(performance),
  ];
}

function mapCompletedTodayKpi(performance: DashboardPerformance | null): OperationalAnalyticsKpi {
  const raw = performance?.completed_today;
  if (raw === null || raw === undefined || !Number.isFinite(raw)) {
    return {
      id: 'completed-today',
      label: 'Completed Today',
      value: 'Unavailable',
      detail: 'Shipments completed with actual delivery date today',
    };
  }
  return {
    id: 'completed-today',
    label: 'Completed Today',
    value: String(raw),
    detail: 'Shipments completed with actual delivery date today',
  };
}

function mapAverageDispatchTimeKpi(performance: DashboardPerformance | null): OperationalAnalyticsKpi {
  const raw = performance?.average_dispatch_time_minutes;
  if (raw === null || raw === undefined || !Number.isFinite(raw)) {
    return {
      id: 'avg-dispatch-time',
      label: 'Average Dispatch Time',
      value: 'Unavailable',
      detail: 'Average minutes from assignment created to In Operation',
    };
  }
  return {
    id: 'avg-dispatch-time',
    label: 'Average Dispatch Time',
    value: `${raw} min`,
    detail: 'Average minutes from assignment created to In Operation',
  };
}

function mapFleetKpiValue(
  value: number | null | undefined,
  format: 'count' | 'percent' = 'count'
): string {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return 'Unavailable';
  }
  return format === 'percent' ? `${value}%` : String(value);
}

const FLEET_UNAVAILABLE: FleetPerformanceMetric[] = [
  { label: 'Total Fleet', value: 'Unavailable' },
  { label: 'Available', value: 'Unavailable' },
  { label: 'Assigned', value: 'Unavailable' },
  { label: 'Maintenance', value: 'Unavailable' },
  { label: 'Offline', value: 'Unavailable' },
  { label: 'Utilization', value: 'Unavailable' },
];

export function mapToFleetMetrics(fleetKPIs: FleetKPIs | null): FleetPerformanceMetric[] {
  if (!fleetKPIs) return FLEET_UNAVAILABLE;

  return [
    { label: 'Total Fleet', value: mapFleetKpiValue(fleetKPIs.total_fleet) },
    { label: 'Available', value: mapFleetKpiValue(fleetKPIs.available) },
    { label: 'Assigned', value: mapFleetKpiValue(fleetKPIs.assigned) },
    { label: 'Maintenance', value: mapFleetKpiValue(fleetKPIs.maintenance) },
    { label: 'Offline', value: mapFleetKpiValue(fleetKPIs.offline) },
    { label: 'Utilization', value: mapFleetKpiValue(fleetKPIs.utilization, 'percent') },
  ];
}

function mapDriverKpiValue(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return 'Unavailable';
  }
  return String(value);
}

const DRIVER_UNAVAILABLE: DriverAvailabilityMetric[] = [
  { label: 'Total Drivers', value: 'Unavailable' },
  { label: 'Available', value: 'Unavailable' },
  { label: 'Busy', value: 'Unavailable' },
  { label: 'Offline', value: 'Unavailable' },
  { label: 'On Leave', value: 'Unavailable' },
  { label: 'Suspended', value: 'Unavailable' },
];

export function mapToDriverMetrics(driverKPIs: DriverKPIs | null): DriverAvailabilityMetric[] {
  if (!driverKPIs) return DRIVER_UNAVAILABLE;

  return [
    { label: 'Total Drivers', value: mapDriverKpiValue(driverKPIs.total_drivers) },
    { label: 'Available', value: mapDriverKpiValue(driverKPIs.available) },
    { label: 'Busy', value: mapDriverKpiValue(driverKPIs.busy) },
    { label: 'Offline', value: mapDriverKpiValue(driverKPIs.offline) },
    { label: 'On Leave', value: mapDriverKpiValue(driverKPIs.on_leave) },
    { label: 'Suspended', value: mapDriverKpiValue(driverKPIs.suspended) },
  ];
}

function mapShipmentKpiValue(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return 'Unavailable';
  }
  return String(value);
}

const SHIPMENT_UNAVAILABLE: ShipmentStatusMetric[] = [
  { label: 'Total Shipments', value: 'Unavailable' },
  { label: 'Pending', value: 'Unavailable' },
  { label: 'Assigned', value: 'Unavailable' },
  { label: 'In Transit', value: 'Unavailable' },
  { label: 'Delivered', value: 'Unavailable' },
  { label: 'Urgent', value: 'Unavailable' },
];

export function mapToShipmentMetrics(shipmentKPIs: ShipmentKPIs | null): ShipmentStatusMetric[] {
  if (!shipmentKPIs) return SHIPMENT_UNAVAILABLE;

  const urgent = shipmentKPIs.urgent ?? shipmentKPIs.critical;

  return [
    { label: 'Total Shipments', value: mapShipmentKpiValue(shipmentKPIs.total_fleet) },
    { label: 'Pending', value: mapShipmentKpiValue(shipmentKPIs.pending) },
    { label: 'Assigned', value: mapShipmentKpiValue(shipmentKPIs.assigned) },
    { label: 'In Transit', value: mapShipmentKpiValue(shipmentKPIs.in_transit) },
    { label: 'Delivered', value: mapShipmentKpiValue(shipmentKPIs.delivered) },
    { label: 'Urgent', value: mapShipmentKpiValue(urgent) },
  ];
}

export function mapToOperationalAnalyticsViewModel(input: {
  dashboardKPIs: DashboardKPIs | null;
  fleetKPIs: FleetKPIs | null;
  shipmentKPIs: ShipmentKPIs | null;
  driverKPIs: DriverKPIs | null;
  performance: DashboardPerformance | null;
  sectionErrors: OperationalAnalyticsSectionErrors;
}): OperationalAnalyticsViewModel {
  return {
    header: {
      lastUpdated: new Date().toLocaleTimeString(),
    },
    kpis: mapToExecutiveKPIs(
      input.dashboardKPIs,
      input.fleetKPIs,
      input.driverKPIs,
      input.performance
    ),
    fleet: mapToFleetMetrics(input.fleetKPIs),
    drivers: mapToDriverMetrics(input.driverKPIs),
    shipments: mapToShipmentMetrics(input.shipmentKPIs),
    sectionErrors: input.sectionErrors,
  };
}
