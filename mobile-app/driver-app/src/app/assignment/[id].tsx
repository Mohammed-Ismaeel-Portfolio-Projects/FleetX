import { useCallback, useRef, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ArrowDown, MapPin, Navigation, Truck } from 'lucide-react-native';

import {
  BottomActionBar,
  DriverCard,
  DriverHeader,
  DriverScreen,
  InfoRow,
  PrimaryButton,
  SecondaryButton,
  SectionHeader,
  StatusBadge,
  statusToneFromLabel,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import {
  acknowledgeAssignment,
  getAssignment,
  startAssignment,
} from '@/features/driverApi/work';
import {
  DriverWorkApiError,
  isRetryableWorkError,
  type DriverPlace,
  type DriverWorkAssignment,
} from '@/features/driverApi/workTypes';
import { useAuth } from '@/lib/auth/AuthContext';
import { ScreenBlocked, ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';
import { phaseStatusLabel } from '@/features/driverApi/executionPhase';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

function PlaceBlock({
  title,
  place,
  testID,
}: {
  title: string;
  place: DriverPlace;
  testID: string;
}) {
  const cityLine =
    [place.city, place.country].filter((p) => p?.trim()).join(', ') || 'Location unavailable';
  return (
    <View style={styles.place} testID={testID}>
      <Text style={styles.placeTitle}>{title}</Text>
      <Text style={styles.placeValue}>{place.company?.trim() || 'Company unavailable'}</Text>
      {place.address?.trim() ? <Text style={styles.placeMeta}>{place.address}</Text> : null}
      <Text style={styles.placeMeta}>{cityLine}</Text>
    </View>
  );
}

/**
 * Assignment Detail (D2 read + D3 acknowledge / start).
 */
export default function AssignmentDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const assignmentId = String(id || '');
  const router = useRouter();
  const { canOperate, roleOk, identityLinked, logout } = useAuth();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [assignment, setAssignment] = useState<DriverWorkAssignment | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionNotice, setActionNotice] = useState<string | null>(null);
  const [mutating, setMutating] = useState<'ack' | 'start' | null>(null);
  const mutateLock = useRef(false);

  const load = useCallback(async () => {
    if (!canOperate) {
      setStatus('error');
      setRetryable(false);
      setError('Work is unavailable for this account.');
      return;
    }
    if (!assignmentId) {
      setStatus('error');
      setRetryable(false);
      setError('Assignment not found.');
      return;
    }
    setStatus('loading');
    setError(null);
    try {
      const next = await getAssignment(assignmentId);
      setAssignment(next);
      setStatus('ready');
    } catch (e) {
      setAssignment(null);
      setRetryable(isRetryableWorkError(e));
      const msg =
        e instanceof DriverWorkApiError
          ? e.statusCode === 404
            ? 'This assignment is no longer available.'
            : e.message
          : 'Could not load assignment.';
      setError(msg);
      setStatus('error');
    }
  }, [assignmentId, canOperate]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const onAcknowledge = async () => {
    if (mutateLock.current || !assignment) return;
    mutateLock.current = true;
    setMutating('ack');
    setActionError(null);
    setActionNotice(null);
    try {
      const next = await acknowledgeAssignment(assignment.id);
      setAssignment(next);
      setActionNotice('Assignment acknowledged. You are ready to start.');
    } catch (e) {
      setActionError(
        e instanceof DriverWorkApiError ? e.message : 'Could not acknowledge assignment.'
      );
      if (e instanceof DriverWorkApiError && (e.statusCode === 404 || e.statusCode === 409)) {
        await load();
      }
    } finally {
      setMutating(null);
      mutateLock.current = false;
    }
  };

  const onStart = async () => {
    if (mutateLock.current || !assignment) return;
    mutateLock.current = true;
    setMutating('start');
    setActionError(null);
    setActionNotice(null);
    try {
      const next = await startAssignment(assignment.id);
      setAssignment(next);
      router.replace(`/active-trip/${next.id}` as Href);
    } catch (e) {
      setActionError(
        e instanceof DriverWorkApiError ? e.message : 'Could not start assignment.'
      );
      if (e instanceof DriverWorkApiError && (e.statusCode === 404 || e.statusCode === 409)) {
        await load();
      }
    } finally {
      setMutating(null);
      mutateLock.current = false;
    }
  };

  const header = (
    <DriverHeader
      title="Assignment"
      subtitle={
        assignment
          ? assignment.assignmentCode || `Assignment #${assignment.id}`
          : null
      }
      statusLabel={assignment?.status?.name ?? null}
      statusAvailable={Boolean(assignment?.isActive)}
      onBack={() => router.back()}
    />
  );

  if (!roleOk || !identityLinked) {
    return (
      <DriverScreen header={header} padded safeBottom>
        <ScreenBlocked
          testID="assignment-blocked"
          title="Assignment unavailable"
          body="Driver linkage is required to view assignments."
          theme={uxTheme}
          actionLabel="Sign out"
          onAction={() => void logout()}
        />
      </DriverScreen>
    );
  }

  if (status === 'loading') {
    return (
      <DriverScreen header={header} padded safeBottom>
        <ScreenLoading testID="assignment-loading" label="Loading assignment…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (status === 'error' || !assignment) {
    return (
      <DriverScreen header={header} padded safeBottom>
        <View style={styles.stack}>
          <ScreenError
            testID="assignment-error"
            retryTestID="assignment-retry"
            title="Could not load assignment"
            body={error || 'Could not load assignment.'}
            onRetry={retryable ? () => void load() : null}
            theme={uxTheme}
          />
          <SecondaryButton
            testID="assignment-back"
            label="Back to Work"
            accessibilityLabel="Back to Work"
            onPress={() => router.back()}
          />
        </View>
      </DriverScreen>
    );
  }

  const shipment = assignment.shipment;
  const inactive = !assignment.isActive;
  const statusKey = assignment.status?.key;
  const canAck = assignment.isActive && statusKey === 'assigned' && !mutating;
  const canStart = assignment.isActive && statusKey === 'ready' && !mutating;
  const inOperation = assignment.isActive && statusKey === 'in_operation';
  const canViewSummary =
    statusKey === 'completed' || (!assignment.isActive && statusKey === 'completed');
  const showAck = assignment.isActive && statusKey === 'assigned';
  const showStart = assignment.isActive && statusKey === 'ready';
  const hasFooter = showAck || showStart || inOperation || canViewSummary;
  const hasSchedule = Boolean(shipment?.plannedPickupDate || shipment?.plannedDeliveryDate);

  const footer = hasFooter ? (
    <BottomActionBar>
      {actionError ? (
        <Text testID="assignment-action-error" style={styles.error}>
          {actionError}
        </Text>
      ) : null}
      {actionNotice ? (
        <Text testID="assignment-action-notice" style={styles.notice}>
          {actionNotice}
        </Text>
      ) : null}
      {showAck ? (
        <PrimaryButton
          testID="assignment-acknowledge"
          loadingTestID="assignment-acknowledge-loading"
          label="Acknowledge Assignment"
          accessibilityLabel="Acknowledge Assignment"
          loading={mutating === 'ack'}
          disabled={!canAck}
          onPress={() => void onAcknowledge()}
        />
      ) : null}
      {showStart ? (
        <PrimaryButton
          testID="assignment-start"
          loadingTestID="assignment-start-loading"
          label="Start Trip"
          accessibilityLabel="Start Trip"
          loading={mutating === 'start'}
          disabled={!canStart}
          onPress={() => void onStart()}
        />
      ) : null}
      {inOperation ? (
        <PrimaryButton
          testID="assignment-open-trip"
          label="Continue Trip"
          accessibilityLabel="Continue Trip"
          onPress={() => router.push(`/active-trip/${assignment.id}` as Href)}
        />
      ) : null}
      {canViewSummary ? (
        <PrimaryButton
          testID="assignment-view-summary"
          label="View Summary"
          accessibilityLabel="View Summary"
          onPress={() => router.push(`/delivery-complete/${assignment.id}` as Href)}
        />
      ) : null}
    </BottomActionBar>
  ) : null;

  return (
    <DriverScreen
      header={header}
      scroll
      padded
      safeBottom={!hasFooter}
      footer={footer}
      scrollTestID="assignment-detail">
      <View style={styles.stack}>
        {inactive ? (
          <View style={styles.warnBanner} testID="assignment-inactive-banner">
            <Text style={styles.warnTitle}>Not active</Text>
            <Text style={styles.meta}>
              This assignment is no longer active (completed, cancelled, or reassigned).
            </Text>
          </View>
        ) : null}

        <SectionHeader label="Status" />
        <DriverCard testID="assignment-summary">
          <View style={styles.cardTop}>
            <Text style={styles.cardTitle}>
              {assignment.assignmentCode || `Assignment #${assignment.id}`}
            </Text>
            <StatusBadge
              label={assignment.status?.name ?? 'Unavailable'}
              tone={statusToneFromLabel(
                assignment.status?.key ?? assignment.status?.name ?? 'Unavailable'
              )}
            />
          </View>
          <Text testID="assignment-status-label" style={styles.body}>
            Status: {assignment.status?.name ?? 'Unavailable'}
          </Text>
          {assignment.execution?.phase ? (
            <Text testID="assignment-phase" style={styles.meta}>
              Current phase: {phaseStatusLabel(assignment.execution.phase)}
            </Text>
          ) : null}
          {statusKey === 'assigned' ? (
            <Text testID="assignment-ack-hint" style={styles.meta}>
              Review the job, then acknowledge to confirm you have received it.
            </Text>
          ) : null}
          {statusKey === 'ready' ? (
            <Text testID="assignment-ready-hint" style={styles.meta}>
              Ready to start your trip.
            </Text>
          ) : null}
          {assignment.assignedAt ? (
            <Text style={styles.meta}>Assigned: {assignment.assignedAt}</Text>
          ) : null}
        </DriverCard>

        <SectionHeader label="Assignment details" />
        <DriverCard testID="assignment-shipment">
          <Text style={styles.sectionLabel}>Shipment</Text>
          <Text style={styles.body}>{shipment?.shipmentNumber ?? 'Shipment number unavailable'}</Text>
          {shipment?.referenceNumber ? (
            <Text style={styles.meta}>Ref: {shipment.referenceNumber}</Text>
          ) : null}
          <Text style={styles.meta}>
            Shipment status: {shipment?.status?.name ?? 'Unavailable'}
          </Text>
        </DriverCard>

        <DriverCard testID="assignment-vehicle">
          <Text style={styles.sectionLabel}>Vehicle</Text>
          {assignment.vehicle ? (
            <>
              {assignment.vehicle.plateNumber ? (
                <InfoRow
                  label="Plate"
                  value={assignment.vehicle.plateNumber}
                  icon={<Truck size={18} color={DriverTheme.colors.primary} />}
                />
              ) : (
                <Text style={styles.body}>Plate: Unavailable</Text>
              )}
              {assignment.vehicle.fleetNumber ? (
                <InfoRow label="Fleet #" value={assignment.vehicle.fleetNumber} />
              ) : (
                <Text style={styles.meta}>Fleet #: Unavailable</Text>
              )}
            </>
          ) : (
            <Text style={styles.body}>No vehicle on this assignment</Text>
          )}
        </DriverCard>

        <DriverCard testID="assignment-trailer">
          <Text style={styles.sectionLabel}>Trailer</Text>
          {assignment.trailer ? (
            <Text style={styles.body}>
              Code: {assignment.trailer.trailerCode ?? 'Unavailable'}
            </Text>
          ) : (
            <Text style={styles.body}>No trailer on this assignment</Text>
          )}
        </DriverCard>

        {shipment ? (
          <>
            <SectionHeader label="Route" />
            <DriverCard>
              <PlaceBlock title="Pickup" place={shipment.pickup} testID="assignment-pickup" />
              <View style={styles.routeConnector} accessibilityElementsHidden>
                <ArrowDown size={16} color={DriverTheme.colors.textMuted} />
              </View>
              <PlaceBlock title="Delivery" place={shipment.delivery} testID="assignment-delivery" />
            </DriverCard>
          </>
        ) : null}

        {hasSchedule ? (
          <>
            <SectionHeader label="Schedule" />
            <DriverCard testID="assignment-schedule">
              {shipment?.plannedPickupDate ? (
                <InfoRow
                  label="Planned pickup"
                  value={shipment.plannedPickupDate}
                  icon={<MapPin size={18} color={DriverTheme.colors.primary} />}
                />
              ) : null}
              {shipment?.plannedDeliveryDate ? (
                <InfoRow
                  label="Planned delivery"
                  value={shipment.plannedDeliveryDate}
                  icon={<Navigation size={18} color={DriverTheme.colors.navyElevated} />}
                />
              ) : null}
            </DriverCard>
          </>
        ) : (
          <DriverCard testID="assignment-schedule">
            <Text style={styles.sectionLabel}>Schedule</Text>
            <Text style={styles.body}>Planned pickup: Unavailable</Text>
            <Text style={styles.body}>Planned delivery: Unavailable</Text>
          </DriverCard>
        )}

        {!hasFooter && actionError ? (
          <Text testID="assignment-action-error" style={styles.error}>
            {actionError}
          </Text>
        ) : null}
        {!hasFooter && actionNotice ? (
          <Text testID="assignment-action-notice" style={styles.notice}>
            {actionNotice}
          </Text>
        ) : null}
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  stack: {
    gap: 16,
    paddingBottom: 24,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 4,
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#FFFFFF',
    flex: 1,
  },
  sectionLabel: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
    color: '#94A3B8',
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  body: {
    fontSize: 15,
    color: '#F8FAFC',
    lineHeight: 22,
  },
  meta: {
    fontSize: 13,
    color: '#94A3B8',
    marginTop: 2,
  },
  place: {
    gap: 4,
  },
  placeTitle: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
    color: '#94A3B8',
    textTransform: 'uppercase',
  },
  placeValue: {
    fontSize: 15,
    fontWeight: '600',
    color: '#F8FAFC',
    marginTop: 2,
  },
  placeMeta: {
    fontSize: 13,
    color: '#94A3B8',
  },
  routeConnector: {
    alignItems: 'center',
    paddingVertical: 10,
  },
  warnBanner: {
    gap: 6,
    padding: 16,
    borderRadius: 16,
    backgroundColor: 'rgba(239, 68, 68, 0.15)',
    borderWidth: 1,
    borderColor: 'rgba(239, 68, 68, 0.3)',
  },
  warnTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#F87171',
  },
  notice: {
    fontSize: 13,
    color: '#60A5FA',
  },
  error: {
    fontSize: 13,
    color: '#F87171',
  },
});
