import {
  getDriverKPIs,
  getDrivers,
} from '../../drivers/services/driver-operations.service';
import {
  dashboardService,
  type DashboardKPI,
} from '../../dashboard/services/dashboard.service';
import { issuesService, type Issue } from '../../dashboard/services/issues.service';
import type {
  CommunicationContextMetric,
  CommunicationSectionErrors,
  CommunicationViewModel,
} from '../types/communication.types';
import type { DriverKPIs } from '../../drivers/types/driver-operations.types';

type Settled<T> = { ok: true; data: T } | { ok: false; error: string };

async function settle<T>(fn: () => Promise<T>, fallback: string): Promise<Settled<T>> {
  try {
    return { ok: true, data: await fn() };
  } catch (error) {
    const message =
      error instanceof Error && error.message.trim() ? error.message : fallback;
    return { ok: false, error: message };
  }
}

function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) return 'Unavailable';
  return String(value);
}

function buildMetrics(input: {
  driverKpis: DriverKPIs | null;
  dashboard: DashboardKPI | null;
}): CommunicationContextMetric[] {
  const { driverKpis, dashboard } = input;

  return [
    {
      id: 'available-drivers',
      label: 'Available Drivers',
      value: formatCount(driverKpis?.available),
      detail: 'Drivers available for dispatch (drivers/kpis)',
      href: '/dispatcher/drivers',
    },
    {
      id: 'busy-drivers',
      label: 'Busy Drivers',
      value: formatCount(driverKpis?.busy),
      detail: 'Drivers committed on an active assignment',
      href: '/dispatcher/drivers',
    },
    {
      id: 'critical-alerts',
      label: 'Critical Alerts',
      value: formatCount(dashboard?.critical_alerts),
      detail: 'Open issues typed critical or emergency',
      href: '/dispatcher/notifications',
    },
    {
      id: 'active-shipments',
      label: 'Active Shipments',
      value: formatCount(dashboard?.active_shipments),
      detail: 'Shipments not Completed or Cancelled',
      href: '/dispatcher/shipments',
    },
    {
      id: 'vehicles-in-transit',
      label: 'Vehicles In Transit',
      value: formatCount(dashboard?.vehicles_in_transit),
      detail: 'Distinct active vehicles on active assignments',
      href: '/dispatcher/tracking',
    },
  ];
}

/**
 * Loads Communication Center operational context from existing SSOTs.
 * Messaging threads are loaded separately via M2 REST (useMessaging).
 */
export const communicationService = {
  async getViewModel(): Promise<CommunicationViewModel> {
    const [driversResult, kpisResult, dashboardResult, issuesResult, activityResult] =
      await Promise.all([
        settle(
          () => getDrivers({ page: 1, per_page: 50, is_active: true }),
          'Failed to load drivers'
        ),
        settle(() => getDriverKPIs(), 'Failed to load driver KPIs'),
        settle(() => dashboardService.getKPI(), 'Failed to load dashboard KPIs'),
        settle(
          () =>
            issuesService.getIssues({
              page: 1,
              per_page: 12,
              sort_by: 'updated_at',
              sort_direction: 'desc',
            }),
          'Failed to load operational issues'
        ),
        settle(() => dashboardService.getActivityTimeline(), 'Failed to load activity'),
      ]);

    const anyOk =
      driversResult.ok ||
      kpisResult.ok ||
      dashboardResult.ok ||
      issuesResult.ok ||
      activityResult.ok;

    if (!anyOk) {
      const detail = [driversResult, kpisResult, dashboardResult, issuesResult, activityResult]
        .filter((r): r is { ok: false; error: string } => !r.ok)
        .map((r) => r.error)
        .join('; ');
      throw new Error(detail || 'Unable to load communication center');
    }

    const sectionErrors: CommunicationSectionErrors = {
      drivers: driversResult.ok ? null : driversResult.error,
      driverKpis: kpisResult.ok ? null : kpisResult.error,
      dashboard: dashboardResult.ok ? null : dashboardResult.error,
      issues: issuesResult.ok ? null : issuesResult.error,
      activity: activityResult.ok ? null : activityResult.error,
    };

    const issues: Issue[] = issuesResult.ok ? issuesResult.data.data : [];

    return {
      lastUpdated: new Date().toLocaleTimeString(),
      metrics: buildMetrics({
        driverKpis: kpisResult.ok ? kpisResult.data : null,
        dashboard: dashboardResult.ok ? dashboardResult.data : null,
      }),
      drivers: driversResult.ok ? driversResult.data.drivers : [],
      driverTotal: driversResult.ok ? driversResult.data.pagination.total : null,
      driverKpis: kpisResult.ok ? kpisResult.data : null,
      issues,
      activity: activityResult.ok ? activityResult.data : [],
      sectionErrors,
    };
  },
};
