/**
 * Centralized Client notification destination resolver (C2.9-C3.8 / Final Polish P2-2).
 *
 * Single source of truth for inbox → customer screen mapping.
 * Never infers IDs from title/message. Never uses Dispatcher/Engine IDs.
 */

import type {
  ClientNotification,
  ClientNotificationAction,
  ClientNotificationActionKind,
} from './types';

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const ALLOWED_KINDS: ReadonlySet<ClientNotificationActionKind> = new Set([
  'shipment_details',
  'shipment_progress',
  'request_details',
  'conversation',
]);

export type ClientNotificationDestinationKind =
  | 'shipment'
  | 'request'
  | 'conversation'
  | 'inbox';

export type ClientNotificationDestination = {
  kind: ClientNotificationDestinationKind;
  href:
    | '/notifications'
    | `/shipment-details/${string}`
    | `/shipment-tracking/${string}`
    | `/request-details/${string}`
    | `/conversation/${string}`;
  ctaLabel: 'View Shipment' | 'View progress' | 'View Request' | 'Open Message' | 'View Notification';
  navigable: boolean;
};

const INBOX_DESTINATION: ClientNotificationDestination = {
  kind: 'inbox',
  href: '/notifications',
  ctaLabel: 'View Notification',
  navigable: false,
};

function isUuid(value: string): boolean {
  return UUID_RE.test(value);
}

/**
 * Map a raw API `action` payload. Title/message are intentionally unused.
 * Unknown kinds, missing ids, and non-UUID ids become null.
 */
export function mapClientNotificationAction(raw: unknown): ClientNotificationAction {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }

  const record = raw as { kind?: unknown; id?: unknown };
  const kind = typeof record.kind === 'string' ? record.kind.trim() : '';
  const id = typeof record.id === 'string' ? record.id.trim() : '';

  if (!kind || !id) return null;
  if (!ALLOWED_KINDS.has(kind as ClientNotificationActionKind)) return null;
  if (!isUuid(id)) return null;

  return { kind: kind as ClientNotificationActionKind, id };
}

/**
 * Deterministic customer destination. Unknown / unsafe → inbox (not a guess).
 * Title, message, and type are accepted so callers can pass a full notification;
 * they are intentionally unused for routing.
 */
export function resolveClientNotificationDestination(
  notification: Pick<ClientNotification, 'action' | 'title' | 'message' | 'type'> | null | undefined
): ClientNotificationDestination {
  void notification?.title;
  void notification?.message;
  void notification?.type;

  const action = mapClientNotificationAction(notification?.action ?? null);
  if (!action) return INBOX_DESTINATION;

  const kind = action.kind;
  const id = action.id;

  if (kind === 'shipment_details') {
    return {
      kind: 'shipment',
      href: `/shipment-details/${id}`,
      ctaLabel: 'View Shipment',
      navigable: true,
    };
  }

  if (kind === 'shipment_progress') {
    return {
      kind: 'shipment',
      href: `/shipment-tracking/${id}`,
      ctaLabel: 'View progress',
      navigable: true,
    };
  }

  if (kind === 'request_details') {
    return {
      kind: 'request',
      href: `/request-details/${id}`,
      ctaLabel: 'View Request',
      navigable: true,
    };
  }

  if (kind === 'conversation') {
    return {
      kind: 'conversation',
      href: `/conversation/${id}`,
      ctaLabel: 'Open Message',
      navigable: true,
    };
  }

  return INBOX_DESTINATION;
}
