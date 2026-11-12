import { apiClient } from '../../lib/api/client';
import {
  customerHttpMessage,
  detectNetworkFailure,
} from '../../lib/ux/customerErrors';
import { ClientShipmentRequestsApiError } from './requestsApi';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

export type GeocodeErrorCode =
  | 'RATE_LIMITED'
  | 'PROVIDER_UNAVAILABLE'
  | 'NETWORK_ERROR'
  | 'UNKNOWN_ERROR';

export class GeocodeSearchError extends Error {
  readonly code: GeocodeErrorCode;
  readonly statusCode?: number;
  readonly isNetwork: boolean;

  constructor(
    message: string,
    code: GeocodeErrorCode,
    statusCode?: number,
    isNetwork = false
  ) {
    super(message);
    this.name = 'GeocodeSearchError';
    this.code = code;
    this.statusCode = statusCode;
    this.isNetwork = isNetwork;
  }
}

export interface GeocodeLocationResult {
  latitude: number;
  longitude: number;
  label: string | null;
  city: string | null;
  country: string | null;
}

function asFiniteNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim()) {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }
  return null;
}

function asNullableString(value: unknown): string | null {
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();
  return trimmed ? trimmed : null;
}

function mapLocation(raw: unknown, fallbackLat: number, fallbackLng: number): GeocodeLocationResult {
  const record = raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {};
  return {
    latitude: asFiniteNumber(record.latitude) ?? fallbackLat,
    longitude: asFiniteNumber(record.longitude) ?? fallbackLng,
    label: asNullableString(record.label),
    city: asNullableString(record.city),
    country: asNullableString(record.country),
  };
}

function toGeocodeError(error: unknown, fallback: string): ClientShipmentRequestsApiError {
  if (error instanceof ClientShipmentRequestsApiError) return error;
  const err = error as {
    response?: { status?: number; data?: { message?: string } };
    message?: string;
  };
  const isNetwork = detectNetworkFailure(error);
  const statusCode = err?.response?.status;
  const serverMessage = err?.response?.data?.message || err?.message || fallback;
  return new ClientShipmentRequestsApiError(
    customerHttpMessage(statusCode, { isNetwork, resource: 'request', fallback: serverMessage }),
    statusCode,
    isNetwork
  );
}

/**
 * POST /api/v1/client/geocode/reverse
 * Failure must not reject a valid coordinate — callers should catch and keep the pin.
 */
export async function reverseGeocodeLocation(
  latitude: number,
  longitude: number
): Promise<GeocodeLocationResult> {
  try {
    const { data } = await apiClient.post<ApiEnvelope<GeocodeLocationResult>>(
      '/client/geocode/reverse',
      { latitude, longitude }
    );
    return mapLocation(data?.data, latitude, longitude);
  } catch (error) {
    throw toGeocodeError(error, 'Could not resolve that location name.');
  }
}

/** POST /api/v1/client/geocode/search — used only to center the picker, not to persist. */
export async function searchGeocodeLocations(query: string): Promise<GeocodeLocationResult[]> {
  const trimmed = query.trim();
  if (trimmed.length < 2) return [];

  try {
    const { data } = await apiClient.post<ApiEnvelope<{ results?: unknown[] }>>(
      '/client/geocode/search',
      { query: trimmed }
    );
    const results = data?.data?.results;
    if (!Array.isArray(results)) return [];
    return results
      .map((item) => mapLocation(item, Number.NaN, Number.NaN))
      .filter((item) => Number.isFinite(item.latitude) && Number.isFinite(item.longitude));
  } catch (error) {
    const isNetwork = detectNetworkFailure(error);
    const err = error as {
      response?: {
        status?: number;
        data?: {
          message?: string;
          errors?: { status_code?: string };
        };
      };
      message?: string;
    };
    const statusCode = err?.response?.status;
    const backendCode = err?.response?.data?.errors?.status_code;
    const serverMessage = err?.response?.data?.message;

    if (isNetwork) {
      throw new GeocodeSearchError(
        'Network connection unavailable. You can place the pickup pin directly on the map.',
        'NETWORK_ERROR',
        undefined,
        true
      );
    }
    if (statusCode === 429 || backendCode === 'RATE_LIMITED') {
      throw new GeocodeSearchError(
        'Location search is temporarily unavailable. You can place the pickup pin directly on the map.',
        'RATE_LIMITED',
        429
      );
    }
    if (statusCode === 503 || backendCode === 'PROVIDER_UNAVAILABLE') {
      throw new GeocodeSearchError(
        'Location search service is temporarily unavailable. You can place the pickup pin directly on the map.',
        'PROVIDER_UNAVAILABLE',
        503
      );
    }

    throw new GeocodeSearchError(
      serverMessage || 'Could not search locations. You can place the pickup pin directly on the map.',
      'UNKNOWN_ERROR',
      statusCode
    );
  }
}
