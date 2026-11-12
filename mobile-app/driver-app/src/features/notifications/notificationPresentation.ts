/**
 * DR-F01 — Driver notification inbox presentation semantics (UI only).
 * Does not alter detection, severity contracts, or Active Trip awareness behavior.
 */

import type { DriverNotificationItem } from '@/features/driverApi/notifications';

export const DRIVER_SAFETY_NOTICE_CODES = [
  'safety.speed_excess',
  'safety.harsh_braking',
  'safety.sudden_stop',
] as const;

export const DRIVER_CRITICAL_NOTIFICATION_CODES = [
  'assignment.cancelled',
  'assignment.reassigned',
] as const;

export type NotificationPresentationIntent = 'action_required' | 'notice' | 'information';

export type NotificationPresentation = {
  intent: NotificationPresentationIntent;
  label: string;
  accessibilityLabel: string;
};

function isSafetyNoticeCode(code: string): boolean {
  return (DRIVER_SAFETY_NOTICE_CODES as readonly string[]).includes(code);
}

function isCriticalNotification(
  item: Pick<DriverNotificationItem, 'code' | 'severity'>
): boolean {
  return (
    item.severity === 'critical' ||
    (DRIVER_CRITICAL_NOTIFICATION_CODES as readonly string[]).includes(item.code)
  );
}

/** Map inbox notification to user-facing semantic label and accessibility text. */
export function getNotificationPresentation(
  item: Pick<DriverNotificationItem, 'code' | 'severity'>
): NotificationPresentation {
  if (isCriticalNotification(item)) {
    return {
      intent: 'action_required',
      label: 'ACTION REQUIRED',
      accessibilityLabel: 'Action required',
    };
  }

  if (isSafetyNoticeCode(item.code)) {
    return {
      intent: 'notice',
      label: 'NOTICE',
      accessibilityLabel: 'Safety notice',
    };
  }

  if (item.severity === 'warning') {
    return {
      intent: 'notice',
      label: 'NOTICE',
      accessibilityLabel: 'Notice',
    };
  }

  return {
    intent: 'information',
    label: 'INFO',
    accessibilityLabel: 'Information',
  };
}

export function notificationRowAccessibilityLabel(
  item: DriverNotificationItem,
  presentation: NotificationPresentation
): string {
  const parts = [presentation.accessibilityLabel, item.title];
  if (item.body) parts.push(item.body);
  if (!item.isRead) parts.push('Unread');
  return parts.join('. ');
}
