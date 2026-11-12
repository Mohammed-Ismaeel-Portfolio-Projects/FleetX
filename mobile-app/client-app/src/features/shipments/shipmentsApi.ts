import { apiClient } from '../../lib/api/client';
import {
  customerHttpMessage,
  detectNetworkFailure,
} from '../../lib/ux/customerErrors';
import type {
  ClientGeoCoordinates,
  ClientLastKnownPosition,
  ClientLocationFreshness,
  ClientLocationFreshnessState,
  ClientPlannedRoute,
  ClientShipment,
  ClientShipmentDetails,
  ClientShipmentListResult,
  ClientShipmentMilestone,
  ClientShipmentMilestoneKey,
  ClientShipmentTracking,
  ClientVerificationEvent,
  ClientVerificationQr,
  ListClientShipmentsParams,
} from './types';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

interface ClientShipmentApi {
  id: string;
  shipment_number?: string | null;
  reference_number?: string | null;
  priority?: string | null;
  status_id?: number | null;
  status?: { id: number; name: string; type: string } | null;
  pickup_city?: string | null;
  pickup_country?: string | null;
  pickup_company?: string | null;
  delivery_city?: string | null;
  delivery_country?: string | null;
  delivery_company?: string | null;
  planned_pickup_date?: string | null;
  planned_delivery_date?: string | null;
  actual_pickup_date?: string | null;
  actual_delivery_date?: string | null;
  cargo_type?: string | null;
  cargo_description?: string | null;
  package_count?: number | null;
  customer_company?: {
    id: string;
    company_code?: string | null;
    company_name?: string | null;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface PaginatedPayload {
  data: ClientShipmentApi[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
  };
}

export class ClientShipmentsApiError extends Error {
  statusCode?: number;
  /** True when the failure looks like offline / no response (not an HTTP status). */
  isNetwork?: boolean;

  constructor(message: string, statusCode?: number, isNetwork = false) {
    super(message);
    this.name = 'ClientShipmentsApiError';
    this.statusCode = statusCode;
    this.isNetwork = isNetwork;
  }
}

function messageFromResponseData(data: unknown): string | null {
  if (data && typeof data === 'object' && 'message' in data) {
    const message = (data as { message?: unknown }).message;
    if (typeof message === 'string' && message.trim()) {
      return message.trim();
    }
  }

  if (typeof ArrayBuffer !== 'undefined' && (data instanceof ArrayBuffer || ArrayBuffer.isView(data))) {
    try {
      const bytes =
        data instanceof ArrayBuffer
          ? new Uint8Array(data)
          : new Uint8Array(data.buffer, data.byteOffset, data.byteLength);
      const text = new TextDecoder().decode(bytes);
      const parsed = JSON.parse(text) as { message?: unknown };
      if (typeof parsed?.message === 'string' && parsed.message.trim()) {
        return parsed.message.trim();
      }
    } catch {
      return null;
    }
  }

  return null;
}

function toApiError(error: unknown, fallback: string): ClientShipmentsApiError {
  if (error instanceof ClientShipmentsApiError) return error;
  const err = error as {
    response?: { status?: number; data?: unknown };
    message?: string;
    code?: string;
  };

  const isNetwork = detectNetworkFailure(error);
  const statusCode = err?.response?.status;
  const serverMessage = messageFromResponseData(err?.response?.data) || fallback;

  return new ClientShipmentsApiError(
    customerHttpMessage(statusCode, {
      isNetwork,
      resource: 'shipment',
      fallback: serverMessage,
    }),
    statusCode,
    isNetwork
  );
}

function mapShipmentBase(api: ClientShipmentApi): ClientShipment {
  return {
    id: String(api.id),
    shipmentNumber: api.shipment_number ?? null,
    referenceNumber: api.reference_number ?? null,
    priority: api.priority ?? null,
    statusId: api.status_id ?? null,
    status: api.status
      ? {
          id: api.status.id,
          name: api.status.name,
          type: api.status.type,
        }
      : null,
    pickupCity: api.pickup_city ?? null,
    pickupCountry: api.pickup_country ?? null,
    pickupCompany: api.pickup_company ?? null,
    deliveryCity: api.delivery_city ?? null,
    deliveryCountry: api.delivery_country ?? null,
    deliveryCompany: api.delivery_company ?? null,
    plannedPickupDate: api.planned_pickup_date ?? null,
    plannedDeliveryDate: api.planned_delivery_date ?? null,
    actualPickupDate: api.actual_pickup_date ?? null,
    actualDeliveryDate: api.actual_delivery_date ?? null,
    customerCompany: api.customer_company
      ? {
          id: String(api.customer_company.id),
          companyCode: api.customer_company.company_code ?? null,
          companyName: api.customer_company.company_name ?? null,
        }
      : null,
    createdAt: api.created_at ?? null,
    updatedAt: api.updated_at ?? null,
  };
}

function mapShipment(api: ClientShipmentApi): ClientShipment {
  return mapShipmentBase(api);
}

function mapShipmentDetails(api: ClientShipmentApi): ClientShipmentDetails {
  return {
    ...mapShipmentBase(api),
    cargoType: api.cargo_type ?? null,
    cargoDescription: api.cargo_description ?? null,
    packageCount: api.package_count ?? null,
  };
}

/**
 * List shipments for the authenticated client's linked company.
 * MUST call GET /api/v1/client/shipments only — never ops /shipments.
 * Must never send customer_company_id or company_id (tenancy is server-side).
 */
export async function listClientShipments(
  params: ListClientShipmentsParams = {}
): Promise<ClientShipmentListResult> {
  const query: Record<string, string | number> = {};

  if (params.page != null) query.page = params.page;
  if (params.per_page != null) query.per_page = params.per_page;
  if (params.search) query.search = params.search;
  if (params.status) query.status = params.status;
  if (params.status_id != null) query.status_id = params.status_id;
  if (params.priority) query.priority = params.priority;
  if (params.country) query.country = params.country;
  if (params.pickup_from) query.pickup_from = params.pickup_from;
  if (params.pickup_to) query.pickup_to = params.pickup_to;
  if (params.delivery_from) query.delivery_from = params.delivery_from;
  if (params.delivery_to) query.delivery_to = params.delivery_to;
  if (params.sort_by) query.sort_by = params.sort_by;
  if (params.sort_direction) query.sort_direction = params.sort_direction;

  // Hard guard: never allow tenancy params even if callers pass extras later.
  delete (query as { customer_company_id?: unknown }).customer_company_id;
  delete (query as { company_id?: unknown }).company_id;

  try {
    const { data } = await apiClient.get<ApiEnvelope<PaginatedPayload>>('/client/shipments', {
      params: query,
    });

    const payload = data?.data;
    if (!payload || !Array.isArray(payload.data) || !payload.meta) {
      throw new ClientShipmentsApiError('Unexpected shipment list response from server.');
    }

    return {
      shipments: payload.data.map(mapShipment),
      meta: {
        currentPage: payload.meta.current_page,
        perPage: payload.meta.per_page,
        total: payload.meta.total,
        lastPage: payload.meta.last_page,
        from: payload.meta.from ?? null,
        to: payload.meta.to ?? null,
      },
    };
  } catch (e) {
    throw toApiError(e, 'Failed to load shipments');
  }
}

/**
 * Show one shipment for the authenticated client's linked company.
 * MUST call GET /api/v1/client/shipments/{id} only — never ops /shipments/{id}.
 * Must never send customer_company_id or company_id (tenancy is server-side).
 */
export async function getClientShipment(shipmentId: string): Promise<ClientShipmentDetails> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const { data } = await apiClient.get<ApiEnvelope<ClientShipmentApi>>(
      `/client/shipments/${encodeURIComponent(id)}`
    );

    const payload = data?.data;
    if (!payload || payload.id == null) {
      throw new ClientShipmentsApiError('Unexpected shipment details response from server.');
    }

    return mapShipmentDetails(payload);
  } catch (e) {
    throw toApiError(e, 'Failed to load shipment details');
  }
}

interface ClientShipmentTrackingApi {
  shipment_id: string;
  shipment_number?: string | null;
  status?: { id: number; name: string; type: string } | null;
  planned_pickup_date?: string | null;
  planned_delivery_date?: string | null;
  actual_pickup_date?: string | null;
  actual_delivery_date?: string | null;
  origin?: {
    city?: string | null;
    country?: string | null;
    company?: string | null;
    coordinates?: unknown;
  } | null;
  destination?: {
    city?: string | null;
    country?: string | null;
    company?: string | null;
    coordinates?: unknown;
  } | null;
  customer_company?: {
    id: string;
    company_code?: string | null;
    company_name?: string | null;
  } | null;
  created_at?: string | null;
  milestones?: Array<{
    key?: string;
    occurred_at?: string | null;
    planned_at?: string | null;
    actual_at?: string | null;
  }> | null;
  last_known_position?: unknown;
  location_freshness?: unknown;
  route?: unknown;
}

const MILESTONE_KEYS = new Set<ClientShipmentMilestoneKey>(['created', 'pickup', 'delivery']);

function mapMilestone(raw: {
  key?: string;
  occurred_at?: string | null;
  planned_at?: string | null;
  actual_at?: string | null;
}): ClientShipmentMilestone | null {
  const key = String(raw?.key || '').trim() as ClientShipmentMilestoneKey;
  if (!MILESTONE_KEYS.has(key)) return null;
  if (key === 'created') {
    return { key, occurredAt: raw.occurred_at ?? null };
  }
  return {
    key,
    plannedAt: raw.planned_at ?? null,
    actualAt: raw.actual_at ?? null,
  };
}

function asFiniteNumber(value: unknown): number | null {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }
  return null;
}

/**
 * C5.1 — allowlist mapper for Client last_known_position.
 * Reads only latitude / longitude / recorded_at. Rejects out-of-range and non-finite coords.
 */
export function mapClientLastKnownPosition(raw: unknown): ClientLastKnownPosition | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }

  const record = raw as {
    latitude?: unknown;
    longitude?: unknown;
    recorded_at?: unknown;
  };

  const latitude = asFiniteNumber(record.latitude);
  const longitude = asFiniteNumber(record.longitude);
  if (latitude == null || longitude == null) {
    return null;
  }
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
    return null;
  }

  let recordedAt: string | null = null;
  if (typeof record.recorded_at === 'string') {
    const trimmed = record.recorded_at.trim();
    recordedAt = trimmed ? trimmed : null;
  }

  return { latitude, longitude, recordedAt };
}

/**
 * C5.5-C3 — allowlist mapper for origin/destination hub coordinates.
 * Does not invent coordinates from city names.
 */
export function mapClientEndpointCoordinates(raw: unknown): ClientGeoCoordinates | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }

  const record = raw as {
    latitude?: unknown;
    longitude?: unknown;
  };

  const latitude = asFiniteNumber(record.latitude);
  const longitude = asFiniteNumber(record.longitude);
  if (latitude == null || longitude == null) {
    return null;
  }
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
    return null;
  }

  return { latitude, longitude };
}

const LOCATION_FRESHNESS_STATES = new Set<ClientLocationFreshnessState>([
  'unavailable',
  'fresh',
  'stale',
  'very_stale',
]);

function unavailableFreshness(): ClientLocationFreshness {
  return {
    state: 'unavailable',
    recordedAt: null,
    ageSeconds: 0,
  };
}

/**
 * C5.5-C3 — allowlist mapper for location_freshness.
 * Fail-closed to unavailable. Does not invent freshness or ETA.
 */
export function mapClientLocationFreshness(raw: unknown): ClientLocationFreshness {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return unavailableFreshness();
  }

  const record = raw as {
    state?: unknown;
    recorded_at?: unknown;
    age_seconds?: unknown;
  };

  if (typeof record.state !== 'string' || !LOCATION_FRESHNESS_STATES.has(record.state as ClientLocationFreshnessState)) {
    return unavailableFreshness();
  }

  const state = record.state as ClientLocationFreshnessState;

  let ageSeconds = 0;
  if (typeof record.age_seconds === 'number' && Number.isFinite(record.age_seconds)) {
    ageSeconds = Math.max(0, Math.trunc(record.age_seconds));
  } else if (typeof record.age_seconds === 'string' && record.age_seconds.trim() !== '') {
    const n = Number(record.age_seconds);
    ageSeconds = Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0;
  }

  let recordedAt: string | null = null;
  if (typeof record.recorded_at === 'string') {
    const trimmed = record.recorded_at.trim();
    recordedAt = trimmed ? trimmed : null;
  }

  if (state === 'unavailable') {
    return unavailableFreshness();
  }

  if (recordedAt == null) {
    return unavailableFreshness();
  }

  return {
    state,
    recordedAt,
    ageSeconds,
  };
}

/**
 * C5.5-C3 — allowlist mapper for sanitized planned route.
 * Fail-closed: any invalid point or fewer than 2 points → null.
 * Does not generate route from origin/destination.
 */
export function mapClientPlannedRoute(raw: unknown): ClientPlannedRoute | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }

  const record = raw as { coordinates?: unknown };
  if (!Array.isArray(record.coordinates) || record.coordinates.length < 2) {
    return null;
  }

  const coordinates: ClientGeoCoordinates[] = [];
  for (const point of record.coordinates) {
    const mapped = mapClientEndpointCoordinates(point);
    if (mapped == null) {
      return null;
    }
    coordinates.push(mapped);
  }

  if (coordinates.length < 2) {
    return null;
  }

  return { coordinates };
}

/** Presentation-only recorded_at display — never invents a timestamp. */
export function formatLastKnownRecordedAt(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;

  const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(trimmed)
    ? trimmed.replace(' ', 'T')
    : trimmed;
  const d = new Date(normalized);
  if (Number.isNaN(d.getTime())) return null;

  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * C5.5-C3 — full Client tracking allowlist mapper (API → domain).
 * Does not invent ETA, coordinates, route geometry, driver, or vehicle.
 */
export function mapClientShipmentTracking(api: ClientShipmentTrackingApi): ClientShipmentTracking {
  const milestones = Array.isArray(api.milestones)
    ? api.milestones.map(mapMilestone).filter((m): m is ClientShipmentMilestone => m != null)
    : [];

  return {
    shipmentId: String(api.shipment_id),
    shipmentNumber: api.shipment_number ?? null,
    status: api.status
      ? {
          id: api.status.id,
          name: api.status.name,
          type: api.status.type,
        }
      : null,
    plannedPickupDate: api.planned_pickup_date ?? null,
    plannedDeliveryDate: api.planned_delivery_date ?? null,
    actualPickupDate: api.actual_pickup_date ?? null,
    actualDeliveryDate: api.actual_delivery_date ?? null,
    origin: {
      city: api.origin?.city ?? null,
      country: api.origin?.country ?? null,
      company: api.origin?.company ?? null,
      coordinates: mapClientEndpointCoordinates(api.origin?.coordinates ?? null),
    },
    destination: {
      city: api.destination?.city ?? null,
      country: api.destination?.country ?? null,
      company: api.destination?.company ?? null,
      coordinates: mapClientEndpointCoordinates(api.destination?.coordinates ?? null),
    },
    customerCompany: api.customer_company
      ? {
          id: String(api.customer_company.id),
          companyCode: api.customer_company.company_code ?? null,
          companyName: api.customer_company.company_name ?? null,
        }
      : null,
    createdAt: api.created_at ?? null,
    milestones,
    lastKnownPosition: mapClientLastKnownPosition(api.last_known_position ?? null),
    locationFreshness: mapClientLocationFreshness(api.location_freshness ?? null),
    route: mapClientPlannedRoute(api.route ?? null),
  };
}

/**
 * Client shipment progress (status + derived milestones + C5.5 map contract fields).
 * MUST call GET /api/v1/client/shipments/{id}/tracking only.
 * Never ops tracking logs, tracking states, live fleet, route planners, or dashboard.
 * Must never send company_id or customer_company_id.
 */
export async function getClientShipmentTracking(
  shipmentId: string
): Promise<ClientShipmentTracking> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const { data } = await apiClient.get<ApiEnvelope<ClientShipmentTrackingApi>>(
      `/client/shipments/${encodeURIComponent(id)}/tracking`
    );

    const payload = data?.data;
    if (!payload || payload.shipment_id == null) {
      throw new ClientShipmentsApiError('Unexpected shipment tracking response from server.');
    }

    return mapClientShipmentTracking(payload);
  } catch (e) {
    throw toApiError(e, 'Failed to load shipment progress');
  }
}

export function formatPlaceSummary(
  company: string | null,
  city: string | null,
  country: string | null
): string {
  const parts = [company, city, country].filter((p) => Boolean(p && String(p).trim()));
  return parts.length > 0 ? parts.join(', ') : 'Unavailable';
}

/** City + country only (details route display). */
export function formatCityCountry(
  city: string | null | undefined,
  country: string | null | undefined
): string {
  const parts = [city, country].filter((p) => Boolean(p && String(p).trim()));
  return parts.length > 0 ? parts.join(', ') : 'Unavailable';
}

/**
 * Client-safe POD availability. Never reads DeliveryProof fields or storage keys.
 * GET /api/v1/client/shipments/{id}/pod/status only.
 */
export async function getClientShipmentPodStatus(shipmentId: string): Promise<boolean> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const { data } = await apiClient.get<ApiEnvelope<{ available?: boolean }>>(
      `/client/shipments/${encodeURIComponent(id)}/pod/status`
    );
    return data?.data?.available === true;
  } catch (e) {
    throw toApiError(e, 'Failed to check proof of delivery');
  }
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  const chunkSize = 0x2000;
  let binary = '';
  for (let i = 0; i < bytes.length; i += chunkSize) {
    const chunk = bytes.subarray(i, i + chunkSize);
    binary += String.fromCharCode.apply(null, Array.from(chunk));
  }
  if (typeof btoa !== 'function') {
    throw new ClientShipmentsApiError('Unable to display proof of delivery.');
  }
  return btoa(binary);
}

/**
 * Retrieve the authorized POD image as a data URI.
 * GET /api/v1/client/shipments/{id}/pod — never ops/storage URLs or object keys.
 */
export async function getClientShipmentPodFile(
  shipmentId: string
): Promise<{ uri: string; mime: string }> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const response = await apiClient.get<ArrayBuffer>(
      `/client/shipments/${encodeURIComponent(id)}/pod`,
      {
        responseType: 'arraybuffer',
        headers: { Accept: '*/*' },
      }
    );

    const mimeHeader = String(response.headers?.['content-type'] || 'image/jpeg')
      .split(';')[0]
      .trim();
    const mime = mimeHeader.startsWith('image/') ? mimeHeader : 'image/jpeg';
    const uri = `data:${mime};base64,${arrayBufferToBase64(response.data)}`;
    return { uri, mime };
  } catch (e) {
    throw toApiError(e, 'Failed to load proof of delivery');
  }
}

interface ApiVerificationQrData {
  event?: string | null;
  qr_payload?: string | null;
  expires_at?: string | null;
  status?: string | null;
}

/**
 * GET /api/v1/client/shipments/{id}/verification-qr (CLIENT-QR-02 / CLIENT-QR-03).
 * Returns active verification QR payload for pickup or delivery, or null when inactive.
 */
export async function getClientShipmentVerificationQr(
  shipmentId: string
): Promise<ClientVerificationQr | null> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const response = await apiClient.get<ApiEnvelope<ApiVerificationQrData | null>>(
      `/client/shipments/${encodeURIComponent(id)}/verification-qr`
    );

    const raw = response.data?.data;
    if (!raw || typeof raw !== 'object' || !raw.qr_payload || !raw.event) {
      return null;
    }

    return {
      event: raw.event === 'delivery_verify' ? 'delivery_verify' : 'pickup_verify',
      qrPayload: String(raw.qr_payload),
      expiresAt: raw.expires_at ? String(raw.expires_at) : null,
      status: raw.status ? String(raw.status) : 'active',
    };
  } catch (e) {
    throw toApiError(e, 'Could not retrieve verification QR code');
  }
}

export interface ClientDeliveryProofData {
  shipmentId: string;
  receiver: {
    name: string | null;
    role: string | null;
  };
  condition: {
    status: 'good' | 'damaged' | 'partial' | 'refused' | string;
  };
  notes: string | null;
  location: {
    latitude: number | null;
    longitude: number | null;
  };
  deliveredAt: string | null;
  driver: {
    id: number;
    name: string;
  } | null;
  photo: {
    available: boolean;
    url: string | null;
  };
  signature: {
    available: boolean;
    url: string | null;
    strokes: Array<{ points: Array<{ x: number; y: number }> }> | null;
  };
}

/**
 * GET /api/v1/client/shipments/{id}/delivery-proof (POD-04).
 * Returns complete delivery proof evidence metadata for owned shipment.
 */
export async function getClientShipmentDeliveryProof(
  shipmentId: string
): Promise<ClientDeliveryProofData | null> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const { data } = await apiClient.get<ApiEnvelope<any>>(
      `/client/shipments/${encodeURIComponent(id)}/delivery-proof`
    );

    const raw = data?.data;
    if (!raw) return null;

    return {
      shipmentId: raw.shipment_id,
      receiver: {
        name: raw.receiver?.name ?? null,
        role: raw.receiver?.role ?? null,
      },
      condition: {
        status: raw.condition?.status ?? 'good',
      },
      notes: raw.notes ?? null,
      location: {
        latitude: raw.location?.latitude ?? null,
        longitude: raw.location?.longitude ?? null,
      },
      deliveredAt: raw.delivered_at ?? null,
      driver: raw.driver ? { id: raw.driver.id, name: raw.driver.name } : null,
      photo: {
        available: Boolean(raw.photo?.available),
        url: raw.photo?.url ?? null,
      },
      signature: {
        available: Boolean(raw.signature?.available),
        url: raw.signature?.url ?? null,
        strokes: raw.signature?.strokes ?? null,
      },
    };
  } catch (e) {
    throw toApiError(e, 'Could not retrieve delivery proof');
  }
}

/**
 * GET /api/v1/client/shipments/{id}/delivery-proof/certificate (POD-05).
 * Downloads authorized PDF certificate file for client's owned shipment.
 */
export async function downloadClientShipmentCertificate(
  shipmentId: string
): Promise<{ uri: string; filename: string }> {
  const id = String(shipmentId || '').trim();
  if (!id) {
    throw new ClientShipmentsApiError('Shipment id unavailable.');
  }

  try {
    const response = await apiClient.get<ArrayBuffer>(
      `/client/shipments/${encodeURIComponent(id)}/delivery-proof/certificate`,
      {
        responseType: 'arraybuffer',
        headers: { Accept: 'application/pdf' },
      }
    );

    const base64 = arrayBufferToBase64(response.data);
    const uri = `data:application/pdf;base64,${base64}`;
    return { uri, filename: `POD-Certificate-${id}.pdf` };
  } catch (e) {
    throw toApiError(e, 'Failed to download certificate');
  }
}
