import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { CircleCheck } from 'lucide-react-native';

import {
  BottomActionBar,
  DriverCard,
  DriverHeader,
  DriverScreen,
  InfoRow,
  PrimaryButton,
  SectionHeader,
  StatusBadge,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { getAssignment } from '@/features/driverApi/work';
import {
  getOwnCurrentAssignmentEvaluation,
  type DriverSelfCurrentAssignmentEvaluation,
} from '@/features/driverApi/performance';
import { CurrentTripPerformanceCard } from '@/features/performance/CurrentTripPerformanceCard';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

/**
 * Delivery completed confirmation (D7 + D9 server re-fetch).
 */
export default function DeliveryCompleteScreen() {
  const { id, ref: refParam, at: atParam } = useLocalSearchParams<{
    id: string;
    ref?: string;
    at?: string;
  }>();
  const assignmentId = String(id || '');
  const router = useRouter();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [shipmentRef, setShipmentRef] = useState<string | null>(
    typeof refParam === 'string' && refParam.trim() ? refParam.trim() : null
  );
  const [completedAt, setCompletedAt] = useState<string | null>(
    typeof atParam === 'string' && atParam.trim() ? atParam.trim() : null
  );
  const [tripPerformance, setTripPerformance] =
    useState<DriverSelfCurrentAssignmentEvaluation | null>(null);
  const [tripPerformanceLoading, setTripPerformanceLoading] = useState(false);
  const [tripPerformanceError, setTripPerformanceError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!assignmentId) {
      setStatus('error');
      setRetryable(false);
      setError('Assignment not found.');
      return;
    }
    setStatus('loading');
    setError(null);
    try {
      const assignment = await getAssignment(assignmentId);
      const key = assignment.status?.key;
      if (key !== 'completed' || assignment.isActive) {
        setStatus('error');
        setRetryable(false);
        setError('Completion could not be confirmed. Return to Work and refresh.');
        return;
      }
      setShipmentRef(assignment.shipment?.shipmentNumber ?? null);
      setCompletedAt(assignment.endedAt ?? null);
      setStatus('ready');
    } catch (e) {
      setRetryable(isRetryableWorkError(e));
      setError(
        e instanceof DriverWorkApiError ? e.message : 'Could not confirm completion.'
      );
      setStatus('error');
      return;
    }

    setTripPerformanceLoading(true);
    try {
      const perf = await getOwnCurrentAssignmentEvaluation(assignmentId);
      setTripPerformance(perf);
      setTripPerformanceError(null);
    } catch (e) {
      setTripPerformance(null);
      setTripPerformanceError(
        e instanceof DriverWorkApiError ? e.message : 'Performance unavailable'
      );
    } finally {
      setTripPerformanceLoading(false);
    }
  }, [assignmentId]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const goWork = () => router.replace('/(tabs)' as Href);

  const header = (
    <DriverHeader
      title="Delivery Complete"
      statusLabel={status === 'ready' ? 'Completed' : null}
      statusAvailable={status === 'ready'}
      onBack={() => router.back()}
    />
  );

  if (status === 'loading') {
    return (
      <DriverScreen header={header} padded safeBottom>
        <ScreenLoading
          testID="delivery-complete-loading"
          label="Confirming completion…"
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  if (status === 'error') {
    return (
      <DriverScreen
        header={header}
        padded
        footer={
          <BottomActionBar>
            <PrimaryButton
              testID="delivery-complete-work-fallback"
              label="Back to Work"
              onPress={goWork}
            />
          </BottomActionBar>
        }>
        <ScreenError
          testID="delivery-complete-error"
          retryTestID="delivery-complete-retry"
          title="Could not confirm completion"
          body={error || 'Could not confirm completion.'}
          onRetry={retryable ? () => void load() : null}
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  return (
    <DriverScreen
      header={header}
      padded
      testID="delivery-complete-screen"
      footer={
        <BottomActionBar>
          <PrimaryButton
            testID="delivery-complete-work"
            label="Back to Work"
            onPress={goWork}
          />
        </BottomActionBar>
      }>
      <View style={styles.stack}>
        <DriverCard>
          <View style={styles.successMark} accessibilityLabel="Delivery complete">
            <CircleCheck size={40} color={DriverTheme.colors.success} />
          </View>
          <Text style={styles.successTitle} testID="delivery-complete-title">
            Delivery completed
          </Text>
          <Text style={styles.body}>
            The delivery has been successfully completed.
          </Text>
          <StatusBadge label="Completed" tone="available" />
        </DriverCard>

        {shipmentRef || completedAt ? (
          <>
            <SectionHeader label="Completion summary" />
            <DriverCard>
              {shipmentRef ? (
                <InfoRow
                  label="Shipment"
                  value={shipmentRef}
                  testID="delivery-complete-ref"
                />
              ) : null}
              {completedAt ? (
                <InfoRow
                  label="Completed"
                  value={completedAt}
                  testID="delivery-complete-at"
                />
              ) : null}
              <Text style={styles.meta}>You are available for the next assignment.</Text>
            </DriverCard>
          </>
        ) : (
          <DriverCard>
            <Text style={styles.meta}>You are available for the next assignment.</Text>
          </DriverCard>
        )}

        <CurrentTripPerformanceCard
          evaluation={tripPerformance}
          loading={tripPerformanceLoading}
          error={tripPerformanceError}
          title="Trip Performance"
          showRecentEvents={false}
          testID="delivery-complete-performance"
        />
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  stack: {
    flex: 1,
    justifyContent: 'center',
    gap: DriverTheme.spacing.lg,
    paddingVertical: DriverTheme.spacing.xl,
  },
  successMark: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: DriverTheme.colors.successSoft,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'center',
    marginBottom: DriverTheme.spacing.sm,
  },
  successTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    textAlign: 'center',
  },
  body: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
    textAlign: 'center',
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
});
