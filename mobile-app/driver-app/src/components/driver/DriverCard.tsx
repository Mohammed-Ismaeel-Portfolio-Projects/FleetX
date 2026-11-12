import type { ReactNode } from 'react';
import { StyleSheet, View, type ViewProps } from 'react-native';

import { DriverTheme } from './theme';

type DriverCardProps = ViewProps & {
  children: ReactNode;
};

export function DriverCard({ children, style, ...rest }: DriverCardProps) {
  return (
    <View style={[styles.card, style]} {...rest}>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#0E1320',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    padding: DriverTheme.spacing.md,
    gap: DriverTheme.spacing.md,
  },
});
