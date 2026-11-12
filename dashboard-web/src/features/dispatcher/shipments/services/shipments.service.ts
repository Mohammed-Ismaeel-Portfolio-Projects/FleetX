import { axiosClientWithInterceptors } from '../../../../core/api';
import type { Shipment } from '../types/shipment.types';
import { mapApiShipmentToShipment, type ShipmentApiResponse } from '../utils/shipmentMapper';

interface ApiEnvelope<T> {
  data: T;
  message: string;
}

class ShipmentApiError extends Error {
  statusCode?: number;

  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'ShipmentApiError';
    this.statusCode = statusCode;
  }
}

function toShipmentApiError(error: any, defaultMessage: string): ShipmentApiError {
  if (error instanceof ShipmentApiError) return error;

  const message = error?.response?.data?.message || error?.message || defaultMessage;
  const statusCode = error?.response?.status;

  return new ShipmentApiError(message, statusCode);
}

/**
 * Dispatcher Shipment service — read paths only (C2.9-C2.7).
 * No status mutation, assignment mutation, or convert endpoints.
 */
export const shipmentsService = {
  async getShipments(params?: {
    search?: string;
    status_id?: string;
    status?: string;
    priority?: string;
    customer_company_id?: string;
    country?: string;
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_direction?: string;
  }): Promise<{ data: Shipment[]; meta: any }> {
    try {
      const response = await axiosClientWithInterceptors.get<
        ApiEnvelope<{
          data: ShipmentApiResponse[];
          meta: any;
        }>
      >('/shipments', { params });

      const shipments = response.data.data.data.map(mapApiShipmentToShipment);

      return {
        data: shipments,
        meta: response.data.data.meta,
      };
    } catch (error) {
      throw toShipmentApiError(error, 'Failed to fetch shipments');
    }
  },

  async getShipmentById(id: string): Promise<Shipment | undefined> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<ShipmentApiResponse>>(
        `/shipments/${id}`
      );
      return mapApiShipmentToShipment(response.data.data);
    } catch (error) {
      throw toShipmentApiError(error, 'Failed to fetch shipment');
    }
  },

  async getShipmentKPIs(): Promise<{
    total_fleet: number;
    pending: number;
    assigned: number;
    in_transit: number;
    delivered: number;
    delayed: number;
    urgent: number;
    critical: number;
    pending_percentage: number;
    assigned_percentage: number;
    in_transit_percentage: number;
    delivered_percentage: number;
    delayed_percentage: number;
  }> {
    try {
      const response = await axiosClientWithInterceptors.get<
        ApiEnvelope<{
          total_fleet: number;
          pending: number;
          assigned: number;
          in_transit: number;
          delivered: number;
          delayed: number;
          urgent: number;
          critical: number;
          pending_percentage: number;
          assigned_percentage: number;
          in_transit_percentage: number;
          delivered_percentage: number;
          delayed_percentage: number;
        }>
      >('/shipments/kpis');
      return response.data.data;
    } catch (error) {
      throw toShipmentApiError(error, 'Failed to fetch shipment KPIs');
    }
  },
};
