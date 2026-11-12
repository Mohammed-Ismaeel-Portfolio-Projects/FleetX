import type { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

type InfoRowProps = {
  label: string;
  value: string;
  testID?: string;
  icon?: ReactNode;
};

export function InfoRow({ label, value, testID, icon }: InfoRowProps) {
  return (
    <View style={styles.row} testID={testID}>
      {icon ? <View style={styles.icon}>{icon}</View> : null}
      <View style={styles.texts}>
        <Text style={styles.label}>{label}</Text>
        <Text style={styles.value}>{value}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: DriverTheme.spacing.md,
    paddingVertical: DriverTheme.spacing.sm,
  },
  icon: {
    width: 28,
    alignItems: 'center',
    paddingTop: 2,
  },
  texts: {
    flex: 1,
    gap: 2,
  },
  label: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  value: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
  },
});
