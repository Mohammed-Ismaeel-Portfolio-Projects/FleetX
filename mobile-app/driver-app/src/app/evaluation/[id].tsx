import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';

import {
  DriverCard,
  DriverHeader,
  DriverScreen,
  InfoRow,
  SectionHeader,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import {
  getOwnEvaluationDetail,
  type DriverSelfEvaluationDetail,
} from '@/features/driverApi/performance';
import {
  formatScoreLabel,
  friendlyEventCode,
} from '@/features/driverApi/performanceDisplay';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

/**
 * Finalized evaluation explainability (DRIVER-EVAL-08E).
 * Own evaluations only via self API.
 */
export default function EvaluationDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const evaluationId = Number(id);
  const router = useRouter();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [detail, setDetail] = useState<DriverSelfEvaluationDetail | null>(null);

  const load = useCallback(async () => {
    if (!Number.isFinite(evaluationId) || evaluationId <= 0) {
      setStatus('error');
      setRetryable(false);
      setError('Evaluation not found.');
      return;
    }
    setStatus('loading');
    setError(null);
    try {
      const next = await getOwnEvaluationDetail(evaluationId);
      setDetail(next);
      setStatus('ready');
    } catch (e) {
      setDetail(null);
      setRetryable(isRetryableWorkError(e));
      setError(
        e instanceof DriverWorkApiError
          ? e.statusCode === 404
            ? 'Evaluation not found.'
            : e.message
          : 'Could not load evaluation.'
      );
      setStatus('error');
    }
  }, [evaluationId]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const header = (
    <DriverHeader title="Trip Evaluation" onBack={() => router.back()} />
  );

  if (status === 'loading') {
    return (
      <DriverScreen header={header} safeBottom>
        <ScreenLoading testID="evaluation-detail-loading" label="Loading evaluation…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (status === 'error' || !detail) {
    return (
      <DriverScreen header={header} padded safeBottom>
        <ScreenError
          testID="evaluation-detail-error"
          retryTestID="evaluation-detail-retry"
          title="Could not load evaluation"
          body={error || 'Evaluation not found.'}
          onRetry={retryable ? () => void load() : null}
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  return (
    <DriverScreen header={header} scroll padded safeBottom testID="evaluation-detail-screen">
      <SectionHeader label="Trip Performance" />
      <DriverCard testID="evaluation-detail-score">
        <Text style={styles.score}>
          {detail.overallScore != null
            ? `${detail.overallScore} / 100`
            : detail.status === 'incomplete'
              ? 'Insufficient trip data'
              : 'Score unavailable'}
        </Text>
        {detail.scoreLabel ? (
          <Text style={styles.label}>{formatScoreLabel(detail.scoreLabel)}</Text>
        ) : null}
        <InfoRow label="Confidence" value={detail.confidence ?? '—'} />
        <InfoRow
          label="Evidence"
          value={detail.evidenceQuality ? detail.evidenceQuality.replace(/_/g, ' ') : '—'}
        />
        <InfoRow label="Finalized" value={detail.finalizedAt ?? '—'} />
      </DriverCard>

      <SectionHeader label="Assignment" />
      <DriverCard>
        <InfoRow label="Code" value={detail.assignmentCode ?? `#${detail.assignmentId}`} />
        <InfoRow label="Shipment" value={detail.shipmentNumber ?? '—'} />
      </DriverCard>

      {detail.categoryScores ? (
        <>
          <SectionHeader label="Categories" />
          <DriverCard>
            {Object.entries(detail.categoryScores).map(([key, value]) => {
              const score =
                value && typeof value === 'object' && 'score' in value
                  ? (value as { score?: number | null }).score
                  : null;
              if (score == null) return null;
              return (
                <InfoRow
                  key={key}
                  label={key.replace(/_/g, ' ')}
                  value={String(score)}
                />
              );
            })}
          </DriverCard>
        </>
      ) : null}

      {detail.events.length > 0 ? (
        <>
          <SectionHeader label="Driving events" />
          <DriverCard>
            {detail.events.slice(0, 20).map((event) => (
              <Text key={event.id} style={styles.meta}>
                {friendlyEventCode(event.eventCode)}
                {event.occurredAt ? ` · ${event.occurredAt}` : ''}
              </Text>
            ))}
          </DriverCard>
        </>
      ) : null}
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  score: {
    fontSize: 22,
    fontWeight: '700',
    color: DriverTheme.colors.textPrimary,
    marginBottom: 4,
  },
  label: {
    fontSize: 15,
    fontWeight: '600',
    color: DriverTheme.colors.textSecondary,
    textTransform: 'capitalize',
    marginBottom: 8,
  },
  meta: {
    fontSize: 13,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'capitalize',
    marginBottom: 4,
  },
});
