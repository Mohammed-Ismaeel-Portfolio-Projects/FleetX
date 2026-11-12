// @ts-nocheck
import React from 'react';
import { StyleSheet, View, Text, TouchableOpacity } from 'react-native';
import { Check } from 'lucide-react-native';
import { useTheme } from '@/hooks/use-theme';

interface CheckboxProps {
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  testID?: string;
}

export function Checkbox({ label, checked, onChange, testID }: CheckboxProps) {
  const theme = useTheme();

  return (
    <TouchableOpacity
      testID={testID}
      style={styles.container}
      activeOpacity={0.8}
      onPress={() => onChange(!checked)}
      accessibilityRole="checkbox"
      accessibilityLabel={label}
      accessibilityState={{ checked }}
    >
      <View
        style={[
          styles.checkbox,
          {
            borderColor: checked ? theme.primary : theme.border,
            backgroundColor: checked ? theme.primary : 'transparent',
          },
        ]}
      >
        {checked && <Check size={14} color="#FFFFFF" strokeWidth={3} />}
      </View>
      <Text style={[styles.label, { color: theme.textSecondary }]}>{label}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 4,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderWidth: 2,
    borderRadius: 6,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 8,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
  },
});
