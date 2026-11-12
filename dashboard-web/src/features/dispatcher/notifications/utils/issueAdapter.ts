import type { Incident } from '../types/notifications.types';
import type { Issue } from '../../dashboard/services/issues.service';

/** Presentation-only: never write into the data model. */
export function displaySsotValue(value: string | number | null | undefined): string {
  if (value === null || value === undefined) return 'Unavailable';
  const text = String(value).trim();
  return text.length > 0 ? text : 'Unavailable';
}

function nullableText(value: string | null | undefined): string | null {
  if (value === null || value === undefined) return null;
  const text = value.trim();
  return text.length > 0 ? text : null;
}

/**
 * Thin Issues → Incident presentation mapper.
 * Pass-through of real Issue fields only — no invented operational meaning.
 */
export function mapIssueToIncident(issue: Issue): Incident {
  const driverName = nullableText(issue.driver?.full_name);
  const shipmentNumber = nullableText(issue.shipment?.shipment_number);
  const shipmentId =
    nullableText(issue.shipment?.id) ??
    nullableText(issue.shipment_id ?? undefined);

  return {
    id: String(issue.id),
    type: nullableText(issue.type),
    description: nullableText(issue.description),
    status: nullableText(issue.status?.name),
    statusId: issue.status?.id ?? issue.status_id ?? null,
    driverId: issue.driver?.id ?? issue.driver_id ?? null,
    driverName,
    shipmentId,
    shipmentNumber,
    createdAt: nullableText(issue.created_at),
  };
}

export function mapIssuesToIncidents(issues: Issue[]): Incident[] {
  return issues.map(mapIssueToIncident);
}
