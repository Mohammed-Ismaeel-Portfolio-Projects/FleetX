import { ArrowDown } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { formatHistoryPlace } from '@/features/driverApi/historyMapper';
import type { DriverHistoryListItem } from '@/features/driverApi/historyTypes';

import { StatusBadge, statusToneFromLabel } from './StatusBadge';
import { DriverTheme } from './theme';

type HistoryCardProps = {
  item: DriverHistoryListItem;
  onPress: () => void;
  children?: ReactNode;
};

export function HistoryCard({ item, onPress, children }: HistoryCardProps) {
  const title = item.assignmentCode || `Assignment #${item.id}`;
  const pickup = formatHistoryPlace(item.pickup);
  const delivery = formatHistoryPlace(item.delivery);
  const outcomeLine = `${item.outcome.label}${
    item.status?.name ? ` · ${item.status.name}` : ''
  }`;

  return (
    <View testID={`history-card-${item.id}`} style={styles.card}>
      <Pressable
        onPress={onPress}
        accessibilityRole="button"
        accessibilityLabel={`${title}. ${outcomeLine}. View Summary`}
        style={({ pressed }) => [styles.press, pressed && styles.pressed]}>
        <View style={styles.titleRow}>
          <Text style={styles.title} numberOfLines={2}>
            {title}
          </Text>
          <StatusBadge
            label={item.outcome.label}
            tone={statusToneFromLabel(item.outcome.key || item.outcome.label)}
          />
        </View>
        {item.shipmentNumber ? (
          <Text style={styles.meta}>Shipment {item.shipmentNumber}</Text>
        ) : null}
        <View style={styles.route}>
          <Text style={styles.routeLabel}>Pickup</Text>
          <Text style={styles.routeValue}>{pickup}</Text>
          <ArrowDown
            size={16}
            color={DriverTheme.colors.textMuted}
            accessibilityElementsHidden
          />
          <Text style={styles.routeLabel}>Delivery</Text>
          <Text style={styles.routeValue}>{delivery}</Text>
        </View>
        <Text testID={`history-outcome-${item.id}`} style={styles.outcome}>
          {outcomeLine}
        </Text>
        {item.endedAt ? <Text style={styles.meta}>{item.endedAt}</Text> : null}
      </Pressable>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: DriverTheme.colors.surface,
    borderRadius: DriverTheme.radius.large,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    padding: DriverTheme.spacing.lg,
    gap: DriverTheme.spacing.md,
    ...DriverTheme.elevation.card,
  },
  press: {
    gap: DriverTheme.spacing.sm,
  },
  pressed: {
    opacity: 0.88,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: DriverTheme.spacing.sm,
  },
  title: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
    flex: 1,
    minWidth: 0,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
  },
  route: {
    gap: 2,
  },
  routeLabel: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  routeValue: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  outcome: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
  },
});
