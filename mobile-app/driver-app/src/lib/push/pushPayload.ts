/**
 * Push payload parser (D12). Navigation only — never mutate from payload.
 */

import { notificationHref, type DriverNotificationItem } from '@/features/driverApi/notifications';

export type DriverPushData = {
  type: 'driver_notification';
  notificationId: number | null;
  code: string | null;
  actionKind: string | null;
  actionTarget: string | null;
};

const FALLBACK_HREF = '/(tabs)/notifications';

export function parseDriverPushData(raw: unknown): DriverPushData | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const d = raw as Record<string, unknown>;
  const nested =
    d.data != null && typeof d.data === 'object' && !Array.isArray(d.data)
      ? (d.data as Record<string, unknown>)
      : d;

  const type = nested.type ?? d.type;
  const hasHint =
    nested.notification_id != null ||
    nested.notificationId != null ||
    nested.code != null ||
    nested.action_kind != null ||
    nested.actionKind != null;
  if (type !== 'driver_notification' && !hasHint) return null;

  const kind = nested.action_kind ?? nested.actionKind;
  const target = nested.action_target ?? nested.actionTarget;
  const idRaw = nested.notification_id ?? nested.notificationId;
  const idNum =
    typeof idRaw === 'number' ? idRaw : typeof idRaw === 'string' ? Number(idRaw) : NaN;

  return {
    type: 'driver_notification',
    notificationId: Number.isFinite(idNum) ? idNum : null,
    code: typeof nested.code === 'string' ? nested.code : null,
    actionKind: typeof kind === 'string' ? kind : null,
    actionTarget: typeof target === 'string' ? target : null,
  };
}

/** Allowlisted href. Malformed / unknown → Alerts tab. Destinations re-fetch. */
export function hrefFromDriverPushData(data: DriverPushData | null): string {
  if (!data) return FALLBACK_HREF;
  const item: DriverNotificationItem = {
    id: data.notificationId ?? 0,
    code: data.code ?? '',
    title: '',
    body: '',
    severity: 'info',
    isRead: false,
    readAt: null,
    createdAt: null,
    assignmentId: null,
    shipmentId: null,
    action:
      data.actionKind === 'assignment' ||
      data.actionKind === 'active_trip' ||
      data.actionKind === 'conversation' ||
      data.actionKind === 'work'
        ? { kind: data.actionKind, target: data.actionTarget }
        : null,
  };
  return notificationHref(item);
}
