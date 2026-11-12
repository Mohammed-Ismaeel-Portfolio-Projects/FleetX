import { AlertTriangle, Bell, Info } from 'lucide-react-native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { NotificationPresentation } from '@/features/notifications/notificationPresentation';

import { StatusBadge, type DriverStatusTone } from './StatusBadge';
import { DriverTheme } from './theme';

type NotificationItemProps = {
  title: string;
  body: string;
  createdAt: string | null;
  isRead: boolean;
  presentation: NotificationPresentation;
  onPress: () => void;
  testID: string;
  accessibilityLabel: string;
};

function intentTone(intent: NotificationPresentation['intent']): DriverStatusTone {
  if (intent === 'action_required') return 'danger';
  if (intent === 'notice') return 'in_operation';
  return 'assigned';
}

function IntentGlyph({ intent }: { intent: NotificationPresentation['intent'] }) {
  const tone = intentTone(intent);
  const color =
    tone === 'danger'
      ? DriverTheme.colors.danger
      : tone === 'in_operation'
        ? DriverTheme.colors.warning
        : DriverTheme.colors.primary;
  const size = 20;
  if (intent === 'action_required') {
    return <AlertTriangle size={size} color={color} />;
  }
  if (intent === 'notice') {
    return <Bell size={size} color={color} />;
  }
  return <Info size={size} color={color} />;
}

function glyphBackground(intent: NotificationPresentation['intent']): string {
  if (intent === 'action_required') return DriverTheme.colors.dangerSoft;
  if (intent === 'notice') return DriverTheme.colors.warningSoft;
  return DriverTheme.colors.infoSoft;
}

export function NotificationItem({
  title,
  body,
  createdAt,
  isRead,
  presentation,
  onPress,
  testID,
  accessibilityLabel,
}: NotificationItemProps) {
  const meta = [
    presentation.label,
    createdAt || null,
    !isRead ? 'Unread' : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable
      testID={testID}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      style={({ pressed }) => [
        styles.row,
        !isRead && styles.unread,
        presentation.intent === 'action_required' && styles.critical,
        pressed && styles.pressed,
      ]}>
      <View
        style={[styles.glyph, { backgroundColor: glyphBackground(presentation.intent) }]}
        accessibilityElementsHidden
        importantForAccessibility="no">
        <IntentGlyph intent={presentation.intent} />
      </View>
      <View style={styles.content}>
        <View style={styles.titleRow}>
          <StatusBadge label={presentation.label} tone={intentTone(presentation.intent)} />
          {!isRead ? <View style={styles.unreadDot} accessibilityElementsHidden /> : null}
        </View>
        <Text style={[styles.title, !isRead && styles.titleUnread]}>{title}</Text>
        {body ? <Text style={styles.body}>{body}</Text> : null}
        {meta ? <Text style={styles.meta}>{meta}</Text> : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: DriverTheme.spacing.md,
    minHeight: 72,
    padding: DriverTheme.spacing.lg,
    backgroundColor: DriverTheme.colors.surface,
    borderRadius: DriverTheme.radius.large,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    ...DriverTheme.elevation.card,
  },
  unread: {
    borderLeftWidth: 3,
    borderLeftColor: DriverTheme.colors.primary,
    backgroundColor: DriverTheme.colors.surface,
  },
  critical: {
    backgroundColor: DriverTheme.colors.dangerSoft,
  },
  pressed: {
    opacity: 0.88,
  },
  glyph: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: {
    flex: 1,
    minWidth: 0,
    gap: 4,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: DriverTheme.spacing.sm,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: DriverTheme.colors.primary,
  },
  title: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
    fontWeight: '600',
  },
  titleUnread: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
  },
  body: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
    marginTop: 2,
  },
});
