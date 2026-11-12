// @ts-nocheck — lucide icon props vs RN ColorValue
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import type { PricingEstimateViewState } from './useRequestPricingEstimate';
import {
  estimateChargeRows,
  formatEstimateDistanceKm,
  formatEstimateMoney,
} from './pricingEstimateFormat';

type ThemeColors = {
  text: string;
  textSecondary: string;
  primary: string;
  border: string;
  inputBg: string;
};

/**
 * Review-only estimated cost panel. Formats backend values only — no pricing math.
 */
export function RequestPricingEstimateCard({
  state,
  theme,
}: {
  state: PricingEstimateViewState;
  theme: ThemeColors;
}) {
  const totalDisplay =
    state.status === 'success' && state.estimate
      ? formatEstimateMoney(state.estimate.total, state.estimate.currency)
      : null;

  return (
    <View
      testID="request-pricing-estimate"
      style={[styles.card, { borderColor: theme.border, backgroundColor: theme.inputBg }]}
      accessibilityLabel="Estimated shipment cost">
      <Text style={[styles.title, { color: theme.text }]}>Estimated shipment cost</Text>
      <Text style={[styles.disclaimer, { color: theme.textSecondary }]}>
        This is an estimated cost. The final price is calculated when the shipment request is
        submitted.
      </Text>

      {state.status === 'loading' || state.status === 'idle' ? (
        <View testID="request-pricing-estimate-loading" style={styles.centerRow}>
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.body, { color: theme.textSecondary }]}>
            Calculating estimated cost…
          </Text>
        </View>
      ) : null}

      {state.status === 'incomplete' ? (
        <Text testID="request-pricing-estimate-incomplete" style={[styles.body, { color: theme.text }]}>
          {state.incompleteMessage || 'Estimated cost is unavailable until pricing details are complete.'}
        </Text>
      ) : null}

      {state.status === 'error' ? (
        <View testID="request-pricing-estimate-error" style={styles.errorBlock}>
          <Text style={[styles.body, { color: theme.text }]}>
            {state.errorMessage || 'Unable to calculate the estimated cost.'}
          </Text>
          <Pressable
            testID="request-pricing-estimate-retry"
            onPress={state.retry}
            style={[styles.retryBtn, { backgroundColor: theme.primary }]}
            accessibilityRole="button"
            accessibilityLabel="Retry estimated cost">
            <Text style={styles.retryText}>Retry</Text>
          </Pressable>
        </View>
      ) : null}

      {state.status === 'success' && state.estimate && totalDisplay ? (
        <View testID="request-pricing-estimate-success">
          <Text testID="request-pricing-estimate-total" style={[styles.total, { color: theme.text }]}>
            {totalDisplay}
          </Text>
          {formatEstimateDistanceKm(state.estimate.distance_km) ? (
            <Text style={[styles.meta, { color: theme.textSecondary }]}>
              Route distance ≈ {formatEstimateDistanceKm(state.estimate.distance_km)}
            </Text>
          ) : null}

          {estimateChargeRows(state.estimate.charges, state.estimate.currency).map((row) => (
            <View key={row.key} style={styles.row}>
              <Text style={[styles.rowLabel, { color: theme.textSecondary }]}>{row.label}</Text>
              <Text style={[styles.rowValue, { color: theme.text }]}>{row.display}</Text>
            </View>
          ))}

          {state.estimate.discount.amount > 0 &&
          formatEstimateMoney(state.estimate.discount.amount, state.estimate.currency) ? (
            <View style={styles.row}>
              <Text style={[styles.rowLabel, { color: theme.textSecondary }]}>Discount</Text>
              <Text style={[styles.rowValue, { color: theme.text }]}>
                −{formatEstimateMoney(state.estimate.discount.amount, state.estimate.currency)}
              </Text>
            </View>
          ) : null}

          {state.estimate.tax.amount > 0 &&
          formatEstimateMoney(state.estimate.tax.amount, state.estimate.currency) ? (
            <View style={styles.row}>
              <Text style={[styles.rowLabel, { color: theme.textSecondary }]}>Tax</Text>
              <Text style={[styles.rowValue, { color: theme.text }]}>
                {formatEstimateMoney(state.estimate.tax.amount, state.estimate.currency)}
              </Text>
            </View>
          ) : null}

          {state.estimate.warnings?.length ? (
            <Text style={[styles.warning, { color: theme.textSecondary }]}>
              {state.estimate.warnings[0]}
            </Text>
          ) : null}
        </View>
      ) : null}

      {state.status === 'success' && state.estimate && !totalDisplay ? (
        <Text testID="request-pricing-estimate-invalid" style={[styles.body, { color: theme.text }]}>
          Unable to calculate the estimated cost.
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
    gap: 8,
    marginBottom: 12,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
  },
  disclaimer: {
    fontSize: 12,
    lineHeight: 17,
  },
  body: {
    fontSize: 14,
    lineHeight: 20,
  },
  centerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
  },
  errorBlock: {
    gap: 10,
  },
  retryBtn: {
    alignSelf: 'flex-start',
    borderRadius: 8,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  retryText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 13,
  },
  total: {
    fontSize: 22,
    fontWeight: '800',
    marginTop: 4,
  },
  meta: {
    fontSize: 12,
    marginBottom: 4,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 2,
  },
  rowLabel: {
    fontSize: 13,
    flex: 1,
  },
  rowValue: {
    fontSize: 13,
    fontWeight: '600',
  },
  warning: {
    fontSize: 11,
    marginTop: 6,
    fontStyle: 'italic',
  },
});
