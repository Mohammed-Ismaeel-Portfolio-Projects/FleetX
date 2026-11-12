// @ts-nocheck
import React, { useState } from 'react';
import { StyleSheet, View, Text, TextInput, TextInputProps, TouchableOpacity } from 'react-native';
import { LucideIcon, Eye, EyeOff } from 'lucide-react-native';
import { useTheme } from '@/hooks/use-theme';

interface InputFieldProps extends TextInputProps {
  label?: string;
  icon?: LucideIcon;
  error?: string;
  secureTextEntry?: boolean;
}

export function InputField({
  label,
  icon: Icon,
  error,
  secureTextEntry,
  style,
  accessibilityLabel,
  ...rest
}: InputFieldProps) {
  const theme = useTheme();
  const [isFocused, setIsFocused] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const isPassword = secureTextEntry === true;
  const shouldSecureText = isPassword && !showPassword;
  const fieldLabel = accessibilityLabel ?? label;

  return (
    <View style={styles.container}>
      {label && <Text style={[styles.label, { color: theme.textSecondary }]}>{label}</Text>}

      <View
        style={[
          styles.inputContainer,
          {
            backgroundColor: theme.inputBg,
            borderColor: error ? '#EF4444' : isFocused ? theme.primary : theme.border,
          },
        ]}>
        {Icon && (
          <View style={styles.iconContainer}>
            <Icon size={20} color={isFocused ? theme.primary : theme.textSecondary} />
          </View>
        )}

        <TextInput
          style={[
            styles.input,
            { color: theme.text },
            style,
          ]}
          placeholderTextColor={theme.textSecondary}
          secureTextEntry={shouldSecureText}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setIsFocused(false)}
          autoCapitalize="none"
          accessibilityLabel={fieldLabel}
          {...rest}
        />

        {isPassword && (
          <TouchableOpacity
            style={styles.eyeButton}
            onPress={() => setShowPassword(!showPassword)}
            activeOpacity={0.7}
            accessibilityRole="button"
            accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}
            accessibilityHint="Toggles password visibility">
            {showPassword ? (
              <EyeOff size={20} color={theme.textSecondary} />
            ) : (
              <Eye size={20} color={theme.textSecondary} />
            )}
          </TouchableOpacity>
        )}
      </View>

      {error && <Text style={styles.errorText}>{error}</Text>}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginBottom: 16,
    width: '100%',
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 6,
    paddingLeft: 4,
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    height: 52,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingHorizontal: 12,
  },
  iconContainer: {
    marginRight: 10,
  },
  input: {
    flex: 1,
    height: '100%',
    fontSize: 16,
    fontWeight: '500',
    paddingVertical: 12,
  },
  eyeButton: {
    padding: 8,
  },
  errorText: {
    color: '#EF4444',
    fontSize: 12,
    marginTop: 4,
    fontWeight: '500',
    paddingLeft: 4,
  },
});
