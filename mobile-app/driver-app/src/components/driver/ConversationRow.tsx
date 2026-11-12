import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { Conversation } from '@/features/messaging/types';

import { StatusBadge, type DriverStatusTone } from './StatusBadge';
import { DriverTheme } from './theme';

type ConversationRowProps = {
  item: Conversation;
  onPress: () => void;
  onShipmentPress?: () => void;
};

function conversationTitle(item: Conversation): string {
  return item.type === 'dispatcher_driver' ? 'Dispatcher' : item.type;
}

function conversationInitials(title: string): string {
  const trimmed = title.trim();
  if (!trimmed) return 'M';
  const parts = trimmed.split(/\s+/).filter(Boolean);
  if (parts.length >= 2) {
    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
  }
  return trimmed.slice(0, 1).toUpperCase();
}

function statusTone(status: string): DriverStatusTone {
  const key = status.trim().toLowerCase();
  if (key === 'open') return 'assigned';
  if (key === 'closed') return 'danger';
  return 'neutral';
}

export function ConversationRow({ item, onPress, onShipmentPress }: ConversationRowProps) {
  const title = conversationTitle(item);
  const shipment = item.shipment?.shipmentNumber ?? item.shipmentId ?? 'Unavailable';
  const status = item.status || 'Unavailable';
  const updated = item.updatedAt ? new Date(item.updatedAt).toLocaleString() : 'Unavailable';

  return (
    <Pressable
      testID={`conversation-row-${item.id}`}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${title}, shipment ${shipment}, ${status}`}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
      <View style={styles.avatar} accessibilityElementsHidden importantForAccessibility="no">
        <Text style={styles.avatarText}>{conversationInitials(title)}</Text>
      </View>
      <View style={styles.body}>
        <View style={styles.titleRow}>
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
          <StatusBadge label={status} tone={statusTone(status)} />
        </View>
        <Text style={styles.preview} numberOfLines={1}>
          Shipment: {shipment}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          Updated: {updated}
        </Text>
        {item.lastReadAt ? (
          <Text style={styles.meta} numberOfLines={1}>
            Last read: {new Date(item.lastReadAt).toLocaleString()}
          </Text>
        ) : null}
        {onShipmentPress && item.shipmentId ? (
          <Pressable
            testID={`shipment-handoff-${item.shipmentId}`}
            onPress={(e) => {
              e.stopPropagation?.();
              onShipmentPress();
            }}
            hitSlop={8}
            accessibilityRole="button"
            accessibilityLabel="Shipment communication"
            style={styles.handoff}>
            <Text style={styles.handoffText}>Shipment communication</Text>
          </Pressable>
        ) : null}
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
  pressed: {
    opacity: 0.88,
    backgroundColor: DriverTheme.colors.infoSoft,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: DriverTheme.colors.infoSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.primary,
  },
  body: {
    flex: 1,
    minWidth: 0,
    gap: 4,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: DriverTheme.spacing.sm,
  },
  title: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
    flex: 1,
    minWidth: 0,
  },
  preview: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
  },
  handoff: {
    alignSelf: 'flex-start',
    minHeight: 44,
    justifyContent: 'center',
    paddingTop: DriverTheme.spacing.xs,
  },
  handoffText: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.primary,
    fontWeight: '700',
  },
});
