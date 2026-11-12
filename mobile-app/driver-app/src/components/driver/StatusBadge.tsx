import { StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

export type DriverStatusTone = 'available' | 'assigned' | 'ready' | 'in_operation' | 'neutral' | 'danger';

type StatusBadgeProps = {
  label: string;
  tone?: DriverStatusTone;
  testID?: string;
};

const TONE_STYLES: Record<
  DriverStatusTone,
  { bg: string; fg: string }
> = {
  available: { bg: DriverTheme.colors.successSoft, fg: DriverTheme.colors.success },
  assigned: { bg: DriverTheme.colors.infoSoft, fg: DriverTheme.colors.info },
  ready: { bg: DriverTheme.colors.infoSoft, fg: DriverTheme.colors.primaryDark },
  in_operation: { bg: DriverTheme.colors.warningSoft, fg: DriverTheme.colors.warning },
  neutral: { bg: DriverTheme.colors.background, fg: DriverTheme.colors.textSecondary },
  danger: { bg: DriverTheme.colors.dangerSoft, fg: DriverTheme.colors.danger },
};

/** Map existing workOperationalLabel / status.key values — no new domain statuses. */
export function statusToneFromLabel(label: string): DriverStatusTone {
  const key = label.trim().toLowerCase();
  if (key === 'available') return 'available';
  if (key === 'assigned') return 'assigned';
  if (key === 'ready') return 'ready';
  if (key === 'in operation' || key === 'in_operation') return 'in_operation';
  if (key === 'unavailable' || key === 'cancelled' || key.endsWith('failed')) return 'danger';
  if (key === 'completed') return 'available';
  return 'neutral';
}

export function StatusBadge({ label, tone, testID }: StatusBadgeProps) {
  const resolved = TONE_STYLES[tone ?? statusToneFromLabel(label)];
  return (
    <View
      style={[styles.pill, { backgroundColor: resolved.bg }]}
      testID={testID}
      accessibilityRole="text"
      accessibilityLabel={label}>
      <Text style={[styles.text, { color: resolved.fg }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    alignSelf: 'flex-start',
    paddingHorizontal: DriverTheme.spacing.md,
    paddingVertical: DriverTheme.spacing.xs,
    borderRadius: DriverTheme.radius.pill,
  },
  text: {
    ...DriverTheme.typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
});
