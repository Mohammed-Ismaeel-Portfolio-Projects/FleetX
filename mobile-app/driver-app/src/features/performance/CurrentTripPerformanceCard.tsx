import { StyleSheet, Text, View } from 'react-native';

import { DriverCard, InfoRow, SectionHeader } from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import type { DriverSelfCurrentAssignmentEvaluation } from '@/features/driverApi/performance';
import {
  formatCurrentEvaluationUiState,
  formatCurrentTripScoreLine,
  formatScoreLabel,
  friendlyEventCode,
} from '@/features/driverApi/performanceDisplay';

interface CurrentTripPerformanceCardProps {
  evaluation: DriverSelfCurrentAssignmentEvaluation | null;
  loading?: boolean;
  error?: string | null;
  /** Active trip vs post-trip wording. */
  title?: string;
  showRecentEvents?: boolean;
  testID?: string;
}

/**
 * Compact trip evaluation presentation (DRIVER-EVAL-08E).
 * Never invents scores — backend values only.
 */
export function CurrentTripPerformanceCard({
  evaluation,
  loading = false,
  error = null,
  title = 'Current Trip Performance',
  showRecentEvents = true,
  testID = 'trip-performance',
}: CurrentTripPerformanceCardProps) {
  return (
    <View testID={testID}>
      <SectionHeader label={title} />
      <DriverCard testID={`${testID}-card`}>
        {loading ? (
          <Text style={styles.muted} testID={`${testID}-loading`}>
            Loading performance...
          </Text>
        ) : null}
        {error ? (
          <Text style={styles.error} testID={`${testID}-error`}>
            Performance unavailable
          </Text>
        ) : null}
        {!loading && !error && evaluation ? (
          <>
            <Text style={styles.state} testID={`${testID}-state`}>
              {formatCurrentEvaluationUiState(evaluation.uiState)}
            </Text>
            <InfoRow
              label="Trip score"
              value={formatCurrentTripScoreLine({
                evaluationAvailable: evaluation.evaluationAvailable,
                uiState: evaluation.uiState,
                scoreAvailable: evaluation.scoreAvailable,
                overallScore: evaluation.overallScore,
              })}
              testID={`${testID}-score`}
            />
            {evaluation.scoreAvailable ? (
              <InfoRow
                label="Label"
                value={formatScoreLabel(evaluation.scoreLabel)}
                testID={`${testID}-label`}
              />
            ) : null}
            {evaluation.evidenceQuality ? (
              <InfoRow label="Evidence" value={evaluation.evidenceQuality.replace(/_/g, ' ')} />
            ) : null}
            <InfoRow label="Events" value={String(evaluation.eventCount)} />
            {showRecentEvents && evaluation.recentEvents.length > 0 ? (
              <View style={styles.events}>
                <Text style={styles.help}>Recent driving events</Text>
                {evaluation.recentEvents.slice(0, 5).map((event) => (
                  <Text key={event.id} style={styles.meta}>
                    {friendlyEventCode(event.eventCode)}
                  </Text>
                ))}
              </View>
            ) : null}
          </>
        ) : null}
        {!loading && !error && !evaluation ? (
          <Text style={styles.muted} testID={`${testID}-empty`}>
            Evaluation not available yet
          </Text>
        ) : null}
      </DriverCard>
    </View>
  );
}

const styles = StyleSheet.create({
  state: {
    fontSize: 16,
    fontWeight: '700',
    color: DriverTheme.colors.textPrimary,
    marginBottom: 8,
    textTransform: 'capitalize',
  },
  muted: {
    color: DriverTheme.colors.textMuted,
    fontSize: 14,
  },
  error: {
    color: DriverTheme.colors.danger,
    fontSize: 14,
  },
  help: {
    marginTop: 8,
    marginBottom: 4,
    fontSize: 12,
    fontWeight: '600',
    color: DriverTheme.colors.textSecondary,
  },
  meta: {
    fontSize: 13,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'capitalize',
  },
  events: {
    marginTop: 4,
  },
});
