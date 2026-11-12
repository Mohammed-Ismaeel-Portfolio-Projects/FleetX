import { apiClient } from '../../lib/api/client';
import {
  customerHttpMessage,
  detectNetworkFailure,
} from '../../lib/ux/customerErrors';
import { mapClientNotificationAction } from './notificationDestination';
import type {
  ClientNotification,
  ClientNotificationListResult,
  ListClientNotificationsParams,
} from './types';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

interface ClientNotificationApi {
  id: number;
  type?: string | null;
  title?: string | null;
  message?: string | null;
  is_read?: boolean | null;
  created_at?: string | null;
  action?: unknown;
}

interface PaginatedPayload {
  data: ClientNotificationApi[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
  };
}

export class ClientNotificationsApiError extends Error {
  statusCode?: number;
  isNetwork?: boolean;

  constructor(message: string, statusCode?: number, isNetwork = false) {
    super(message);
    this.name = 'ClientNotificationsApiError';
    this.statusCode = statusCode;
    this.isNetwork = isNetwork;
  }
}

function toApiError(error: unknown, fallback: string): ClientNotificationsApiError {
  if (error instanceof ClientNotificationsApiError) return error;
  const err = error as {
    response?: { status?: number; data?: { message?: string } };
    message?: string;
    code?: string;
  };

  const isNetwork = detectNetworkFailure(error);
  const statusCode = err?.response?.status;
  const serverMessage =
    (typeof err?.response?.data?.message === 'string' && err.response.data.message.trim()) ||
    fallback;

  return new ClientNotificationsApiError(
    customerHttpMessage(statusCode, {
      isNetwork,
      resource: 'notification',
      fallback: serverMessage,
    }),
    statusCode,
    isNetwork
  );
}

function mapNotification(row: ClientNotificationApi): ClientNotification {
  return {
    id: Number(row.id),
    type: row.type != null ? String(row.type) : '',
    title: row.title != null ? String(row.title) : '',
    message: row.message != null ? String(row.message) : '',
    isRead: Boolean(row.is_read),
    createdAt: row.created_at != null ? String(row.created_at) : null,
    // Destinations come only from structured action { kind, id }. Never from title/message.
    action: mapClientNotificationAction(row.action),
  };
}

/**
 * Presentation-only type label — does not change API `type` value.
 */
export function formatNotificationTypeLabel(type: string | null | undefined): string {
  const raw = type != null ? String(type).trim() : '';
  if (!raw) return 'Notification';
  if (raw === 'shipment.status') return 'Shipment update';
  if (raw === 'message.new') return 'Message';
  if (raw === 'system.notice') return 'System';
  return raw;
}

/** Presentation-only datetime display. */
export function formatNotificationCreatedAt(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;
  return trimmed;
}

/**
 * GET /api/v1/client/notifications
 * Must never send user_id / company_id / customer_company_id / owner_id / recipient_id.
 */
export async function listClientNotifications(
  params: ListClientNotificationsParams = {}
): Promise<ClientNotificationListResult> {
  const query: Record<string, string | number | boolean> = {};

  if (params.page != null) query.page = params.page;
  if (params.per_page != null) query.per_page = params.per_page;
  if (params.is_read != null) query.is_read = params.is_read;

  // Match backend prohibit-list — never send ownership / company scope params.
  delete (query as { user_id?: unknown }).user_id;
  delete (query as { company_id?: unknown }).company_id;
  delete (query as { customer_company_id?: unknown }).customer_company_id;
  delete (query as { owner_id?: unknown }).owner_id;
  delete (query as { recipient_id?: unknown }).recipient_id;

  try {
    const { data } = await apiClient.get<ApiEnvelope<PaginatedPayload>>('/client/notifications', {
      params: query,
    });

    const payload = data?.data;
    if (!payload || !Array.isArray(payload.data) || !payload.meta) {
      throw new ClientNotificationsApiError('Unexpected notification list response from server.');
    }

    return {
      notifications: payload.data.map(mapNotification),
      meta: {
        currentPage: payload.meta.current_page,
        perPage: payload.meta.per_page,
        total: payload.meta.total,
        lastPage: payload.meta.last_page,
        from: payload.meta.from ?? null,
        to: payload.meta.to ?? null,
      },
    };
  } catch (e) {
    throw toApiError(e, 'Failed to load notifications');
  }
}

/**
 * GET /api/v1/client/notifications/unread-count
 */
export async function getClientNotificationsUnreadCount(): Promise<number> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<{ unread_count: number }>>(
      '/client/notifications/unread-count'
    );
    const count = data?.data?.unread_count;
    if (typeof count !== 'number' || !Number.isFinite(count) || count < 0) {
      throw new ClientNotificationsApiError('Unexpected unread count response from server.');
    }
    return count;
  } catch (e) {
    throw toApiError(e, 'Failed to load unread count');
  }
}

/**
 * PATCH /api/v1/client/notifications/{id}/read
 */
export async function markClientNotificationRead(
  notificationId: number | string
): Promise<ClientNotification> {
  const id = String(notificationId ?? '').trim();
  if (!id) {
    throw new ClientNotificationsApiError('Notification id unavailable.');
  }

  try {
    const { data } = await apiClient.patch<ApiEnvelope<ClientNotificationApi>>(
      `/client/notifications/${encodeURIComponent(id)}/read`
    );
    const payload = data?.data;
    if (!payload || payload.id == null) {
      throw new ClientNotificationsApiError('Unexpected mark-read response from server.');
    }
    return mapNotification(payload);
  } catch (e) {
    throw toApiError(e, 'Failed to mark notification as read');
  }
}

/**
 * POST /api/v1/client/notifications/read-all
 */
export async function markAllClientNotificationsRead(): Promise<number> {
  try {
    const { data } = await apiClient.post<ApiEnvelope<{ marked_read: number }>>(
      '/client/notifications/read-all'
    );
    const marked = data?.data?.marked_read;
    if (typeof marked !== 'number' || !Number.isFinite(marked) || marked < 0) {
      throw new ClientNotificationsApiError('Unexpected mark-all-read response from server.');
    }
    return marked;
  } catch (e) {
    throw toApiError(e, 'Failed to mark all notifications as read');
  }
}
