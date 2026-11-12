import type { ClientShipment } from '../shipments/types';
import type { ClientShipmentRequest } from '../requests/types';
import { SHIPMENT_STATUS } from '../shipments/shipmentWorkspace';

/**
 * Customer-safe shipment statuses that count as operationally active on Home.
 * Aligned with Client lifecycle catalog (SHIPMENT_STATUS / C3.11–C3.12).
 * Terminal statuses (Delivered, Completed, Cancelled) are excluded.
 *
 * Note: Legacy StatusesSeeder snake_case names are intentionally NOT included.
 * Dual backend vocabulary may still exist in some databases; snake_case-only
 * rows will not appear in Home "active" selection (honest omission).
 * Engine operational stages are never treated as Home statuses.
 */
const ACTIVE_SHIPMENT_STATUS_NAMES = new Set<string>([
  SHIPMENT_STATUS.WAITING_ASSIGNMENT,
  SHIPMENT_STATUS.ASSIGNED,
  SHIPMENT_STATUS.READY_FOR_PICKUP,
  SHIPMENT_STATUS.PICKED_UP,
  SHIPMENT_STATUS.IN_TRANSIT,
  SHIPMENT_STATUS.AT_CUSTOMS,
  SHIPMENT_STATUS.OUT_FOR_DELIVERY,
]);

/** Request statuses that still need customer awareness (not converted/cancelled). */
const PENDING_REQUEST_STATUS_NAMES = new Set([
  'draft',
  'submitted',
  'under_review',
  'changes_requested',
  'approved',
  'rejected',
]);

const ACTIONABLE_REQUEST_STATUS_NAMES = new Set(['changes_requested', 'draft']);

/**
 * Converted requests are excluded from Home pending rows.
 * Continuity is the created shipment on the Client Shipment API (Active Shipments),
 * plus Requests → Request Detail → existing Shipment Workspace.
 * If a converted request is shown, this returns the Client-safe linked shipment id only.
 */
export function shipmentWorkspaceIdForConvertedRequest(
  request: Pick<ClientShipmentRequest, 'status' | 'linkedShipment'>
): string | null {
  if (!request?.status?.name || request.status.name !== 'converted') return null;
  const id = request.linkedShipment?.id;
  if (id == null) return null;
  const trimmed = String(id).trim();
  return trimmed ? trimmed : null;
}

export function isActiveShipmentStatus(name: string | null | undefined): boolean {
  if (!name) return false;
  return ACTIVE_SHIPMENT_STATUS_NAMES.has(name);
}

export function isPendingRequestStatus(name: string | null | undefined): boolean {
  if (!name) return false;
  return PENDING_REQUEST_STATUS_NAMES.has(name);
}

export function isActionableRequestStatus(name: string | null | undefined): boolean {
  if (!name) return false;
  return ACTIONABLE_REQUEST_STATUS_NAMES.has(name);
}

export function selectActiveShipments(shipments: ClientShipment[], limit = 3): ClientShipment[] {
  return shipments.filter((s) => isActiveShipmentStatus(s.status?.name)).slice(0, limit);
}

export function selectPendingRequests(
  requests: ClientShipmentRequest[],
  limit = 3
): ClientShipmentRequest[] {
  return requests.filter((r) => isPendingRequestStatus(r.status?.name)).slice(0, limit);
}

export function hasActionableRequest(requests: ClientShipmentRequest[]): boolean {
  return requests.some((r) => isActionableRequestStatus(r.status?.name));
}

/**
 * Home empty-hub copy (final polish P2-1).
 * Uses only the loaded shipment page — if any rows exist but none are active,
 * wording must not imply the customer has never had shipments.
 */
export function homeEmptyHubCopy(hasAnyLoadedShipments: boolean): {
  title: string;
  body: string;
} {
  if (hasAnyLoadedShipments) {
    return {
      title: 'No active shipments right now',
      body:
        'None of your shipments are currently in progress. Open Shipments to review past deliveries, or go to Requests to submit a new one.',
    };
  }
  return {
    title: 'No shipments yet',
    body:
      'When you submit a shipment request and it is converted, it will appear here so you can follow pickup, transit, and delivery.',
  };
}

/** Presentation-only date for Home cards — calendar date portion when ISO. */
export function formatHomeDate(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;
  const isoDate = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
  return isoDate ? isoDate[1] : trimmed;
}
