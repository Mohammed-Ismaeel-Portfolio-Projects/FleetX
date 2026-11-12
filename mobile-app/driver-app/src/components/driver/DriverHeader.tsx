import type { ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft } from 'lucide-react-native';

import { DriverTheme } from './theme';

type DriverHeaderProps = {
  title: string;
  subtitle?: string | null;
  statusLabel?: string | null;
  statusAvailable?: boolean;
  rightAction?: ReactNode;
  onBack?: () => void;
  titleTestID?: string;
  statusTestID?: string;
  subtitleTestID?: string;
};

export function DriverHeader({
  title,
  subtitle,
  statusLabel,
  statusAvailable,
  rightAction,
  onBack,
  titleTestID,
  statusTestID,
  subtitleTestID,
}: DriverHeaderProps) {
  return (
    <View style={styles.wrap}>
      <View style={styles.row}>
        {onBack ? (
          <Pressable
            onPress={onBack}
            hitSlop={8}
            style={styles.back}
            accessibilityRole="button"
            accessibilityLabel="Go back">
            <ChevronLeft size={24} color={DriverTheme.colors.textOnNavy} />
          </Pressable>
        ) : null}
        <View style={styles.titles}>
          <Text style={styles.title} testID={titleTestID} accessibilityRole="header">
            {title}
          </Text>
          {statusLabel ? (
            <View style={styles.statusRow}>
              <View
                style={[
                  styles.dot,
                  {
                    backgroundColor: statusAvailable
                      ? DriverTheme.colors.success
                      : DriverTheme.colors.textMuted,
                  },
                ]}
                accessibilityElementsHidden
                importantForAccessibility="no"
              />
              <Text style={styles.status} testID={statusTestID}>
                {statusLabel}
              </Text>
            </View>
          ) : null}
          {subtitle || subtitleTestID ? (
            <Text style={styles.subtitle} testID={subtitleTestID}>
              {subtitle ?? ''}
            </Text>
          ) : null}
        </View>
        {rightAction ? <View style={styles.right}>{rightAction}</View> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 16,
    backgroundColor: '#080B16',
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255, 255, 255, 0.08)',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  back: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 8,
    marginLeft: -8,
  },
  titles: {
    flex: 1,
    gap: 4,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    color: '#FFFFFF',
    letterSpacing: -0.3,
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 2,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  status: {
    fontSize: 13,
    fontWeight: '600',
    color: '#34D399',
  },
  subtitle: {
    fontSize: 13,
    color: '#94A3B8',
    marginTop: 2,
  },
  right: {
    marginLeft: 12,
    justifyContent: 'center',
    minHeight: 44,
  },
});
