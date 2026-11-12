// @ts-nocheck
import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View, ActivityIndicator } from 'react-native';
import { useTheme } from '@/hooks/use-theme';

interface CustomButtonProps {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'outline' | 'link';
  loading?: boolean;
  disabled?: boolean;
  icon?: React.ReactNode;
  accessibilityLabel?: string;
}

export function CustomButton({
  title,
  onPress,
  variant = 'primary',
  loading = false,
  disabled = false,
  icon,
  accessibilityLabel,
}: CustomButtonProps) {
  const theme = useTheme();

  const isPrimary = variant === 'primary';
  const isOutline = variant === 'outline';
  const isLink = variant === 'link';

  const buttonStyle = [
    styles.button,
    isPrimary && [styles.primaryButton, { backgroundColor: theme.primary }],
    isOutline && [styles.outlineButton, { borderColor: theme.border, backgroundColor: theme.inputBg }],
    isLink && styles.linkButton,
    (disabled || loading) && styles.disabled,
  ];

  const textStyle = [
    styles.text,
    isPrimary && styles.primaryText,
    isOutline && [styles.outlineText, { color: theme.text }],
    isLink && [styles.linkText, { color: theme.primary }],
  ];

  return (
    <TouchableOpacity
      style={buttonStyle}
      onPress={onPress}
      activeOpacity={0.8}
      disabled={disabled || loading}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? title}
      accessibilityState={{ disabled: disabled || loading, busy: loading }}
    >
      {loading ? (
        <ActivityIndicator size="small" color={isPrimary ? '#FFFFFF' : theme.primary} />
      ) : (
        <View style={styles.contentContainer}>
          {icon && <View style={styles.iconContainer}>{icon}</View>}
          <Text style={textStyle}>{title}</Text>
        </View>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  button: {
    height: 52,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    width: '100%',
    marginVertical: 8,
  },
  contentContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconContainer: {
    marginRight: 10,
  },
  primaryButton: {
    shadowColor: '#1E75FF',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  outlineButton: {
    borderWidth: 1.5,
  },
  linkButton: {
    height: 'auto',
    backgroundColor: 'transparent',
    paddingVertical: 4,
    marginVertical: 4,
  },
  text: {
    fontSize: 16,
    fontWeight: '600',
  },
  primaryText: {
    color: '#FFFFFF',
  },
  outlineText: {
    fontWeight: '500',
  },
  linkText: {
    fontSize: 14,
    fontWeight: '600',
  },
  disabled: {
    opacity: 0.6,
  },
});
