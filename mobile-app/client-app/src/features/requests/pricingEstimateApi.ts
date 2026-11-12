import { apiClient } from '../../lib/api/client';
import {
  ClientUxCopy,
  customerHttpMessage,
  detectNetworkFailure,
} from '../../lib/ux/customerErrors';
import type {
  ClientPricingCalculatePayload,
  ClientPricingEstimate,
} from './pricingEstimateTypes';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[] | string>;
}

export class ClientPricingEstimateApiError extends Error {
  statusCode?: number;
  fieldErrors?: Record<string, string>;

  constructor(
    message: string,
    options?: { statusCode?: number; fieldErrors?: Record<string, string> }
  ) {
    super(message);
    this.name = 'ClientPricingEstimateApiError';
    this.statusCode = options?.statusCode;
    this.fieldErrors = options?.fieldErrors;
  }
}

function firstErrorMessages(
  errors: Record<string, string[] | string> | undefined
): Record<string, string> | undefined {
  if (!errors) return undefined;
  const out: Record<string, string> = {};
  for (const [key, value] of Object.entries(errors)) {
    if (Array.isArray(value) && value[0]) out[key] = String(value[0]);
    else if (typeof value === 'string' && value) out[key] = value;
  }
  return Object.keys(out).length ? out : undefined;
}

function asNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }
  return null;
}

function mapEstimate(raw: Record<string, unknown>): ClientPricingEstimate | null {
  const currency = typeof raw.currency === 'string' ? raw.currency : null;
  const total = asNumber(raw.total);
  if (!currency || total == null) return null;

  const chargesRaw = (raw.charges && typeof raw.charges === 'object'
    ? raw.charges
    : {}) as Record<string, unknown>;
  const discountRaw = (raw.discount && typeof raw.discount === 'object'
    ? raw.discount
    : {}) as Record<string, unknown>;
  const taxRaw = (raw.tax && typeof raw.tax === 'object' ? raw.tax : {}) as Record<string, unknown>;

  return {
    currency,
    distance_km: asNumber(raw.distance_km),
    charges: {
      freight: asNumber(chargesRaw.freight) ?? 0,
      weight_surcharge: asNumber(chargesRaw.weight_surcharge) ?? 0,
      waiting_charge: asNumber(chargesRaw.waiting_charge) ?? 0,
      services: asNumber(chargesRaw.services) ?? 0,
      border: asNumber(chargesRaw.border) ?? 0,
      insurance: asNumber(chargesRaw.insurance) ?? 0,
      hazmat: asNumber(chargesRaw.hazmat) ?? 0,
      heavy_reefer: asNumber(chargesRaw.heavy_reefer) ?? 0,
      priority: asNumber(chargesRaw.priority) ?? 0,
    },
    subtotal: asNumber(raw.subtotal) ?? 0,
    discount: {
      amount: asNumber(discountRaw.amount) ?? 0,
      type: typeof discountRaw.type === 'string' ? discountRaw.type : null,
      rate: asNumber(discountRaw.rate),
    },
    after_discount: asNumber(raw.after_discount) ?? 0,
    minimum_fare_adjustment: asNumber(raw.minimum_fare_adjustment) ?? 0,
    after_minimum_fare: asNumber(raw.after_minimum_fare) ?? 0,
    tax: {
      amount: asNumber(taxRaw.amount) ?? 0,
      rate: asNumber(taxRaw.rate),
    },
    total,
    service_items: Array.isArray(raw.service_items)
      ? (raw.service_items as ClientPricingEstimate['service_items'])
      : [],
    warnings: Array.isArray(raw.warnings)
      ? raw.warnings.filter((w): w is string => typeof w === 'string')
      : [],
  };
}

/**
 * POST /api/v1/client/pricing/calculate — read-only estimate.
 * Does not persist; submit recalculates authoritative pricing.
 */
export async function calculateClientPricingEstimate(
  payload: ClientPricingCalculatePayload
): Promise<ClientPricingEstimate> {
  try {
    const res = await apiClient.post<ApiEnvelope<Record<string, unknown>>>(
      '/client/pricing/calculate',
      payload
    );
    const data = res.data?.data;
    if (!data || typeof data !== 'object') {
      throw new ClientPricingEstimateApiError('Pricing estimate response was incomplete.');
    }
    const mapped = mapEstimate(data);
    if (!mapped) {
      throw new ClientPricingEstimateApiError('Pricing estimate response was incomplete.');
    }
    return mapped;
  } catch (e) {
    if (e instanceof ClientPricingEstimateApiError) throw e;
    const network = detectNetworkFailure(e);
    if (network) {
      throw new ClientPricingEstimateApiError(ClientUxCopy.networkBodyShort);
    }
    const err = e as {
      response?: { status?: number; data?: { message?: string; errors?: Record<string, string[] | string> } };
      message?: string;
    };
    const status = err.response?.status;
    const fieldErrors = firstErrorMessages(err.response?.data?.errors);
    const serverMessage = err.response?.data?.message;
    const fallback = serverMessage || err.message || 'Unable to calculate the estimated cost.';
    // Keep backend semantics; soften configuration terminology for Client Review UX.
    const presentation =
      typeof serverMessage === 'string' &&
      /customer type is not configured/i.test(serverMessage)
        ? 'Estimated cost unavailable. Your company\'s pricing configuration is not available yet. Please contact your administrator.'
        : customerHttpMessage(status, {
            resource: 'request',
            fallback,
          });
    throw new ClientPricingEstimateApiError(presentation, { statusCode: status, fieldErrors });
  }
}
