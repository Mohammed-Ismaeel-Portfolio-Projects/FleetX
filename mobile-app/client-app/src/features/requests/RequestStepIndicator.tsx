// @ts-nocheck — lucide icon props vs RN ColorValue
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { REQUEST_WIZARD_STEPS } from './requestWizard';

type ThemeColors = {
  text: string;
  textSecondary: string;
  primary: string;
  border: string;
  backgroundElement: string;
};

export function RequestStepIndicator({
  current,
  reviewMode,
  onSelectEditStep,
  onSelectReview,
  theme,
}: {
  current: number;
  reviewMode: boolean;
  onSelectEditStep: (step: 1 | 2 | 3 | 4 | 5) => void;
  onSelectReview: () => void;
  theme: ThemeColors;
}) {
  const active = reviewMode ? 6 : current;

  return (
    <View
      testID="request-step-indicator"
      style={styles.wrap}
      accessibilityRole="tablist"
      accessibilityLabel={`Request step ${active} of 6`}>
      {REQUEST_WIZARD_STEPS.map((item, index) => {
        const isActive = item.step === active;
        const isDone = item.step < active;
        const isReview = item.step === 6;
        return (
          <View key={item.key} style={styles.item}>
            {index > 0 ? (
              <View
                style={[
                  styles.connector,
                  { backgroundColor: isDone || isActive ? theme.primary : theme.border },
                ]}
              />
            ) : null}
            <Pressable
              testID={`request-step-${item.key}`}
              onPress={() => {
                if (isReview) {
                  onSelectReview();
                  return;
                }
                onSelectEditStep(item.step as 1 | 2 | 3 | 4 | 5);
              }}
              accessibilityRole="tab"
              accessibilityState={{ selected: isActive }}
              accessibilityLabel={`${item.label}, step ${item.step} of 6`}
              style={styles.tab}>
              <View
                style={[
                  styles.dot,
                  {
                    backgroundColor: isActive || isDone ? theme.primary : theme.backgroundElement,
                    borderColor: isActive || isDone ? theme.primary : theme.border,
                  },
                ]}>
                <Text style={[styles.dotText, { color: isActive || isDone ? '#FFFFFF' : theme.textSecondary }]}>
                  {item.step}
                </Text>
              </View>
              <Text
                style={[
                  styles.label,
                  { color: isActive ? theme.text : theme.textSecondary, fontWeight: isActive ? '700' : '600' },
                ]}
                numberOfLines={1}>
                {item.label}
              </Text>
            </Pressable>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 4,
  },
  item: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    minWidth: 0,
  },
  connector: {
    position: 'absolute',
    left: -8,
    right: '50%',
    top: 12,
    height: 2,
    borderRadius: 1,
  },
  tab: {
    flex: 1,
    alignItems: 'center',
    gap: 4,
    minWidth: 0,
  },
  dot: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dotText: {
    fontSize: 11,
    fontWeight: '700',
  },
  label: {
    fontSize: 9,
    letterSpacing: 0.2,
  },
});
