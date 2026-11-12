// @ts-nocheck — lucide icon props vs RN ColorValue
import { StyleSheet, Text, View } from 'react-native';
import { Check, Circle } from 'lucide-react-native';

import type { CustomerJourneyModel, CustomerJourneyStep } from './shipmentWorkspace';

type ThemeColors = {
  text: string;
  textSecondary: string;
  primary: string;
  border: string;
  backgroundElement: string;
};

function StepIcon({
  step,
  theme,
}: {
  step: CustomerJourneyStep;
  theme: ThemeColors;
}) {
  if (step.visualState === 'completed') {
    return (
      <View
        style={[styles.iconWrap, { backgroundColor: theme.primary, borderColor: theme.primary }]}
        accessibilityElementsHidden
        importantForAccessibility="no">
        <Check size={12} color="#FFFFFF" strokeWidth={3} />
      </View>
    );
  }

  if (step.visualState === 'current') {
    return (
      <View
        style={[styles.iconWrap, { backgroundColor: theme.primary, borderColor: theme.primary }]}
        accessibilityElementsHidden
        importantForAccessibility="no">
        <View style={styles.currentInner} />
      </View>
    );
  }

  return (
    <View
      style={[styles.iconWrap, { backgroundColor: 'transparent', borderColor: theme.border }]}
      accessibilityElementsHidden
      importantForAccessibility="no">
      <Circle size={10} color={theme.textSecondary} />
    </View>
  );
}

/**
 * Compact customer journey for the Shipment Workspace (C2.9-C3.7).
 * Detailed pickup/delivery milestones remain on /shipment-tracking/[id].
 */
export function CustomerJourney({
  model,
  theme,
}: {
  model: CustomerJourneyModel;
  theme: ThemeColors;
}) {
  if (model.kind === 'cancelled') {
    return (
      <View
        accessibilityRole="summary"
        accessibilityLabel="This shipment was cancelled. It is not progressing.">
        <Text style={[styles.sectionTitle, { color: theme.text }]}>Journey</Text>
        <Text style={[styles.body, { color: theme.textSecondary }]} testID="shipment-journey-cancelled">
          This shipment was cancelled. It is not progressing through assignment, transit, or
          delivery.
        </Text>
      </View>
    );
  }

  if (model.kind === 'unknown') {
    return (
      <View
        accessibilityRole="summary"
        accessibilityLabel="Shipment journey is unavailable because the current status is not recognized.">
        <Text style={[styles.sectionTitle, { color: theme.text }]}>Journey</Text>
        <Text style={[styles.body, { color: theme.textSecondary }]} testID="shipment-journey-unknown">
          Status details are not available for this shipment.
        </Text>
      </View>
    );
  }

  const current = model.steps[model.currentIndex];
  const summary = current
    ? `Current stage ${current.label}. ${model.currentIndex} completed. ${
        model.steps.length - model.currentIndex - 1
      } upcoming.`
    : 'Shipment journey';

  return (
    <View
      testID="shipment-customer-journey"
      accessibilityRole="summary"
      accessibilityLabel={summary}>
      <Text style={[styles.sectionTitle, { color: theme.text }]}>Journey</Text>
      {model.steps.map((step, index) => {
        const isLast = index === model.steps.length - 1;
        return (
          <View key={step.key} testID={`shipment-journey-step-${step.key}`}>
            <View
              style={styles.stepRow}
              accessible
              accessibilityLabel={`${step.stateLabel}. ${step.label}. ${step.explanation}${
                step.dateLine ? `. ${step.dateLine.text}` : ''
              }`}>
              <View style={styles.rail}>
                <StepIcon step={step} theme={theme} />
                {isLast ? null : (
                  <View
                    style={[
                      styles.connector,
                      {
                        backgroundColor:
                          step.visualState === 'upcoming' ? theme.border : theme.primary,
                      },
                    ]}
                  />
                )}
              </View>
              <View style={styles.stepBody}>
                <View style={styles.stepHeader}>
                  <Text
                    style={[
                      styles.stepLabel,
                      {
                        color: step.visualState === 'upcoming' ? theme.textSecondary : theme.text,
                        fontWeight: step.visualState === 'current' ? '700' : '600',
                      },
                    ]}
                    numberOfLines={2}>
                    {step.label}
                  </Text>
                  <Text
                    style={[
                      styles.stateChip,
                      {
                        color:
                          step.visualState === 'current' ? theme.primary : theme.textSecondary,
                      },
                    ]}
                    testID={`shipment-journey-state-${step.key}`}>
                    {step.stateLabel}
                  </Text>
                </View>
                <Text style={[styles.body, { color: theme.textSecondary }]}>{step.explanation}</Text>
                {step.dateLine ? (
                  <Text
                    style={[
                      styles.dateLine,
                      {
                        color: theme.textSecondary,
                        fontStyle: step.dateLine.kind === 'missing' ? 'italic' : 'normal',
                      },
                    ]}
                    testID={`shipment-journey-date-${step.key}`}>
                    {step.dateLine.text}
                  </Text>
                ) : null}
              </View>
            </View>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  sectionTitle: { fontSize: 15, fontWeight: '700', marginBottom: 10 },
  body: { fontSize: 13, lineHeight: 18 },
  stepRow: { flexDirection: 'row', alignItems: 'stretch', gap: 12 },
  rail: { width: 22, alignItems: 'center' },
  iconWrap: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  currentInner: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#FFFFFF',
  },
  connector: {
    width: 2,
    flex: 1,
    minHeight: 12,
    marginTop: 2,
    marginBottom: 2,
  },
  stepBody: { flex: 1, paddingBottom: 14 },
  stepHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 8,
    marginBottom: 4,
  },
  stepLabel: { flex: 1, fontSize: 14, lineHeight: 20 },
  stateChip: { fontSize: 11, fontWeight: '700', marginTop: 2 },
  dateLine: { fontSize: 12, lineHeight: 16, marginTop: 4 },
});
