import { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ArrowDown, MapPin, Navigation, Truck } from 'lucide-react-native';

import { OfflineBanner } from '@/components/OfflineBanner';
import {
  DriverCard,
  DriverHeader,
  DriverScreen,
  InfoRow,
  SecondaryButton,
  SectionHeader,
  StatusBadge,
  statusToneFromLabel,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { phaseStatusLabel } from '@/features/driverApi/executionPhase';
import { getAssignmentSummary } from '@/features/driverApi/history';
import { formatHistoryPlace } from '@/features/driverApi/historyMapper';
import type { DriverAssignmentSummary } from '@/features/driverApi/historyTypes';
import {
  getOwnCurrentAssignmentEvaluation,
  type DriverSelfCurrentAssignmentEvaluation,
} from '@/features/driverApi/performance';
import { CurrentTripPerformanceCard } from '@/features/performance/CurrentTripPerformanceCard';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { useNetworkStatus } from '@/lib/network/useNetworkStatus';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

function vehicleLine(summary: DriverAssignmentSummary): string | null {
  const plate = summary.vehicle?.plateNumber?.trim() || null;
  const fleet = summary.vehicle?.fleetNumber?.trim() || null;
  const bits = [plate, fleet && fleet !== plate ? fleet : null].filter(Boolean);
  const trailer =
    summary.trailer?.trailerCode || summary.trailer?.plateNumber || null;
  if (bits.length === 0 && !trailer) return null;
  const line = bits.length ? bits.join(' · ') : null;
  return trailer ? (line ? `${line} · Trailer ${trailer}` : `Trailer ${trailer}`) : line;
}

/**
 * Read-only historical trip summary (D11). No mutation CTAs.
 */
export default function HistorySummaryScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const assignmentId = String(id || '');
  const router = useRouter();
  const { online } = useNetworkStatus();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error' | 'blocked'>('loading');
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [summary, setSummary] = useState<DriverAssignmentSummary | null>(null);
  const [tripPerformance, setTripPerformance] =
    useState<DriverSelfCurrentAssignmentEvaluation | null>(null);
  const [tripPerformanceLoading, setTripPerformanceLoading] = useState(false);
  const [tripPerformanceError, setTripPerformanceError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!assignmentId) {
      setStatus('error');
      setRetryable(false);
      setError('This assignment is no longer available.');
      return;
    }
    setStatus('loading');
    setError(null);
    try {
      const next = await getAssignmentSummary(assignmentId);
      setSummary(next);
      setStatus('ready');
    } catch (e) {
      const code = e instanceof DriverWorkApiError ? e.statusCode : undefined;
      if (code === 404) {
        setRetryable(false);
        setError('This assignment is no longer available.');
        setStatus('error');
        return;
      }
      if (code === 409) {
        setRetryable(false);
        setError(
          e instanceof DriverWorkApiError
            ? e.message
            : 'This assignment is still in progress. Return to Work.'
        );
        setStatus('blocked');
        return;
      }
      setRetryable(isRetryableWorkError(e));
      setError(e instanceof DriverWorkApiError ? e.message : 'Could not load trip summary.');
      setStatus('error');
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

  const header = (
    <DriverHeader title="Trip Summary" onBack={() => router.back()} />
  );

  if (status === 'loading') {
    return (
      <DriverScreen header={header} safeBottom>
        <ScreenLoading testID="history-summary-loading" label="Loading summary…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (status === 'error' || status === 'blocked' || !summary) {
    return (
      <DriverScreen header={header} padded safeBottom>
        <View style={styles.errorWrap}>
          <ScreenError
            testID="history-summary-error"
            retryTestID="history-summary-retry"
            title={status === 'blocked' ? 'Trip still in progress' : 'Could not load summary'}
            body={error || 'This assignment is no longer available.'}
            onRetry={status === 'error' && retryable ? () => void load() : null}
            theme={uxTheme}
          />
          <SecondaryButton
            testID="history-summary-back"
            label={status === 'blocked' ? 'Back to Work' : 'Back to History'}
            onPress={() =>
              router.replace((status === 'blocked' ? '/(tabs)' : '/history') as Href)
            }
            accessibilityLabel="Back"
          />
        </View>
      </DriverScreen>
    );
  }

  const shipment = summary.shipment;
  const assets = vehicleLine(summary);

  return (
    <DriverScreen
      header={header}
      testID="history-summary-screen"
      scroll
      padded
      safeBottom>
      {!online ? <OfflineBanner testID="history-summary-offline" /> : null}

      <SectionHeader label="Assignment summary" />
      <DriverCard testID="history-summary-outcome">
        <Text style={styles.kicker}>This trip is finished</Text>
        <View style={styles.titleRow}>
          <Text style={styles.title}>
            {summary.assignmentCode || `Assignment #${summary.id}`}
          </Text>
          <StatusBadge
            label={summary.outcome.label}
            tone={statusToneFromLabel(summary.outcome.key || summary.outcome.label)}
          />
        </View>
        <Text style={styles.body}>{summary.outcome.label}</Text>
        <Text style={styles.meta}>
          {summary.status?.name ?? 'Status unavailable'}
          {summary.endedAt ? ` · ${summary.endedAt}` : ''}
        </Text>
      </DriverCard>

      <CurrentTripPerformanceCard
        evaluation={tripPerformance}
        loading={tripPerformanceLoading}
        error={tripPerformanceError}
        title="Trip Performance"
        showRecentEvents={false}
        testID="history-trip-performance"
      />

      <SectionHeader label="Shipment" />
      <DriverCard testID="history-summary-shipment">
        <Text style={styles.body}>{shipment?.shipmentNumber ?? 'Unavailable'}</Text>
        {shipment?.referenceNumber ? (
          <Text style={styles.meta}>Ref: {shipment.referenceNumber}</Text>
        ) : null}
        <Text style={styles.meta}>{shipment?.status ?? 'Shipment status unavailable'}</Text>
      </DriverCard>

      <SectionHeader label="Route" />
      <DriverCard testID="history-summary-pickup">
        <InfoRow
          label="Pickup"
          value={shipment ? formatHistoryPlace(shipment.pickup) : 'Unavailable'}
          icon={<MapPin size={18} color={DriverTheme.colors.primary} />}
        />
        {shipment?.pickup.address ? (
          <Text style={styles.meta}>{shipment.pickup.address}</Text>
        ) : null}
      </DriverCard>
      <View style={styles.arrow} accessibilityElementsHidden>
        <ArrowDown size={16} color={DriverTheme.colors.textMuted} />
      </View>
      <DriverCard testID="history-summary-delivery">
        <InfoRow
          label="Delivery"
          value={shipment ? formatHistoryPlace(shipment.delivery) : 'Unavailable'}
          icon={<Navigation size={18} color={DriverTheme.colors.primary} />}
        />
        {shipment?.delivery.address ? (
          <Text style={styles.meta}>{shipment.delivery.address}</Text>
        ) : null}
      </DriverCard>

      {assets ? (
        <DriverCard>
          <InfoRow
            label="Vehicle"
            value={assets}
            icon={<Truck size={18} color={DriverTheme.colors.primary} />}
          />
        </DriverCard>
      ) : null}

      <SectionHeader label="Timing" />
      <DriverCard testID="history-summary-execution">
        <Text style={styles.body}>
          Final phase:{' '}
          {summary.executionPhase ? phaseStatusLabel(summary.executionPhase) : 'Unavailable'}
        </Text>
        <Text style={styles.meta}>Assigned: {summary.assignedAt ?? 'Unavailable'}</Text>
        <Text style={styles.meta}>Ended: {summary.endedAt ?? 'Unavailable'}</Text>
        <Text style={styles.meta}>
          Pickup verified:{' '}
          {summary.verification.pickup.verified
            ? summary.verification.pickup.verifiedAt ?? 'Yes'
            : 'No'}
        </Text>
        <Text style={styles.meta}>
          Delivery verified:{' '}
          {summary.verification.delivery.verified
            ? summary.verification.delivery.verifiedAt ?? 'Yes'
            : 'No'}
        </Text>
      </DriverCard>

      <SectionHeader label="Proof of delivery" />
      <DriverCard testID="history-summary-pod">
        {summary.proof.exists ? (
          <>
            <Text style={styles.body}>Proof is on file.</Text>
            {summary.proof.confirmedAt ? (
              <Text style={styles.meta}>Confirmed: {summary.proof.confirmedAt}</Text>
            ) : null}
            {summary.proof.types.length > 0 ? (
              <Text style={styles.meta}>Included: {summary.proof.types.join(', ')}</Text>
            ) : null}
          </>
        ) : (
          <Text style={styles.meta}>No proof of delivery on file.</Text>
        )}
      </DriverCard>

      <SectionHeader label="Safety" />
      <DriverCard testID="history-summary-safety">
        <Text style={styles.body}>
          {summary.safety.speedAlertCount > 0
            ? `${summary.safety.speedAlertCount} speed safety alert${
                summary.safety.speedAlertCount === 1 ? '' : 's'
              } on this trip.`
            : 'No speed safety alerts on this trip.'}
        </Text>
        <Text style={styles.meta}>
          Alerts use a configured safety threshold, not a legal road speed limit.
        </Text>
        <Text style={styles.body}>
          {summary.safety.harshBrakingCount > 0
            ? `${summary.safety.harshBrakingCount} sharp-slowdown notice${
                summary.safety.harshBrakingCount === 1 ? '' : 's'
              } on this trip.`
            : 'No sharp-slowdown notices on this trip.'}
        </Text>
        <Text style={styles.body}>
          {summary.safety.suddenStopCount > 0
            ? `${summary.safety.suddenStopCount} rapid-stop notice${
                summary.safety.suddenStopCount === 1 ? '' : 's'
              } on this trip.`
            : 'No rapid-stop notices on this trip.'}
        </Text>
        <Text style={styles.meta}>
          These are operational notices from reported speed, not crash or legal findings.
        </Text>
      </DriverCard>

      <SectionHeader label="Exceptions" />
      <DriverCard testID="history-summary-exceptions">
        {summary.exceptions.length === 0 ? (
          <Text style={styles.meta}>No exceptions recorded.</Text>
        ) : (
          summary.exceptions.map((ex, i) => (
            <View key={`${ex.type}-${i}`} style={styles.exception}>
              <Text style={styles.body}>
                {ex.label}
                {ex.status ? ` · ${ex.status}` : ''}
              </Text>
              {ex.description ? <Text style={styles.meta}>{ex.description}</Text> : null}
              {ex.createdAt ? <Text style={styles.meta}>{ex.createdAt}</Text> : null}
            </View>
          ))
        )}
      </DriverCard>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  errorWrap: {
    flex: 1,
    justifyContent: 'center',
    gap: DriverTheme.spacing.md,
  },
  kicker: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: DriverTheme.spacing.sm,
  },
  title: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    flex: 1,
    minWidth: 0,
  },
  body: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
  },
  arrow: {
    alignItems: 'center',
    paddingVertical: DriverTheme.spacing.xs,
  },
  exception: {
    gap: 2,
    marginTop: DriverTheme.spacing.xs,
  },
});
