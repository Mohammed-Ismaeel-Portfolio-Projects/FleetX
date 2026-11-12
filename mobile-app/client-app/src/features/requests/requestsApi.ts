import { apiClient } from '../../lib/api/client';
import {
  customerHttpMessage,
  detectNetworkFailure,
} from '../../lib/ux/customerErrors';
import type {
  ClientShipmentRequest,
  ClientShipmentRequestDetails,
  ClientShipmentRequestListResult,
  ClientShipmentRequestWritePayload,
  ListClientShipmentRequestsParams,
} from './types';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[] | string>;
}

interface ClientShipmentRequestApi {
  id: string;
  request_number?: string | null;
  reference_number?: string | null;
  status_id?: number | null;
  status?: { id: number; name: string; type: string } | null;
  submission_revision?: number | null;
  cargo_type?: string | null;
  cargo_description?: string | null;
  trailer_type?: string | null;
  cargo_weight?: number | string | null;
  cargo_volume?: number | string | null;
  package_count?: number | null;
  hazardous_cargo?: boolean | null;
  temperature_controlled?: boolean | null;
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
  /** Show-only allowlist — never present on list rows. */
  review_feedback?: { message?: string | null } | null;
  linked_shipment?: {
    id?: string | null;
    shipment_number?: string | null;
    status?: { id: number; name: string; type: string } | null;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface PaginatedPayload {
  data: ClientShipmentRequestApi[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
  };
}

export class ClientShipmentRequestsApiError extends Error {
  statusCode?: number;
  isNetwork?: boolean;
  fieldErrors?: Record<string, string>;

  constructor(
    message: string,
    statusCode?: number,
    isNetwork = false,
    fieldErrors?: Record<string, string>
  ) {
    super(message);
    this.name = 'ClientShipmentRequestsApiError';
    this.statusCode = statusCode;
    this.isNetwork = isNetwork;
    this.fieldErrors = fieldErrors;
  }
}

function firstErrorMessages(
  errors: Record<string, string[] | string> | undefined
): Record<string, string> | undefined {
  if (!errors || typeof errors !== 'object') return undefined;
  const out: Record<string, string> = {};
  for (const [key, value] of Object.entries(errors)) {
    if (Array.isArray(value) && value[0]) out[key] = String(value[0]);
    else if (typeof value === 'string' && value.trim()) out[key] = value;
  }
  return Object.keys(out).length > 0 ? out : undefined;
}

function customerMessage(statusCode: number | undefined, fallback: string, isNetwork: boolean): string {
  return customerHttpMessage(statusCode, {
    isNetwork,
    resource: 'request',
    fallback,
  });
}

/**
 * Maps HTTP failures through customerHttpMessage, including
 * statusCode === 401, statusCode === 403, statusCode === 404,
 * statusCode === 409, and statusCode === 429, plus fieldErrors.
 */
function toApiError(error: unknown, fallback: string): ClientShipmentRequestsApiError {
  if (error instanceof ClientShipmentRequestsApiError) return error;
  const err = error as {
    response?: {
      status?: number;
      data?: { message?: string; errors?: Record<string, string[] | string> };
    };
    message?: string;
    code?: string;
  };

  const isNetwork = detectNetworkFailure(error);

  const statusCode = err?.response?.status;
  const serverMessage = err?.response?.data?.message || err?.message || fallback;
  const fieldErrors = firstErrorMessages(err?.response?.data?.errors);

  return new ClientShipmentRequestsApiError(
    customerMessage(statusCode, serverMessage, isNetwork),
    statusCode,
    isNetwork,
    fieldErrors
  );
}

function asNumber(value: number | string | null | undefined): number | null {
  if (value == null || value === '') return null;
  const n = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(n) ? n : null;
}

function stripTenancy<T extends Record<string, unknown>>(body: T): T {
  delete (body as { customer_company_id?: unknown }).customer_company_id;
  delete (body as { company_id?: unknown }).company_id;
  delete (body as { submitted_by_user_id?: unknown }).submitted_by_user_id;
  delete (body as { status_id?: unknown }).status_id;
  delete (body as { status?: unknown }).status;
  delete (body as { converted_shipment_id?: unknown }).converted_shipment_id;
  delete (body as { linked_shipment?: unknown }).linked_shipment;
  delete (body as { linked_shipment_id?: unknown }).linked_shipment_id;
  delete (body as { dispatcher_notes?: unknown }).dispatcher_notes;
  delete (body as { payload_json?: unknown }).payload_json;
  delete (body as { request_number?: unknown }).request_number;
  return body;
}

function mapLinkedShipment(
  linked: ClientShipmentRequestApi['linked_shipment']
): ClientShipmentRequest['linkedShipment'] {
  if (!linked || linked.id == null) return null;
  const id = String(linked.id).trim();
  if (!id) return null;
  return {
    id,
    shipmentNumber: linked.shipment_number ?? null,
    status: linked.status
      ? { id: linked.status.id, name: linked.status.name, type: linked.status.type }
      : null,
  };
}

function mapListItem(api: ClientShipmentRequestApi): ClientShipmentRequest {
  return {
    id: String(api.id),
    requestNumber: api.request_number ?? null,
    referenceNumber: api.reference_number ?? null,
    statusId: api.status_id ?? null,
    status: api.status
      ? { id: api.status.id, name: api.status.name, type: api.status.type }
      : null,
    cargoType: api.cargo_type ?? null,
    cargoDescription: api.cargo_description ?? null,
    packageCount: api.package_count ?? null,
    pickupCity: api.pickup_city ?? null,
    pickupCountry: api.pickup_country ?? null,
    pickupCompany: api.pickup_company ?? null,
    deliveryCity: api.delivery_city ?? null,
    deliveryCountry: api.delivery_country ?? null,
    deliveryCompany: api.delivery_company ?? null,
    requestedPickupDate: api.requested_pickup_date ?? null,
    requestedDeliveryDate: api.requested_delivery_date ?? null,
    createdAt: api.created_at ?? null,
    updatedAt: api.updated_at ?? null,
    linkedShipment: mapLinkedShipment(api.linked_shipment),
  };
}

/** Maps allowlisted review_feedback only — never spreads raw history. */
export function mapClientReviewFeedback(
  raw: ClientShipmentRequestApi['review_feedback']
): ClientShipmentRequestDetails['reviewFeedback'] {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }
  if (typeof raw.message !== 'string') {
    return null;
  }
  const message = raw.message.trim();
  return message ? { message } : null;
}

function mapDetails(api: ClientShipmentRequestApi): ClientShipmentRequestDetails {
  return {
    ...mapListItem(api),
    submissionRevision: api.submission_revision ?? null,
    cargoWeight: asNumber(api.cargo_weight),
    cargoVolume: asNumber(api.cargo_volume),
    trailerType: api.trailer_type ?? null,
    hazardousCargo: Boolean(api.hazardous_cargo),
    temperatureControlled: Boolean(api.temperature_controlled),
    pickupContact: api.pickup_contact ?? null,
    pickupPhone: api.pickup_phone ?? null,
    pickupAddress: api.pickup_address ?? null,
    pickupLatitude: asNumber(api.pickup_latitude),
    pickupLongitude: asNumber(api.pickup_longitude),
    deliveryContact: api.delivery_contact ?? null,
    deliveryPhone: api.delivery_phone ?? null,
    deliveryAddress: api.delivery_address ?? null,
    deliveryLatitude: asNumber(api.delivery_latitude),
    deliveryLongitude: asNumber(api.delivery_longitude),
    specialInstructions: api.special_instructions ?? null,
    clientNotes: api.client_notes ?? null,
    reviewFeedback: mapClientReviewFeedback(api.review_feedback),
  };
}

/**
 * List shipment requests for the authenticated client's linked company.
 * GET /api/v1/client/shipment-requests
 * Must never send company_id / customer_company_id / submitted_by_user_id.
 */
export async function listClientShipmentRequests(
  params: ListClientShipmentRequestsParams = {}
): Promise<ClientShipmentRequestListResult> {
  const query: Record<string, string | number> = {};
  if (params.page != null) query.page = params.page;
  if (params.per_page != null) query.per_page = params.per_page;
  if (params.status) query.status = params.status;
  // Hard guard: never allow tenancy/ownership params (do not strip allowlisted status).
  delete (query as { customer_company_id?: unknown }).customer_company_id;
  delete (query as { company_id?: unknown }).company_id;
  delete (query as { submitted_by_user_id?: unknown }).submitted_by_user_id;

  try {
    const { data } = await apiClient.get<ApiEnvelope<PaginatedPayload>>(
      '/client/shipment-requests',
      { params: query }
    );

    const payload = data?.data;
    if (!payload || !Array.isArray(payload.data) || !payload.meta) {
      throw new ClientShipmentRequestsApiError('Unexpected shipment request list response from server.');
    }

    return {
      requests: payload.data.map(mapListItem),
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
    throw toApiError(e, 'Failed to load shipment requests');
  }
}

/** GET /api/v1/client/shipment-requests/{id} */
export async function getClientShipmentRequest(id: string): Promise<ClientShipmentRequestDetails> {
  const requestId = String(id ?? '').trim();
  if (!requestId) {
    throw new ClientShipmentRequestsApiError('Request id unavailable.');
  }

  try {
    const { data } = await apiClient.get<ApiEnvelope<ClientShipmentRequestApi>>(
      `/client/shipment-requests/${encodeURIComponent(requestId)}`
    );
    const payload = data?.data;
    if (!payload || payload.id == null) {
      throw new ClientShipmentRequestsApiError('Unexpected shipment request response from server.');
    }
    return mapDetails(payload);
  } catch (e) {
    throw toApiError(e, 'Failed to load shipment request');
  }
}

/** POST /api/v1/client/shipment-requests — creates a draft. */
export async function createClientShipmentRequest(
  payload: ClientShipmentRequestWritePayload
): Promise<ClientShipmentRequestDetails> {
  const body = stripTenancy({ ...payload });

  try {
    const { data } = await apiClient.post<ApiEnvelope<ClientShipmentRequestApi>>(
      '/client/shipment-requests',
      body
    );
    const created = data?.data;
    if (!created || created.id == null) {
      throw new ClientShipmentRequestsApiError('Unexpected create response from server.');
    }
    return mapDetails(created);
  } catch (e) {
    throw toApiError(e, 'Failed to save draft');
  }
}

/** PATCH /api/v1/client/shipment-requests/{id} — draft or changes_requested only. */
export async function updateClientShipmentRequest(
  id: string,
  payload: ClientShipmentRequestWritePayload
): Promise<ClientShipmentRequestDetails> {
  const requestId = String(id ?? '').trim();
  if (!requestId) {
    throw new ClientShipmentRequestsApiError('Request id unavailable.');
  }
  const body = stripTenancy({ ...payload });

  try {
    const { data } = await apiClient.patch<ApiEnvelope<ClientShipmentRequestApi>>(
      `/client/shipment-requests/${encodeURIComponent(requestId)}`,
      body
    );
    const updated = data?.data;
    if (!updated || updated.id == null) {
      throw new ClientShipmentRequestsApiError('Unexpected update response from server.');
    }
    return mapDetails(updated);
  } catch (e) {
    throw toApiError(e, 'Failed to update request');
  }
}

/** POST /api/v1/client/shipment-requests/{id}/submit — empty body. */
export async function submitClientShipmentRequest(
  id: string
): Promise<ClientShipmentRequestDetails> {
  const requestId = String(id ?? '').trim();
  if (!requestId) {
    throw new ClientShipmentRequestsApiError('Request id unavailable.');
  }

  const endpoint = `/client/shipment-requests/${encodeURIComponent(requestId)}/submit`;
  console.log(`[SUBMIT REQUEST] POST /api/v1${endpoint}`);

  try {
    const res = await apiClient.post<ApiEnvelope<ClientShipmentRequestApi>>(
      endpoint,
      {}
    );
    console.log(`[SUBMIT RESPONSE] HTTP ${res.status}`, res.data);
    const submitted = res.data?.data;
    if (!submitted || submitted.id == null) {
      throw new ClientShipmentRequestsApiError('Unexpected submit response from server.');
    }
    return mapDetails(submitted);
  } catch (e) {
    const errObj = e as { response?: { status?: number; data?: unknown } };
    console.error(`[SUBMIT ERROR] HTTP ${errObj.response?.status || 'Network/Error'}`, errObj.response?.data || e);
    throw toApiError(e, 'Failed to submit request');
  }
}

/** POST /api/v1/client/shipment-requests/{id}/resubmit — empty body. */
export async function resubmitClientShipmentRequest(
  id: string
): Promise<ClientShipmentRequestDetails> {
  const requestId = String(id ?? '').trim();
  if (!requestId) {
    throw new ClientShipmentRequestsApiError('Request id unavailable.');
  }

  const endpoint = `/client/shipment-requests/${encodeURIComponent(requestId)}/resubmit`;
  console.log(`[RESUBMIT REQUEST] POST /api/v1${endpoint}`);

  try {
    const res = await apiClient.post<ApiEnvelope<ClientShipmentRequestApi>>(
      endpoint,
      {}
    );
    console.log(`[RESUBMIT RESPONSE] HTTP ${res.status}`, res.data);
    const resubmitted = res.data?.data;
    if (!resubmitted || resubmitted.id == null) {
      throw new ClientShipmentRequestsApiError('Unexpected resubmit response from server.');
    }
    return mapDetails(resubmitted);
  } catch (e) {
    const errObj = e as { response?: { status?: number; data?: unknown } };
    console.error(`[RESUBMIT ERROR] HTTP ${errObj.response?.status || 'Network/Error'}`, errObj.response?.data || e);
    throw toApiError(e, 'Failed to resubmit request');
  }
}
