import axios from 'axios';
import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  Trailer,
  TrailerCollection,
  TrailerPayload,
  TrailerQueryParams,
  TrailerStatusToggleResponse,
  TrailerValidationErrors,
} from '../types/trailer.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export class TrailerApiError extends Error {
  status?: number;
  validationErrors: TrailerValidationErrors;

  constructor(message: string, status?: number, validationErrors: TrailerValidationErrors = {}) {
    super(message);
    this.name = 'TrailerApiError';
    this.status = status;
    this.validationErrors = validationErrors;
  }
}

function toTrailerApiError(error: unknown, fallback: string): TrailerApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: TrailerValidationErrors;
    } | undefined;

    return new TrailerApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors || {},
    );
  }

  return new TrailerApiError(error instanceof Error ? error.message : fallback);
}

export const trailersService = {
  async getTrailers(params?: TrailerQueryParams): Promise<TrailerCollection> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<TrailerCollection>>('/trailers', {
        params: params ? cleanParams(params) : undefined,
      });

      return response.data.data;
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to fetch trailers');
    }
  },

  async getTrailerById(id: string): Promise<Trailer> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<Trailer>>(`/trailers/${id}`);
      return response.data.data;
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to fetch trailer details');
    }
  },

  async createTrailer(payload: TrailerPayload): Promise<Trailer> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<Trailer>>('/trailers', payload);
      return response.data.data;
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to create trailer');
    }
  },

  async updateTrailer(id: string, payload: TrailerPayload): Promise<Trailer> {
    try {
      const response = await axiosClientWithInterceptors.put<ApiEnvelope<Trailer>>(`/trailers/${id}`, payload);
      return response.data.data;
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to update trailer');
    }
  },

  async toggleTrailerStatus(id: string, isActive: boolean): Promise<TrailerStatusToggleResponse> {
    try {
      const response = await axiosClientWithInterceptors.patch<ApiEnvelope<TrailerStatusToggleResponse>>(
        `/trailers/${id}/status`,
        {
          is_active: isActive,
        },
      );

      return response.data.data;
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to update trailer status');
    }
  },

  async deleteTrailer(id: string): Promise<void> {
    try {
      await axiosClientWithInterceptors.delete<ApiEnvelope<null>>(`/trailers/${id}`);
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to delete trailer');
    }
  },

  async restoreTrailer(id: string): Promise<void> {
    try {
      await axiosClientWithInterceptors.patch<ApiEnvelope<null>>(`/trailers/${id}/restore`);
    } catch (error) {
      throw toTrailerApiError(error, 'Failed to restore trailer');
    }
  },
};
