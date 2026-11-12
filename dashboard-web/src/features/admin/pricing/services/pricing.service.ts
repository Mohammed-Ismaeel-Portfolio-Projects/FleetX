import axios from 'axios';
import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  PricingApiErrorPayload,
  PricingEstimateRequest,
  PricingEstimateResponse,
  PricingPolicy,
  PricingPolicyInput,
  PricingPolicySummary,
} from '../types/pricing.types';
import type { PricingPolicyTimeline } from '../components/PolicyTimelinePanel';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  errors?: PricingApiErrorPayload;
}

export class PricingApiError extends Error {
  status?: number;
  errors?: PricingApiErrorPayload;

  constructor(message: string, status?: number, errors?: PricingApiErrorPayload) {
    super(message);
    this.name = 'PricingApiError';
    this.status = status;
    this.errors = errors;
  }
}

function toPricingApiError(error: unknown, fallback: string): PricingApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: PricingApiErrorPayload;
    } | undefined;

    return new PricingApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors,
    );
  }

  return new PricingApiError(error instanceof Error ? error.message : fallback);
}

export const pricingService = {
  async getPricingPolicies(): Promise<PricingPolicySummary[]> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PricingPolicySummary[]>>(
        '/admin/pricing/policies',
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to load pricing policies.');
    }
  },

  async getActivePricingPolicy(): Promise<PricingPolicy> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PricingPolicy>>(
        '/admin/pricing/policies/active',
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to load the active pricing policy.');
    }
  },

  async getPricingPolicy(id: number): Promise<PricingPolicy> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PricingPolicy>>(
        `/admin/pricing/policies/${id}`,
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to load the pricing policy.');
    }
  },

  async calculatePricing(payload: PricingEstimateRequest): Promise<PricingEstimateResponse> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<PricingEstimateResponse>>(
        '/pricing/calculate',
        payload,
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to calculate pricing estimate.');
    }
  },

  async getPricingPolicyTimeline(): Promise<PricingPolicyTimeline> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PricingPolicyTimeline>>(
        '/admin/pricing/policies/timeline',
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to load the pricing policy timeline.');
    }
  },

  async createPricingPolicy(payload: PricingPolicyInput): Promise<PricingPolicy> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<PricingPolicy>>(
        '/admin/pricing/policies',
        payload,
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to create pricing policy.');
    }
  },

  async updatePricingPolicy(id: number, payload: PricingPolicyInput): Promise<PricingPolicy> {
    try {
      const response = await axiosClientWithInterceptors.put<ApiEnvelope<PricingPolicy>>(
        `/admin/pricing/policies/${id}`,
        payload,
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to update pricing policy.');
    }
  },

  async activatePricingPolicy(id: number): Promise<PricingPolicy> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<PricingPolicy>>(
        `/admin/pricing/policies/${id}/activate`,
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to activate pricing policy.');
    }
  },

  async archivePricingPolicy(id: number): Promise<PricingPolicy> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<PricingPolicy>>(
        `/admin/pricing/policies/${id}/archive`,
      );
      return response.data.data;
    } catch (error) {
      throw toPricingApiError(error, 'Unable to archive pricing policy.');
    }
  },
};
