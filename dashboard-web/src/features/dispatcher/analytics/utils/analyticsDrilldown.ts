import { useLocation } from 'react-router-dom';
import { useAuth } from '../../../../core/auth';

export type OpsRole = 'admin' | 'dispatcher';
export type OpsBasePath = '/dispatcher' | '/admin';

/**
 * Resolve Analytics host from authenticated role (authorization source of truth).
 * Pathname is only a fallback when role is unavailable; never invents cross-role hosts.
 *
 * Sharing AnalyticsPage between `/dispatcher/analytics` and `/admin/analytics` is valid:
 * KPI sources are auth:api dashboard/domain endpoints available to both roles.
 * Navigation destinations remain role-scoped via ProtectedRoute shells.
 */
export function resolveOpsBasePath(
  role: OpsRole | undefined | null,
  pathname: string
): OpsBasePath {
  if (role === 'admin') return '/admin';
  if (role === 'dispatcher') return '/dispatcher';
  return pathname.startsWith('/admin') ? '/admin' : '/dispatcher';
}

export function useOpsBasePath(): OpsBasePath {
  const { user } = useAuth();
  const { pathname } = useLocation();
  return resolveOpsBasePath(user?.role, pathname);
}

/**
 * Executive KPI → existing operational route authorized for this role (App.tsx).
 * Returns null when no safe destination exists — never cross-role links.
 *
 * Role rules:
 * - Critical Alerts → Dispatcher Alerts Center only (`/dispatcher/notifications`)
 * - Pending Assignments → no drilldown destination (assignment management is Dispatcher-only)
 * - Fleet / Drivers / Shipments / Tracking → same segment under the role base
 * - Delivery KPIs / Delayed Shipments → no destination
 */
export function getExecutiveDrilldownPath(kpiId: string, base: OpsBasePath): string | null {
  switch (kpiId) {
    case 'critical-alerts':
      return base === '/dispatcher' ? '/dispatcher/notifications' : null;
    case 'pending-assignments':
      return null;
    case 'active-shipments':
      return `${base}/shipments`;
    case 'vehicles-in-transit':
      return `${base}/tracking`;
    case 'fleet-utilization':
      return `${base}/fleet`;
    case 'available-drivers':
      return `${base}/drivers`;
    case 'delayed-shipments':
    case 'on-time-delivery':
    case 'completed-today':
    case 'avg-dispatch-time':
      return null;
    default:
      return null;
  }
}

/** Short CTA for clickable executive KPIs — matches destination. */
export function getExecutiveDrilldownCtaLabel(kpiId: string): string {
  switch (kpiId) {
    case 'critical-alerts':
      return 'Open Alerts →';
    case 'pending-assignments':
      return 'Open Assignments →';
    case 'active-shipments':
      return 'Open Shipments →';
    case 'vehicles-in-transit':
      return 'Open Tracking →';
    case 'fleet-utilization':
      return 'Open Fleet →';
    case 'available-drivers':
      return 'Open Drivers →';
    default:
      return 'Open →';
  }
}

/** Section → Fleet route under the current role shell. */
export function getFleetDrilldownPath(base: OpsBasePath): string {
  return `${base}/fleet`;
}

/** Section → Drivers route under the current role shell. */
export function getDriversDrilldownPath(base: OpsBasePath): string {
  return `${base}/drivers`;
}

/** Section → Shipments route under the current role shell. */
export function getShipmentsDrilldownPath(base: OpsBasePath): string {
  return `${base}/shipments`;
}
