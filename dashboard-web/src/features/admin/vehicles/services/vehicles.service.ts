import axios from 'axios';
import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  Vehicle,
  VehicleCollection,
  VehiclePayload,
  VehicleQueryParams,
  VehicleStatusToggleResponse,
  VehicleValidationErrors,
} from '../types/vehicle.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export class VehicleApiError extends Error {
  status?: number;
  validationErrors: VehicleValidationErrors;

  constructor(message: string, status?: number, validationErrors: VehicleValidationErrors = {}) {
    super(message);
    this.name = 'VehicleApiError';
    this.status = status;
    this.validationErrors = validationErrors;
  }
}

function toVehicleApiError(error: unknown, fallback: string): VehicleApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: VehicleValidationErrors;
    } | undefined;

    return new VehicleApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors || {},
    );
  }

  return new VehicleApiError(error instanceof Error ? error.message : fallback);
}

export const vehiclesService = {
  async getVehicles(params?: VehicleQueryParams): Promise<VehicleCollection> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<VehicleCollection>>('/vehicles', {
        params: params ? cleanParams(params) : undefined,
      });

      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to fetch vehicles');
    }
  },

  async getVehicleById(id: string): Promise<Vehicle> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<Vehicle>>(`/vehicles/${id}`);
      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to fetch vehicle details');
    }
  },

  async createVehicle(payload: VehiclePayload): Promise<Vehicle> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<Vehicle>>('/vehicles', payload);
      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to create vehicle');
    }
  },

  async updateVehicle(id: string, payload: VehiclePayload): Promise<Vehicle> {
    try {
      const response = await axiosClientWithInterceptors.put<ApiEnvelope<Vehicle>>(`/vehicles/${id}`, payload);
      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to update vehicle');
    }
  },

  async toggleVehicleStatus(id: string, isActive: boolean): Promise<VehicleStatusToggleResponse> {
    try {
      const response = await axiosClientWithInterceptors.patch<ApiEnvelope<VehicleStatusToggleResponse>>(
        `/vehicles/${id}/status`,
        {
          is_active: isActive,
        },
      );

      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to update vehicle status');
    }
  },

  async pairDriver(vehicleId: string, driverId: number): Promise<Vehicle> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<Vehicle>>(
        `/vehicles/${vehicleId}/pair-driver`,
        { driver_id: driverId }
      );
      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to pair driver with vehicle');
    }
  },

  async unpairDriver(vehicleId: string): Promise<Vehicle> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<Vehicle>>(
        `/vehicles/${vehicleId}/unpair-driver`
      );
      return response.data.data;
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to unpair driver from vehicle');
    }
  },

  async deleteVehicle(id: string): Promise<void> {
    try {
      await axiosClientWithInterceptors.delete<ApiEnvelope<null>>(`/vehicles/${id}`);
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to delete vehicle');
    }
  },

  async restoreVehicle(id: string): Promise<void> {
    try {
      await axiosClientWithInterceptors.patch<ApiEnvelope<null>>(`/vehicles/${id}/restore`);
    } catch (error) {
      throw toVehicleApiError(error, 'Failed to restore vehicle');
    }
  },
};
