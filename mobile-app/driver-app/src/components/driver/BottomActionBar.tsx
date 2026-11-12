import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { DriverTheme } from './theme';

type BottomActionBarProps = {
  children: ReactNode;
};

/**
 * Stack-screen action dock. Owns the bottom safe-area inset.
 * Does not overlay the map: it sits below the scrolling body.
 */
export function BottomActionBar({ children }: BottomActionBarProps) {
  const insets = useSafeAreaInsets();
  return (
    <View
      style={[
        styles.bar,
        { paddingBottom: Math.max(insets.bottom, DriverTheme.spacing.md) },
      ]}>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    backgroundColor: '#0E1320',
    borderTopWidth: 1,
    borderTopColor: 'rgba(255, 255, 255, 0.1)',
    paddingHorizontal: 16,
    paddingTop: 16,
    gap: 12,
  },
});
