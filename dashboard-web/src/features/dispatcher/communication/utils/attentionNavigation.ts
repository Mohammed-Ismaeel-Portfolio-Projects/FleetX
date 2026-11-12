import type { Issue } from '../../dashboard/services/issues.service';
import { alertsCenterIssueHref } from '../../notifications/utils/issueDeepLink';

/** Existing dispatcher routes used by Attention Queue deep links. */
export const ATTENTION_ROUTES = {
  alertsCenter: '/dispatcher/notifications',
  driversSurface: '/dispatcher/drivers',
  shipmentsList: '/dispatcher/shipments',
  shipmentDetail: (shipmentId: string) => `/dispatcher/shipments/${shipmentId}`,
} as const;

export interface IssueAttentionTargets {
  issueId: number;
  /**
   * Alerts Center with `?issue=<Issue.id>` when id is valid;
   * otherwise generic `/dispatcher/notifications`.
   */
  alertsHref: string;
  /** Real shipment UUID/string from Issue SSOT, or null. */
  shipmentId: string | null;
  /** `/dispatcher/shipments/:id` when shipmentId is present. */
  shipmentHref: string | null;
  /** Real driver id as string (Issue `driver.id` / `driver_id`), or null. */
  driverId: string | null;
  /**
   * Driver Operations list when a real driver id exists.
   * There is no `/dispatcher/drivers/:id` route — ID is not placed in the URL.
   */
  driverSurfaceHref: string | null;
  /** Critical / emergency typed issues — Alerts is the primary ops destination. */
  prefersAlerts: boolean;
}

function nullableIdText(value: string | number | null | undefined): string | null {
  if (value === null || value === undefined) return null;
  const text = String(value).trim();
  return text.length > 0 ? text : null;
}

/**
 * Resolve Attention Queue destinations from Issue SSOT fields only.
 * Missing relation/ID → null href (caller shows Unavailable).
 * Does not invent path routes or fake record IDs.
 */
export function resolveIssueAttentionTargets(issue: Issue): IssueAttentionTargets {
  const shipmentId =
    nullableIdText(issue.shipment?.id) ?? nullableIdText(issue.shipment_id ?? undefined);

  const driverId =
    nullableIdText(issue.driver?.id) ?? nullableIdText(issue.driver_id ?? undefined);

  const type = (issue.type || '').trim().toLowerCase();
  const prefersAlerts = type === 'critical' || type === 'emergency';

  return {
    issueId: issue.id,
    alertsHref: alertsCenterIssueHref(issue.id),
    shipmentId,
    shipmentHref: shipmentId ? ATTENTION_ROUTES.shipmentDetail(shipmentId) : null,
    driverId,
    driverSurfaceHref: driverId ? ATTENTION_ROUTES.driversSurface : null,
    prefersAlerts,
  };
}
