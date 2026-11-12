import type {
  ShipmentRequestDetail,
  ShipmentRequestListItem,
} from '../types/request.types';

/** Raw list/show payload fields from GET /shipment-requests. */
export interface ShipmentRequestApiItem {
  id: string;
  request_number: string;
  reference_number?: string | null;
  customer_company?: {
    id: string;
    company_name: string;
    company_code: string;
  } | null;
  status_id: number | string;
  status?: {
    id: number | string;
    name: string;
    type?: string;
  } | null;
  submission_revision?: number | null;
  cargo_type?: string | null;
  cargo_description?: string | null;
  cargo_weight?: string | number | null;
  cargo_volume?: string | number | null;
  package_count?: number | null;
  trailer_type?: string | null;
  converted_shipment_id?: string | null;
  planned_route?: {
    status: string;
    distance_meters?: number | null;
    distance_km?: number | null;
    duration_seconds?: number | null;
    duration_formatted?: string | null;
    geometry?: [number, number][] | null;
    calculated_at?: string | null;
  } | null;
  price_summary?: {
    currency?: string;
    total?: number;
    calculated_at?: string | null;
  } | null;
  price_snapshot?: {
    currency?: string;
    total?: number;
    calculated_at?: string | null;
    distance?: { distance_km?: number | null } | null;
    warnings?: string[];
  } | null;
  pickup_company?: string | null;
  pickup_contact?: string | null;
  pickup_phone?: string | null;
  pickup_address?: string | null;
  pickup_city?: string | null;
  pickup_country?: string | null;
  pickup_latitude?: number | string | null;
  pickup_longitude?: number | string | null;
  delivery_company?: string | null;
  delivery_contact?: string | null;
  delivery_phone?: string | null;
  delivery_address?: string | null;
  delivery_city?: string | null;
  delivery_country?: string | null;
  delivery_latitude?: number | string | null;
  delivery_longitude?: number | string | null;
  requested_pickup_date?: string | null;
  requested_delivery_date?: string | null;
  special_instructions?: string | null;
  client_notes?: string | null;
  dispatcher_notes?: string | null;
  priority?: string | null;
  converted_shipment_id?: string | null;
  converted_shipment?: {
    id: string;
    shipment_number: string;
    status?: { id: number | string; name: string; type?: string } | null;
  } | null;
  submitted_by?: {
    id: string;
    name: string;
    email: string;
  } | null;
  status_history?: Array<{
    id: string | number;
    from_status?: { name?: string } | string | null;
    to_status?: { name?: string } | string | null;
    fromStatus?: { name?: string } | null;
    toStatus?: { name?: string } | null;
    changed_by?: { id: string; name: string; email: string } | null;
    reason?: string | null;
    created_at?: string | null;
  }>;
  created_at?: string | null;
  updated_at?: string | null;
}

function formatRoute(pickupCity?: string | null, pickupCountry?: string | null, deliveryCity?: string | null, deliveryCountry?: string | null): string {
  const origin = [pickupCity, pickupCountry].filter(Boolean).join(', ') || 'Unavailable';
  const destination = [deliveryCity, deliveryCountry].filter(Boolean).join(', ') || 'Unavailable';
  return `${origin} → ${destination}`;
}

function historyStatusName(value: unknown): string | null {
  if (value == null) return null;
  if (typeof value === 'string') return value;
  if (typeof value === 'object' && value !== null && 'name' in value) {
    const name = (value as { name?: unknown }).name;
    return typeof name === 'string' ? name : null;
  }
  return null;
}

function asCoordinate(value: number | string | null | undefined): number | null {
  if (value == null || value === '') return null;
  const n = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(n) ? n : null;
}

/**
 * Pass-through mapper: display backend truth without inventing fields.
 */
export function mapApiRequestToListItem(api: ShipmentRequestApiItem): ShipmentRequestListItem {
  const customer = api.customer_company
    ? {
        id: api.customer_company.id,
        companyName: api.customer_company.company_name,
        companyCode: api.customer_company.company_code,
      }
    : null;

  return {
    id: api.id,
    requestNumber: api.request_number,
    referenceNumber: api.reference_number ?? null,
    customer,
    statusId: api.status_id,
    status: api.status?.name || 'Unavailable',
    submissionRevision: api.submission_revision ?? null,
    cargoType: api.cargo_type ?? null,
    cargoDescription: api.cargo_description ?? null,
    cargoWeight: api.cargo_weight ?? null,
    packageCount: api.package_count ?? null,
    trailerType: api.trailer_type ?? null,
    pickupCity: api.pickup_city ?? null,
    pickupCountry: api.pickup_country ?? null,
    deliveryCity: api.delivery_city ?? null,
    deliveryCountry: api.delivery_country ?? null,
    routeLabel: formatRoute(api.pickup_city, api.pickup_country, api.delivery_city, api.delivery_country),
    requestedPickupDate: api.requested_pickup_date ?? null,
    requestedDeliveryDate: api.requested_delivery_date ?? null,
    priority: api.priority ?? null,
    priceSummary: api.price_summary
      ? {
          currency: api.price_summary.currency || 'USD',
          total: Number(api.price_summary.total ?? 0),
          calculatedAt: api.price_summary.calculated_at ?? null,
        }
      : null,
    convertedShipmentId: api.converted_shipment_id ?? null,
    createdAt: api.created_at ?? null,
    updatedAt: api.updated_at ?? null,
  };
}

export function mapApiRequestToDetail(api: ShipmentRequestApiItem): ShipmentRequestDetail {
  const base = mapApiRequestToListItem(api);

  return {
    ...base,
    submittedBy: api.submitted_by
      ? {
          id: api.submitted_by.id,
          name: api.submitted_by.name,
          email: api.submitted_by.email,
        }
      : null,
    cargoWeight: api.cargo_weight ?? null,
    cargoVolume: api.cargo_volume ?? null,
    packageCount: api.package_count ?? null,
    hazardousCargo: Boolean(api.hazardous_cargo),
    temperatureControlled: Boolean(api.temperature_controlled),
    pickupCompany: api.pickup_company ?? null,
    pickupContact: api.pickup_contact ?? null,
    pickupPhone: api.pickup_phone ?? null,
    pickupAddress: api.pickup_address ?? null,
    pickupLatitude: asCoordinate(api.pickup_latitude),
    pickupLongitude: asCoordinate(api.pickup_longitude),
    deliveryCompany: api.delivery_company ?? null,
    deliveryContact: api.delivery_contact ?? null,
    deliveryPhone: api.delivery_phone ?? null,
    deliveryAddress: api.delivery_address ?? null,
    deliveryLatitude: asCoordinate(api.delivery_latitude),
    deliveryLongitude: asCoordinate(api.delivery_longitude),
    specialInstructions: api.special_instructions ?? null,
    clientNotes: api.client_notes ?? null,
    dispatcherNotes: api.dispatcher_notes ?? null,
    convertedShipmentId: api.converted_shipment_id ?? null,
    convertedShipment: api.converted_shipment
      ? {
          id: api.converted_shipment.id,
          shipmentNumber: api.converted_shipment.shipment_number,
          status: api.converted_shipment.status?.name ?? null,
        }
      : null,
    priceSnapshot: api.price_snapshot
      ? {
          currency: api.price_snapshot.currency || 'USD',
          total: Number(api.price_snapshot.total ?? 0),
          calculatedAt: api.price_snapshot.calculated_at ?? null,
          distanceKm:
            api.price_snapshot.distance?.distance_km != null
              ? Number(api.price_snapshot.distance.distance_km)
              : null,
          warnings: Array.isArray(api.price_snapshot.warnings) ? api.price_snapshot.warnings : [],
        }
      : null,
    plannedRoute: api.planned_route
      ? {
          status: api.planned_route.status,
          distanceMeters: api.planned_route.distance_meters ?? null,
          distanceKm: api.planned_route.distance_km ?? null,
          durationSeconds: api.planned_route.duration_seconds ?? null,
          durationFormatted: api.planned_route.duration_formatted ?? null,
          geometry: Array.isArray(api.planned_route.geometry)
            ? api.planned_route.geometry
            : null,
          calculatedAt: api.planned_route.calculated_at ?? null,
        }
      : null,
    statusHistory: (api.status_history ?? []).map((row) => ({
      id: row.id,
      fromStatus: historyStatusName(row.from_status ?? row.fromStatus),
      toStatus: historyStatusName(row.to_status ?? row.toStatus),
      changedBy: row.changed_by
        ? {
            id: row.changed_by.id,
            name: row.changed_by.name,
            email: row.changed_by.email,
          }
        : null,
      reason: row.reason ?? null,
      createdAt: row.created_at ?? null,
    })),
  };
}

export function requestStatusTone(status: string): 'neutral' | 'info' | 'success' | 'warning' | 'danger' {
  switch (status) {
    case 'submitted':
    case 'under_review':
      return 'info';
    case 'changes_requested':
      return 'warning';
    case 'approved':
    case 'converted':
      return 'success';
    case 'rejected':
    case 'cancelled':
      return 'danger';
    default:
      return 'neutral';
  }
}

export function formatRequestStatusLabel(status: string): string {
  return status.replace(/_/g, ' ');
}

/** Review readiness labels derived only from existing API fields. */
export type RequestReviewPhase =
  | 'draft'
  | 'reviewable'
  | 'awaiting_client'
  | 'ready_for_decision'
  | 'converted'
  | 'closed'
  | 'unknown';

export function getRequestReviewPhase(status: string): RequestReviewPhase {
  switch (status) {
    case 'draft':
      return 'draft';
    case 'submitted':
    case 'under_review':
      return 'reviewable';
    case 'changes_requested':
      return 'awaiting_client';
    case 'approved':
      return 'ready_for_decision';
    case 'converted':
      return 'converted';
    case 'rejected':
    case 'cancelled':
      return 'closed';
    default:
      return 'unknown';
  }
}

/**
 * Backend start-review is allowed only from submitted.
 */
export function canStartReviewRequest(status: string): boolean {
  return status === 'submitted';
}

/**
 * Backend approve/reject are allowed only from under_review (C2.9-B2.2).
 */
export function canApproveOrRejectRequest(status: string): boolean {
  return status === 'under_review';
}

/**
 * Backend request-changes is allowed only from under_review.
 */
export function canRequestChangesRequest(status: string): boolean {
  return status === 'under_review';
}

/**
 * Backend convert is allowed only from approved with no converted shipment (C2.9-B3.3).
 */
export function canConvertRequest(status: string, convertedShipmentId: string | null): boolean {
  return status === 'approved' && !convertedShipmentId;
}

export function getRequestReviewPhaseLabel(phase: RequestReviewPhase): string {
  switch (phase) {
    case 'draft':
      return 'Draft — not yet submitted for review';
    case 'reviewable':
      return 'Reviewable — awaiting dispatcher attention';
    case 'awaiting_client':
      return 'Changes requested — waiting on client resubmission';
    case 'ready_for_decision':
      return 'Approved — convert to shipment when the conversion panel is available';
    case 'converted':
      return 'Converted — operational shipment already created';
    case 'closed':
      return 'Closed — no further review actions available';
    default:
      return 'Status available from API';
  }
}

export function collectMissingReviewFields(detail: {
  customer: { companyName: string } | null;
  submittedBy: { name: string } | null;
  pickupCity: string | null;
  pickupCountry: string | null;
  deliveryCity: string | null;
  deliveryCountry: string | null;
  cargoType: string | null;
  requestedPickupDate: string | null;
  requestedDeliveryDate: string | null;
  priority: string | null;
}): string[] {
  const missing: string[] = [];
  if (!detail.customer?.companyName) missing.push('Customer company');
  if (!detail.submittedBy?.name) missing.push('Submitted by');
  if (!detail.pickupCity && !detail.pickupCountry) missing.push('Pickup location');
  if (!detail.deliveryCity && !detail.deliveryCountry) missing.push('Delivery location');
  if (!detail.cargoType) missing.push('Cargo type');
  if (!detail.requestedPickupDate) missing.push('Requested pickup date');
  if (!detail.requestedDeliveryDate) missing.push('Requested delivery date');
  if (!detail.priority) missing.push('Priority');
  return missing;
}

export type RequestPrimaryAction =
  | 'start_review'
  | 'decide'
  | 'await_client'
  | 'convert'
  | 'assign'
  | 'view_shipment'
  | 'closed'
  | 'open';

/** Status-driven primary next action for the requests operations workspace. */
export function getRequestPrimaryAction(
  status: string,
  convertedShipmentId: string | null = null
): { key: RequestPrimaryAction; label: string } {
  if (canStartReviewRequest(status)) {
    return { key: 'start_review', label: 'Start Review' };
  }
  if (canApproveOrRejectRequest(status)) {
    return { key: 'decide', label: 'Review Decision' };
  }
  if (status === 'changes_requested') {
    return { key: 'await_client', label: 'Awaiting Client' };
  }
  if (canConvertRequest(status, convertedShipmentId)) {
    return { key: 'convert', label: 'Convert to Shipment' };
  }
  if (status === 'converted') {
    return { key: 'assign', label: 'Assign Resources' };
  }
  if (status === 'rejected' || status === 'cancelled') {
    return { key: 'closed', label: 'View Request' };
  }
  return { key: 'open', label: 'Open Request' };
}

export function formatCargoSummary(request: {
  cargoType: string | null;
  cargoWeight?: string | number | null;
  packageCount?: number | null;
}): string {
  const parts: string[] = [];
  if (request.cargoType) parts.push(request.cargoType);
  if (request.cargoWeight != null && request.cargoWeight !== '') {
    parts.push(`${request.cargoWeight} kg`);
  }
  if (request.packageCount != null) {
    parts.push(`${request.packageCount} pkg`);
  }
  return parts.length > 0 ? parts.join(' · ') : 'Cargo unavailable';
}

export function formatPriceSummaryLabel(summary: {
  currency: string;
  total: number;
} | null): string {
  if (!summary) return 'Estimate unavailable';
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: summary.currency || 'USD',
      maximumFractionDigits: 2,
    }).format(summary.total);
  } catch {
    return `${summary.total} ${summary.currency}`;
  }
}
