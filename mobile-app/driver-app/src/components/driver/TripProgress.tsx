import { StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

const STEPS = ['Pickup', 'Transit', 'Delivery', 'POD'] as const;

/** Presentational index from server execution.phase — does not mutate state. */
export function tripProgressIndex(phase: string | null | undefined): number {
  switch (phase) {
    case 'pickup_verified':
    case 'delivery_en_route':
      return 1;
    case 'delivery_arrived':
    case 'delivery_unloading':
    case 'delivery_verifying':
    case 'delivery_failed':
      return 2;
    case 'delivery_verified':
    case 'pod_submitted':
      return 3;
    default:
      return 0;
  }
}

type TripProgressProps = {
  phase: string | null | undefined;
  testID?: string;
};

export function TripProgress({ phase, testID }: TripProgressProps) {
  const current = tripProgressIndex(phase);
  const failed = phase === 'pickup_failed' || phase === 'delivery_failed';
  const last = STEPS.length - 1;

  return (
    <View
      style={styles.wrap}
      testID={testID}
      accessibilityRole="progressbar"
      accessibilityLabel={`Trip progress: ${STEPS[current]}`}>
      {STEPS.map((label, index) => {
        const done = index < current;
        const active = index === current;
        const lineOn = DriverTheme.colors.primary;
        const lineOff = DriverTheme.colors.border;
        const tone =
          active && failed
            ? DriverTheme.colors.danger
            : done || active
              ? DriverTheme.colors.primary
              : DriverTheme.colors.border;
        const textColor = active
          ? failed
            ? DriverTheme.colors.danger
            : DriverTheme.colors.primary
          : done
            ? DriverTheme.colors.textPrimary
            : DriverTheme.colors.textMuted;
        return (
          <View key={label} style={styles.step}>
            <View style={styles.track}>
              <View
                style={[
                  styles.line,
                  { backgroundColor: index === 0 ? 'transparent' : index <= current ? lineOn : lineOff },
                ]}
              />
              <View
                style={[styles.dot, { backgroundColor: tone }, active && styles.dotActive]}
                accessibilityLabel={`${label}${active ? ', current' : done ? ', complete' : ''}`}
              />
              <View
                style={[
                  styles.line,
                  { backgroundColor: index === last ? 'transparent' : index < current ? lineOn : lineOff },
                ]}
              />
            </View>
            <Text style={[styles.label, { color: textColor }]}>{label}</Text>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  step: {
    flex: 1,
    alignItems: 'center',
    gap: DriverTheme.spacing.xs,
  },
  track: {
    flexDirection: 'row',
    alignItems: 'center',
    width: '100%',
    height: 16,
  },
  line: {
    flex: 1,
    height: 2,
  },
  dot: {
    width: 14,
    height: 14,
    borderRadius: 7,
  },
  dotActive: {
    width: 16,
    height: 16,
    borderRadius: 8,
    borderWidth: 3,
    borderColor: DriverTheme.colors.infoSoft,
  },
  label: {
    ...DriverTheme.typography.caption,
    fontWeight: '700',
    textAlign: 'center',
  },
});
