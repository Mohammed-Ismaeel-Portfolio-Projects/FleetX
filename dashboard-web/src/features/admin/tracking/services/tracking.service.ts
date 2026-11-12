import axios from 'axios';
import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  TrackingState,
  TrackingStateCollection,
  TrackingQueryParams,
} from '../types/tracking.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export class TrackingApiError extends Error {
  status?: number;

  constructor(message: string, status?: number) {
    super(message);
    this.name = 'TrackingApiError';
    this.status = status;
  }
}

function toTrackingApiError(error: unknown, fallback: string): TrackingApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
    } | undefined;

    return new TrackingApiError(
      data?.message || fallback,
      error.response?.status
    );
  }

  return new TrackingApiError(error instanceof Error ? error.message : fallback);
}

export const trackingService = {
  async getTrackingStates(params?: TrackingQueryParams): Promise<TrackingStateCollection> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<TrackingStateCollection>>('/tracking-states', {
        params: params ? cleanParams(params) : undefined,
      });
      return response.data.data;
    } catch (error) {
      throw toTrackingApiError(error, 'Failed to fetch tracking states');
    }
  },

  async getTrackingState(id: string): Promise<TrackingState> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<TrackingState>>(`/tracking-states/${id}`);
      return response.data.data;
    } catch (error) {
      throw toTrackingApiError(error, 'Failed to fetch tracking state');
    }
  },
};
