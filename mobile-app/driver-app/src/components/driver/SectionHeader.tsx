import { StyleSheet, Text, View } from 'react-native';

import { DriverTheme } from './theme';

type SectionHeaderProps = {
  label: string;
  testID?: string;
};

export function SectionHeader({ label, testID }: SectionHeaderProps) {
  return (
    <View style={styles.wrap} testID={testID}>
      <Text style={styles.label}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: DriverTheme.spacing.md,
    marginBottom: DriverTheme.spacing.xs,
  },
  label: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
    color: '#94A3B8',
    textTransform: 'uppercase',
  },
});
