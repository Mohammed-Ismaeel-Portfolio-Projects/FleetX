import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  TrackingSession,
  TrackingSessionEventItem,
  TrackingSessionOverridePayload,
} from '../types/trackingSession.types';

interface ApiEnvelope<T> {
  data: T;
  message?: string;
  success?: boolean;
}

export class TrackingSessionApiError extends Error {
  statusCode?: number;

  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'TrackingSessionApiError';
    this.statusCode = statusCode;
  }
}

function handleApiError(error: unknown, fallbackMessage: string): never {
  const err = error as {
    response?: { data?: { message?: string }; status?: number };
    message?: string;
  };
  throw new TrackingSessionApiError(
    err?.response?.data?.message || err?.message || fallbackMessage,
    err?.response?.status
  );
}

export const trackingSessionService = {
  async getSessionByShipmentOrder(shipmentOrderId: string): Promise<TrackingSession | null> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<TrackingSession>>(
        `/dispatcher/tracking-sessions/shipment-order/${shipmentOrderId}`
      );
      return response.data.data;
    } catch (error: unknown) {
      const status = (error as { response?: { status?: number } })?.response?.status;
      if (status === 404) {
        return null;
      }
      return handleApiError(error, 'Failed to fetch tracking session for shipment order');
    }
  },

  async startSession(shipmentOrderId: string): Promise<TrackingSession> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<TrackingSession>>(
        '/dispatcher/tracking-sessions/start',
        { shipment_order_id: shipmentOrderId }
      );
      return response.data.data;
    } catch (error: unknown) {
      return handleApiError(error, 'Failed to start tracking session');
    }
  },

  async pauseSession(sessionId: string): Promise<TrackingSession> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<TrackingSession>>(
        `/dispatcher/tracking-sessions/${sessionId}/pause`
      );
      return response.data.data;
    } catch (error: unknown) {
      return handleApiError(error, 'Failed to pause tracking session');
    }
  },

  async resumeSession(sessionId: string): Promise<TrackingSession> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<TrackingSession>>(
        `/dispatcher/tracking-sessions/${sessionId}/resume`
      );
      return response.data.data;
    } catch (error: unknown) {
      return handleApiError(error, 'Failed to resume tracking session');
    }
  },

  async stopSession(sessionId: string): Promise<TrackingSession> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<TrackingSession>>(
        `/dispatcher/tracking-sessions/${sessionId}/stop`
      );
      return response.data.data;
    } catch (error: unknown) {
      return handleApiError(error, 'Failed to stop tracking session');
    }
  },

  async updateOverride(
    sessionId: string,
    payload: TrackingSessionOverridePayload
  ): Promise<TrackingSession> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<TrackingSession>>(
        `/dispatcher/tracking-sessions/${sessionId}/override`,
        payload
      );
      return response.data.data;
    } catch (error: unknown) {
      return handleApiError(error, 'Failed to update tracking session override');
    }
  },

  async getEvents(sessionId: string): Promise<TrackingSessionEventItem[]> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<TrackingSessionEventItem[]>>(
        `/dispatcher/tracking-sessions/${sessionId}/events`
      );
      return response.data.data;
    } catch (error: unknown) {
      const status = (error as { response?: { status?: number } })?.response?.status;
      if (status === 404) {
        return [];
      }
      return handleApiError(error, 'Failed to fetch session event history');
    }
  },
};
