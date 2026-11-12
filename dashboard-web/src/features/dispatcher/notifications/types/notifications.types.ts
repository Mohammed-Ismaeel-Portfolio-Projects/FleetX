/** Official Issue status vocabulary (backend SSOT). */
export type OfficialIssueStatus = 'Open' | 'In Progress' | 'Resolved';

/**
 * Issue-backed row for Alerts Center — real SSOT fields only.
 * Null means missing relation/value; UI renders project-standard Unavailable.
 */
export interface Incident {
  /** Canonical Issue.id (stringified for React keys only). */
  id: string;
  /** Raw Issue.type — never coerced into another category. */
  type: string | null;
  /** Raw Issue.description. */
  description: string | null;
  /** Authoritative Issue status name when present. */
  status: string | null;
  /** Issue.status_id / status.id when present. */
  statusId: number | null;
  driverId: number | null;
  driverName: string | null;
  shipmentId: string | null;
  shipmentNumber: string | null;
  createdAt: string | null;
}

export interface IncidentMetrics {
  /** Query-wide total from Issues pagination meta.total for the current filters. */
  queryTotal: number;
  /** Rows on the currently loaded server page (after optional client search). */
  pageCount: number;
  pageOpen: number;
  pageInProgress: number;
  pageResolved: number;
}

export interface IssuePaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}
