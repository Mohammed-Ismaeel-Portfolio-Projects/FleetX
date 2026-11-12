/** Client-safe shipment types — list (C2.6-C2) + show (C2.6-C5-D3). */

export interface ClientShipmentStatus {
  id: number;
  name: string;
  type: string;
}

export interface ClientShipmentCompany {
  id: string;
  companyCode: string | null;
  companyName: string | null;
}

export interface ClientShipment {
  id: string;
  shipmentNumber: string | null;
  referenceNumber: string | null;
  priority: string | null;
  statusId: number | null;
  status: ClientShipmentStatus | null;
  pickupCity: string | null;
  pickupCountry: string | null;
  pickupCompany: string | null;
  deliveryCity: string | null;
  deliveryCountry: string | null;
  deliveryCompany: string | null;
  plannedPickupDate: string | null;
  plannedDeliveryDate: string | null;
  actualPickupDate: string | null;
  actualDeliveryDate: string | null;
  customerCompany: ClientShipmentCompany | null;
  createdAt: string | null;
  updatedAt: string | null;
}

/** Show resource allowlist cargo fields (C2.6-C5-D3). */
export interface ClientShipmentDetails extends ClientShipment {
  cargoType: string | null;
  cargoDescription: string | null;
  packageCount: number | null;
}

/** Tracking place summary — city/country/company + optional hub coordinates (C2.7-C / C5.5-C3). */
export interface ClientShipmentTrackingPlace {
  city: string | null;
  country: string | null;
  company: string | null;
  /** Hub-level coordinates from Client tracking; null when unavailable. */
  coordinates: ClientGeoCoordinates | null;
}

/**
 * Finite geographic point (C5.5-C2 / C5.5-C3).
 * Allowlist only: latitude, longitude.
 */
export interface ClientGeoCoordinates {
  latitude: number;
  longitude: number;
}

/**
 * Derived milestone (C2.7-B option B) — progress facts, not status-event history.
 * Keys: created | pickup | delivery only.
 */
export type ClientShipmentMilestoneKey = 'created' | 'pickup' | 'delivery';

export interface ClientShipmentMilestone {
  key: ClientShipmentMilestoneKey;
  occurredAt?: string | null;
  plannedAt?: string | null;
  actualAt?: string | null;
}

/**
 * Customer-safe last-known GPS projection (C4.2-A3 / C5.1).
 * Allowlist only: latitude, longitude, recordedAt — never Ops/Engine fields.
 */
export interface ClientLastKnownPosition {
  latitude: number;
  longitude: number;
  /** When the position was recorded; null if the API omitted a usable timestamp. */
  recordedAt: string | null;
}

/** Backend-derived location freshness states (C5.5-C2.3 / C5.5-C3). */
export type ClientLocationFreshnessState =
  | 'unavailable'
  | 'fresh'
  | 'stale'
  | 'very_stale';

/**
 * Location freshness projection — derived from last_known_position.recorded_at on the backend.
 * Client mapper must not invent freshness.
 */
export interface ClientLocationFreshness {
  state: ClientLocationFreshnessState;
  recordedAt: string | null;
  ageSeconds: number;
}

/**
 * Sanitized planned route geometry (C5.5-C2.4 / C5.5-C3).
 * Coordinates only — never provider, encoded geometry, or Ops metadata.
 */
export interface ClientPlannedRoute {
  coordinates: ClientGeoCoordinates[];
}

/** GET /api/v1/client/shipments/{id}/tracking — C2.7-C / C5.1 / C5.5-C3 allowlist. */
export interface ClientShipmentTracking {
  shipmentId: string;
  shipmentNumber: string | null;
  status: ClientShipmentStatus | null;
  plannedPickupDate: string | null;
  plannedDeliveryDate: string | null;
  actualPickupDate: string | null;
  actualDeliveryDate: string | null;
  origin: ClientShipmentTrackingPlace;
  destination: ClientShipmentTrackingPlace;
  customerCompany: ClientShipmentCompany | null;
  createdAt: string | null;
  milestones: ClientShipmentMilestone[];
  /** Last known position from Client tracking contract; null when unavailable/invalid. */
  lastKnownPosition: ClientLastKnownPosition | null;
  /** Backend-derived freshness; never invent Client-side. */
  locationFreshness: ClientLocationFreshness;
  /** Sanitized planned route or null when unavailable. */
  route: ClientPlannedRoute | null;
}

export interface ClientShipmentListMeta {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
  from: number | null;
  to: number | null;
}

export interface ClientShipmentListResult {
  shipments: ClientShipment[];
  meta: ClientShipmentListMeta;
}

export interface ListClientShipmentsParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  status_id?: number;
  priority?: 'low' | 'normal' | 'high' | 'urgent';
  country?: string;
  pickup_from?: string;
  pickup_to?: string;
  delivery_from?: string;
  delivery_to?: string;
  sort_by?:
    | 'shipment_number'
    | 'priority'
    | 'planned_pickup_date'
    | 'planned_delivery_date'
    | 'created_at'
    | 'updated_at';
  sort_direction?: 'asc' | 'desc';
}

/** Verification QR payload for pickup or delivery stage (CLIENT-QR-02 / CLIENT-QR-03). */
export type ClientVerificationEvent = 'pickup_verify' | 'delivery_verify';

export interface ClientVerificationQr {
  event: ClientVerificationEvent;
  qrPayload: string;
  expiresAt: string | null;
  status: 'active' | string;
}
