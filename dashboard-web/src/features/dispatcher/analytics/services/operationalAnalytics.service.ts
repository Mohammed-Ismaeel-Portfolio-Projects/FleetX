import type { OperationalAnalyticsViewModel } from '../types/operationalAnalytics.types';
import { analyticsApiService, AnalyticsApiError } from './analyticsApi.service';
import {
  dashboardService,
  DashboardApiError,
} from '../../dashboard/services/dashboard.service';
import { mapToOperationalAnalyticsViewModel } from '../utils/analyticsAdapter';

type Settled<T> = { ok: true; data: T } | { ok: false; error: string };

function isPermissionDenied(error: unknown): boolean {
  if (error instanceof AnalyticsApiError) {
    return error.statusCode === 401 || error.statusCode === 403;
  }
  if (error instanceof DashboardApiError) {
    return error.status === 401 || error.status === 403;
  }
  return false;
}

function settleMessage(error: unknown, fallbackMessage: string): string {
  if (isPermissionDenied(error)) {
    return 'Permission denied for this data source';
  }
  if (error instanceof Error && error.message.trim()) {
    return error.message;
  }
  return fallbackMessage;
}

async function settle<T>(fn: () => Promise<T>, fallbackMessage: string): Promise<Settled<T>> {
  try {
    const data = await fn();
    return { ok: true, data };
  } catch (error) {
    return { ok: false, error: settleMessage(error, fallbackMessage) };
  }
}

export class OperationalAnalyticsLoadError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'OperationalAnalyticsLoadError';
  }
}

export const operationalAnalyticsService = {
  /**
   * Loads independent KPI sources with Promise.allSettled semantics.
   * Partial success is returned; throws only when every KPI domain source fails.
   * Auth failures surface as honest permission errors — never mapped to fake zeros.
   */
  async getViewModel(): Promise<OperationalAnalyticsViewModel> {
    const [dashboard, fleet, shipments, drivers, performance] = await Promise.all([
      settle(() => analyticsApiService.getDashboardKPIs(), 'Failed to fetch dashboard KPIs'),
      settle(() => analyticsApiService.getFleetKPIs(), 'Failed to fetch fleet KPIs'),
      settle(() => analyticsApiService.getShipmentKPIs(), 'Failed to fetch shipment KPIs'),
      settle(() => analyticsApiService.getDriverKPIs(), 'Failed to fetch driver KPIs'),
      settle(() => dashboardService.getPerformance(), 'Failed to fetch dashboard performance metrics'),
    ]);

    const anyKpiSourceOk = dashboard.ok || fleet.ok || shipments.ok || drivers.ok;

    if (!anyKpiSourceOk) {
      const detail = [dashboard, fleet, shipments, drivers]
        .filter((r): r is { ok: false; error: string } => !r.ok)
        .map((r) => r.error)
        .join('; ');
      throw new OperationalAnalyticsLoadError(
        detail || 'Unable to load analytics — all KPI sources failed'
      );
    }

    return mapToOperationalAnalyticsViewModel({
      dashboardKPIs: dashboard.ok ? dashboard.data : null,
      fleetKPIs: fleet.ok ? fleet.data : null,
      shipmentKPIs: shipments.ok ? shipments.data : null,
      driverKPIs: drivers.ok ? drivers.data : null,
      performance: performance.ok ? performance.data : null,
      sectionErrors: {
        dashboard: dashboard.ok ? null : dashboard.error,
        fleet: fleet.ok ? null : fleet.error,
        shipments: shipments.ok ? null : shipments.error,
        drivers: drivers.ok ? null : drivers.error,
        performance: performance.ok ? null : performance.error,
      },
    });
  },
};
