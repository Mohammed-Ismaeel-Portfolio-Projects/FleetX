import axios from 'axios';
import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  Shipment,
  ShipmentCollection,
  ShipmentQueryParams,
  ShipmentValidationError,
} from '../types/shipment.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export class ShipmentApiError extends Error {
  status?: number;
  errors?: ShipmentValidationError[];

  constructor(message: string, status?: number, errors?: ShipmentValidationError[]) {
    super(message);
    this.name = 'ShipmentApiError';
    this.status = status;
    this.errors = errors;
  }
}

function toShipmentApiError(error: unknown, fallback: string): ShipmentApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: ShipmentValidationError[];
    } | undefined;

    return new ShipmentApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors
    );
  }

  return new ShipmentApiError(error instanceof Error ? error.message : fallback);
}

export const shipmentsService = {
  async getShipments(params?: ShipmentQueryParams): Promise<ShipmentCollection> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<ShipmentCollection>>('/shipments', {
        params: params ? cleanParams(params) : undefined,
      });
      return response.data.data;
    } catch (error) {
      throw toShipmentApiError(error, 'Failed to fetch shipments');
    }
  },

  async getShipment(id: string): Promise<Shipment> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<Shipment>>(`/shipments/${id}`);
      return response.data.data;
    } catch (error) {
      throw toShipmentApiError(error, 'Failed to fetch shipment');
    }
  },

  async getShipmentQueue(): Promise<Shipment[]> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<Shipment[]>>('/shipments/queue');
      return response.data.data;
    } catch (error) {
      throw toShipmentApiError(error, 'Failed to fetch shipment queue');
    }
  },
};
