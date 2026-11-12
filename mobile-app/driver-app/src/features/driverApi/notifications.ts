/**
 * Driver notifications API (D8) — never calls Client /notifications or Ops APIs.
 */

import { apiClient } from '@/lib/api/client';
import { DriverWorkApiError } from './workTypes';

export type DriverNotificationSeverity = 'info' | 'warning' | 'critical';

export type DriverNotificationActionKind =
  | 'assignment'
  | 'active_trip'
  | 'conversation'
  | 'work';

export type DriverNotificationItem = {
  id: number;
  code: string;
  title: string;
  body: string;
  severity: DriverNotificationSeverity;
  isRead: boolean;
  readAt: string | null;
  createdAt: string | null;
  assignmentId: number | null;
  shipmentId: string | null;
  action: { kind: DriverNotificationActionKind; target: string | null } | null;
};

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

type ApiItem = {
  id: number;
  code?: string;
  title?: string;
  body?: string;
  severity?: string;
  is_read?: boolean;
  read_at?: string | null;
  created_at?: string | null;
  assignment_id?: number | null;
  shipment_id?: string | null;
  action?: { kind?: string; target?: string | null } | null;
};

function toWorkError(error: unknown, fallback: string): DriverWorkApiError {
  if (error instanceof DriverWorkApiError) return error;
  const err = error as {
    response?: { status?: number; data?: { message?: string } };
    message?: string;
  };
  return new DriverWorkApiError(
    err?.response?.data?.message || err?.message || fallback,
    err?.response?.status
  );
}

function mapItem(raw: ApiItem): DriverNotificationItem | null {
  if (typeof raw?.id !== 'number') return null;
  const kindRaw = raw.action?.kind;
  let action: DriverNotificationItem['action'] = null;
  if (
    kindRaw === 'assignment' ||
    kindRaw === 'active_trip' ||
    kindRaw === 'conversation' ||
    kindRaw === 'work'
  ) {
    action = { kind: kindRaw, target: raw.action?.target ?? null };
  }

  return {
    id: raw.id,
    code: raw.code ?? '',
    title: raw.title ?? '',
    body: raw.body ?? '',
    severity: (raw.severity as DriverNotificationSeverity) || 'info',
    isRead: Boolean(raw.is_read),
    readAt: raw.read_at ?? null,
    createdAt: raw.created_at ?? null,
    assignmentId: raw.assignment_id ?? null,
    shipmentId: raw.shipment_id ?? null,
    action,
  };
}

export async function listNotifications(page = 1): Promise<{
  items: DriverNotificationItem[];
  total: number;
}> {
  try {
    const { data } = await apiClient.get<
      ApiEnvelope<{ data?: ApiItem[]; meta?: { total?: number } }>
    >('/driver/notifications', { params: { page, per_page: 30 } });
    const items = (data.data?.data ?? [])
      .map(mapItem)
      .filter((x): x is DriverNotificationItem => x !== null);
    return { items, total: data.data?.meta?.total ?? items.length };
  } catch (e) {
    throw toWorkError(e, 'Could not load notifications.');
  }
}

export async function getUnreadNotificationCount(): Promise<number> {
  try {
    const { data } = await apiClient.get<ApiEnvelope<{ unread_count?: number }>>(
      '/driver/notifications/unread-count'
    );
    return Number(data.data?.unread_count ?? 0);
  } catch (e) {
    throw toWorkError(e, 'Could not load unread count.');
  }
}

export async function markNotificationRead(id: number): Promise<DriverNotificationItem> {
  try {
    const { data } = await apiClient.post<ApiEnvelope<ApiItem>>(
      `/driver/notifications/${id}/read`
    );
    const mapped = mapItem(data.data);
    if (!mapped) throw new DriverWorkApiError('Unexpected read response.');
    return mapped;
  } catch (e) {
    throw toWorkError(e, 'Could not mark notification read.');
  }
}

export async function markAllNotificationsRead(): Promise<void> {
  try {
    await apiClient.post('/driver/notifications/read-all');
  } catch (e) {
    throw toWorkError(e, 'Could not mark all notifications read.');
  }
}

/** Resolve navigation href from allowlisted action (destination re-fetches). */
export function notificationHref(item: DriverNotificationItem): string {
  const kind = item.action?.kind;
  const target = item.action?.target;
  if (kind === 'assignment' && target) return `/assignment/${target}`;
  if (kind === 'active_trip' && target) return `/active-trip/${target}`;
  if (kind === 'conversation' && target) return `/conversation/${target}`;
  return '/(tabs)';
}
