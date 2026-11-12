/** Canonical ShipmentOrderStatus names — presentation helpers only. */

export const REQUEST_STATUS = {
  DRAFT: 'draft',
  SUBMITTED: 'submitted',
  UNDER_REVIEW: 'under_review',
  CHANGES_REQUESTED: 'changes_requested',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
  CONVERTED: 'converted',
} as const;

export type RequestStatusName = (typeof REQUEST_STATUS)[keyof typeof REQUEST_STATUS];

/**
 * Exact `status.name` values for GET /client/shipment-requests?status=…
 * Domain statuses only — no invented Client vocabulary.
 */
export const CLIENT_REQUEST_STATUS_FILTERS: readonly RequestStatusName[] = [
  REQUEST_STATUS.DRAFT,
  REQUEST_STATUS.SUBMITTED,
  REQUEST_STATUS.UNDER_REVIEW,
  REQUEST_STATUS.CHANGES_REQUESTED,
  REQUEST_STATUS.APPROVED,
  REQUEST_STATUS.REJECTED,
  REQUEST_STATUS.CONVERTED,
  REQUEST_STATUS.CANCELLED,
];
export function requestStatusName(raw: string | null | undefined): string | null {
  if (!raw) return null;
  const trimmed = String(raw).trim();
  return trimmed ? trimmed : null;
}

export function isDraftStatus(name: string | null | undefined): boolean {
  return requestStatusName(name) === REQUEST_STATUS.DRAFT;
}

export function isChangesRequestedStatus(name: string | null | undefined): boolean {
  return requestStatusName(name) === REQUEST_STATUS.CHANGES_REQUESTED;
}

export function isRejectedStatus(name: string | null | undefined): boolean {
  return requestStatusName(name) === REQUEST_STATUS.REJECTED;
}

/** Backend allows PATCH only in draft or changes_requested. */
export function isEditableRequestStatus(name: string | null | undefined): boolean {
  const n = requestStatusName(name);
  return n === REQUEST_STATUS.DRAFT || n === REQUEST_STATUS.CHANGES_REQUESTED;
}

export function canSubmitRequest(name: string | null | undefined): boolean {
  return isDraftStatus(name);
}

export function canResubmitRequest(name: string | null | undefined): boolean {
  return isChangesRequestedStatus(name);
}

export function isConvertedStatus(name: string | null | undefined): boolean {
  return requestStatusName(name) === REQUEST_STATUS.CONVERTED;
}

/** Safe linked shipment id from the Client contract — never invented. */
export function linkedShipmentId(
  linked: { id?: string | null } | null | undefined
): string | null {
  if (!linked || linked.id == null) return null;
  const id = String(linked.id).trim();
  return id ? id : null;
}

/** UI-only label — does not invent backend vocabulary. */
export function formatRequestStatusLabel(rawName: string | null | undefined): string {
  if (!rawName) return 'Unavailable';
  return rawName
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join(' ');
}

export function requestStatusGuidance(name: string | null | undefined): string {
  switch (requestStatusName(name)) {
    case REQUEST_STATUS.DRAFT:
      return 'This is a draft. FleetX has not received it yet. You can keep editing, then review and submit.';
    case REQUEST_STATUS.SUBMITTED:
      return 'FleetX has received your request and it is awaiting review. This is not a shipment yet.';
    case REQUEST_STATUS.UNDER_REVIEW:
      return 'FleetX is reviewing your request. No further action is needed from you right now.';
    case REQUEST_STATUS.CHANGES_REQUESTED:
      return 'FleetX asked you to update this request. It is still a shipment request, not a shipment. Edit the details, then resubmit.';
    case REQUEST_STATUS.APPROVED:
      return 'FleetX approved this request. A shipment is created by FleetX after conversion.';
    case REQUEST_STATUS.REJECTED:
      return 'FleetX declined this request. It is not a shipment.';
    case REQUEST_STATUS.CONVERTED:
      return 'This request has been converted into a shipment. Open the shipment to follow pickup, transit, and delivery.';
    case REQUEST_STATUS.CANCELLED:
      return 'This request was cancelled.';
    default:
      return 'This is a shipment request for FleetX to arrange transportation.';
  }
}
