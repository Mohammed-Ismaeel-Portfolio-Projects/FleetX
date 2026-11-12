import type { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

type EmptyStateProps = {
  title?: string;
  body?: string;
  icon?: ReactNode;
  testID?: string;
  children?: ReactNode;
};

export function EmptyState({ title, body, icon, testID, children }: EmptyStateProps) {
  return (
    <View style={styles.wrap} testID={testID} accessibilityRole="summary">
      {icon ? <View style={styles.iconWrap}>{icon}</View> : null}
      {title ? <Text style={styles.title}>{title}</Text> : null}
      {body ? <Text style={styles.body}>{body}</Text> : null}
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    paddingHorizontal: DriverTheme.spacing.xl,
    paddingVertical: DriverTheme.spacing.xxl,
    gap: DriverTheme.spacing.sm,
  },
  iconWrap: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: DriverTheme.colors.infoSoft,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: DriverTheme.spacing.md,
  },
  title: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    textAlign: 'center',
  },
  body: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
    textAlign: 'center',
    maxWidth: 320,
  },
});
