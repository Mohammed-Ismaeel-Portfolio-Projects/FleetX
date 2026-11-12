import axios from 'axios';
import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  Driver,
  DriverCollection,
  DriverPayload,
  DriverQueryParams,
  DriverStatusToggleResponse,
  DriverValidationErrors,
} from '../types/driver.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export class DriverApiError extends Error {
  status?: number;
  validationErrors: DriverValidationErrors;

  constructor(message: string, status?: number, validationErrors: DriverValidationErrors = {}) {
    super(message);
    this.name = 'DriverApiError';
    this.status = status;
    this.validationErrors = validationErrors;
  }
}

function toDriverApiError(error: unknown, fallback: string): DriverApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: DriverValidationErrors;
    } | undefined;

    return new DriverApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors || {},
    );
  }

  return new DriverApiError(error instanceof Error ? error.message : fallback);
}

export const driversService = {
  async getDrivers(params?: DriverQueryParams): Promise<DriverCollection> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<DriverCollection>>('/drivers', {
        params: params ? cleanParams(params) : undefined,
      });

      return response.data.data;
    } catch (error) {
      throw toDriverApiError(error, 'Failed to fetch drivers');
    }
  },

  async getDriverById(id: string): Promise<Driver> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<Driver>>(`/drivers/${id}`);
      return response.data.data;
    } catch (error) {
      throw toDriverApiError(error, 'Failed to fetch driver details');
    }
  },

  async createDriver(payload: DriverPayload): Promise<Driver> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<Driver>>('/drivers', payload);
      return response.data.data;
    } catch (error) {
      throw toDriverApiError(error, 'Failed to create driver');
    }
  },

  async updateDriver(id: string, payload: DriverPayload): Promise<Driver> {
    try {
      const response = await axiosClientWithInterceptors.put<ApiEnvelope<Driver>>(`/drivers/${id}`, payload);
      return response.data.data;
    } catch (error) {
      throw toDriverApiError(error, 'Failed to update driver');
    }
  },

  async toggleDriverStatus(id: string, isActive: boolean): Promise<DriverStatusToggleResponse> {
    try {
      const response = await axiosClientWithInterceptors.patch<ApiEnvelope<DriverStatusToggleResponse>>(
        `/drivers/${id}/status`,
        {
          is_active: isActive,
        },
      );

      return response.data.data;
    } catch (error) {
      throw toDriverApiError(error, 'Failed to update driver status');
    }
  },

  async deleteDriver(id: string): Promise<void> {
    try {
      await axiosClientWithInterceptors.delete<ApiEnvelope<null>>(`/drivers/${id}`);
    } catch (error) {
      throw toDriverApiError(error, 'Failed to delete driver');
    }
  },

  async restoreDriver(id: string): Promise<void> {
    try {
      await axiosClientWithInterceptors.patch<ApiEnvelope<null>>(`/drivers/${id}/restore`);
    } catch (error) {
      throw toDriverApiError(error, 'Failed to restore driver');
    }
  },
};
