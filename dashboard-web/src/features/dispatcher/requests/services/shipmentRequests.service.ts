import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  ShipmentRequestDetail,
  ShipmentRequestListItem,
  ShipmentRequestListMeta,
  ShipmentRequestQueryParams,
} from '../types/request.types';
import {
  mapApiRequestToDetail,
  mapApiRequestToListItem,
  type ShipmentRequestApiItem,
} from '../utils/requestMapper';

interface ApiEnvelope<T> {
  data: T;
  message?: string;
  success?: boolean;
}

export class ShipmentRequestApiError extends Error {
  statusCode?: number;
  errors?: unknown;

  constructor(message: string, statusCode?: number, errors?: unknown) {
    super(message);
    this.name = 'ShipmentRequestApiError';
    this.statusCode = statusCode;
    this.errors = errors;
  }
}

function toApiError(error: unknown, fallback: string): ShipmentRequestApiError {
  if (error instanceof ShipmentRequestApiError) return error;
  const err = error as {
    response?: { data?: { message?: string; errors?: unknown }; status?: number };
    message?: string;
  };
  return new ShipmentRequestApiError(
    err?.response?.data?.message || err?.message || fallback,
    err?.response?.status,
    err?.response?.data?.errors
  );
}

/**
 * Dispatcher Request service (C2.9-C2.3–C2.6 + INT-016/028).
 * List/show + start-review/request-changes/approve/reject/convert against existing ops endpoints.
 * No Assignment / Tracking / Shipment lifecycle mutation methods.
 */
export const shipmentRequestsService = {
  async getRequests(params?: ShipmentRequestQueryParams): Promise<{
    data: ShipmentRequestListItem[];
    meta: ShipmentRequestListMeta;
  }> {
    try {
      const response = await axiosClientWithInterceptors.get<
        ApiEnvelope<{
          data: ShipmentRequestApiItem[];
          meta: ShipmentRequestListMeta;
        }>
      >('/shipment-requests', {
        params: params ? cleanParams(params as Record<string, any>) : undefined,
      });

      const payload = response.data.data;
      return {
        data: (payload.data ?? []).map(mapApiRequestToListItem),
        meta: payload.meta ?? {
          current_page: 1,
          per_page: 15,
          total: 0,
          last_page: 1,
        },
      };
    } catch (error) {
      throw toApiError(error, 'Failed to fetch shipment requests');
    }
  },

  async getRequestById(id: string): Promise<ShipmentRequestDetail> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<ShipmentRequestApiItem>>(
        `/shipment-requests/${id}`
      );
      return mapApiRequestToDetail(response.data.data);
    } catch (error) {
      throw toApiError(error, 'Failed to fetch shipment request');
    }
  },

  /**
   * submitted → under_review. Empty body — reason is prohibited by the API.
   */
  async startReview(id: string): Promise<ShipmentRequestDetail> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<ShipmentRequestApiItem>>(
        `/shipment-requests/${id}/start-review`,
        {}
      );
      return mapApiRequestToDetail(response.data.data);
    } catch (error) {
      throw toApiError(error, 'Failed to start review');
    }
  },

  /**
   * under_review → changes_requested (reason required by API).
   */
  async requestChanges(id: string, reason: string): Promise<ShipmentRequestDetail> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<ShipmentRequestApiItem>>(
        `/shipment-requests/${id}/request-changes`,
        { reason: reason.trim() }
      );
      return mapApiRequestToDetail(response.data.data);
    } catch (error) {
      throw toApiError(error, 'Failed to request changes');
    }
  },

  /**
   * under_review → approved (optional reason).
   */
  async approveRequest(id: string, reason?: string | null): Promise<ShipmentRequestDetail> {
    try {
      const payload =
        reason && reason.trim().length > 0 ? { reason: reason.trim() } : {};
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<ShipmentRequestApiItem>>(
        `/shipment-requests/${id}/approve`,
        payload
      );
      return mapApiRequestToDetail(response.data.data);
    } catch (error) {
      throw toApiError(error, 'Failed to approve shipment request');
    }
  },

  /**
   * under_review → rejected (reason required by API).
   */
  async rejectRequest(id: string, reason: string): Promise<ShipmentRequestDetail> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<ShipmentRequestApiItem>>(
        `/shipment-requests/${id}/reject`,
        { reason: reason.trim() }
      );
      return mapApiRequestToDetail(response.data.data);
    } catch (error) {
      throw toApiError(error, 'Failed to reject shipment request');
    }
  },

  /**
   * approved → converted (+ Shipment Waiting Assignment). Empty body by API design.
   */
  async convertRequest(id: string): Promise<ShipmentRequestDetail> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<ShipmentRequestApiItem>>(
        `/shipment-requests/${id}/convert`,
        {}
      );
      return mapApiRequestToDetail(response.data.data);
    } catch (error) {
      throw toApiError(error, 'Failed to convert shipment request');
    }
  },
};
