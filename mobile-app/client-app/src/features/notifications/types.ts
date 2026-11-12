/**
 * Client notification destination types — C2.8-B / C2.9-C3.8.
 *
 * Vocabulary (`type`) is unchanged. Destinations come only from
 * structured `action` { kind, id }, never from title/message.
 */

export type ClientNotificationType = 'shipment.status' | 'message.new' | 'system.notice' | string;

export type ClientNotificationActionKind =
  | 'shipment_details'
  | 'shipment_progress'
  | 'request_details'
  | 'conversation';

export type ClientNotificationAction = {
  kind: ClientNotificationActionKind;
  id: string;
} | null;

export type ClientNotification = {
  id: number;
  type: ClientNotificationType;
  title: string;
  message: string;
  isRead: boolean;
  createdAt: string | null;
  /** Structured deep-link from the Client API; null when none. */
  action: ClientNotificationAction;
};

export type ClientNotificationListMeta = {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
  from: number | null;
  to: number | null;
};

export type ClientNotificationListResult = {
  notifications: ClientNotification[];
  meta: ClientNotificationListMeta;
};

export type ListClientNotificationsParams = {
  page?: number;
  per_page?: number;
  is_read?: boolean;
};
