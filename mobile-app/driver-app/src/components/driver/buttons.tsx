import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

type ButtonVariant = 'primary' | 'secondary' | 'danger';

type DriverButtonProps = {
  label: string;
  onPress: () => void;
  variant?: ButtonVariant;
  disabled?: boolean;
  loading?: boolean;
  testID?: string;
  loadingTestID?: string;
  accessibilityLabel?: string;
  icon?: ReactNode;
};

export function DriverButton({
  label,
  onPress,
  variant = 'primary',
  disabled = false,
  loading = false,
  testID,
  loadingTestID,
  accessibilityLabel,
  icon,
}: DriverButtonProps) {
  const palette =
    variant === 'secondary'
      ? styles.secondary
      : variant === 'danger'
        ? styles.danger
        : styles.primary;
  const textStyle =
    variant === 'secondary' ? styles.secondaryText : styles.onColorText;
  const spinner =
    variant === 'secondary' ? DriverTheme.colors.primary : DriverTheme.colors.textOnPrimary;

  const inactive = disabled || loading;

  return (
    <Pressable
      testID={testID}
      onPress={onPress}
      disabled={inactive}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: inactive, busy: loading }}
      style={({ pressed }) => [
        styles.base,
        palette,
        pressed && !inactive && styles.pressed,
        inactive && styles.disabled,
      ]}>
      {loading ? (
        <ActivityIndicator color={spinner} testID={loadingTestID} />
      ) : (
        <View style={styles.content}>
          {icon ? <View style={styles.icon}>{icon}</View> : null}
          <Text style={[styles.label, textStyle]}>{label}</Text>
        </View>
      )}
    </Pressable>
  );
}

export function PrimaryButton(props: Omit<DriverButtonProps, 'variant'>) {
  return <DriverButton {...props} variant="primary" />;
}

export function SecondaryButton(props: Omit<DriverButtonProps, 'variant'>) {
  return <DriverButton {...props} variant="secondary" />;
}

export function DangerButton(props: Omit<DriverButtonProps, 'variant'>) {
  return <DriverButton {...props} variant="danger" />;
}

const styles = StyleSheet.create({
  base: {
    minHeight: DriverTheme.control.buttonHeight,
    borderRadius: DriverTheme.radius.medium,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: DriverTheme.spacing.xl,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  icon: {
    marginRight: DriverTheme.spacing.sm,
  },
  label: {
    ...DriverTheme.typography.button,
    textAlign: 'center',
  },
  primary: {
    backgroundColor: '#1E75FF',
    borderRadius: 12,
    shadowColor: '#1E75FF',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  secondary: {
    backgroundColor: 'rgba(255, 255, 255, 0.06)',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.12)',
  },
  danger: {
    backgroundColor: DriverTheme.colors.danger,
    borderRadius: 12,
  },
  onColorText: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
  secondaryText: {
    color: '#F8FAFC',
    fontWeight: '600',
  },
  pressed: {
    opacity: 0.88,
  },
  disabled: {
    opacity: 0.5,
  },
});
