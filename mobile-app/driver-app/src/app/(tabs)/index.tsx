import { useCallback, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter, type Href } from 'expo-router';
import { ArrowDown, Bell, ClipboardCheck, MapPin, Navigation } from 'lucide-react-native';

import { OfflineBanner } from '@/components/OfflineBanner';
import {
  DriverCard,
  DriverHeader,
  DriverScreen,
  EmptyState,
  InfoRow,
  PrimaryButton,
  SecondaryButton,
  SectionHeader,
  StatusBadge,
  statusToneFromLabel,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { getActiveAssignment } from '@/features/driverApi/work';
import { getUnreadNotificationCount } from '@/features/driverApi/notifications';
import {
  getDriverAvailability,
  type DriverAvailability,
} from '@/features/driverApi/availability';
import {
  nextWorkActionHint,
  workHomePrimaryCta,
  workOperationalLabel,
} from '@/lib/driver/assignmentState';
import { phaseStatusLabel } from '@/features/driverApi/executionPhase';
import { useNetworkStatus } from '@/lib/network/useNetworkStatus';
import { formatPlaceLine } from '@/features/driverApi/workMapper';
import {
  DriverWorkApiError,
  isRetryableWorkError,
  type DriverWorkAssignment,
} from '@/features/driverApi/workTypes';
import { useAuth } from '@/lib/auth/AuthContext';
import { ScreenBlocked, ScreenEmpty, ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

function vehicleDisplay(assignment: DriverWorkAssignment): string | null {
  const plate = assignment.vehicle?.plateNumber?.trim() || null;
  const fleet = assignment.vehicle?.fleetNumber?.trim() || null;
  const vehicleBits = [plate, fleet && fleet !== plate ? fleet : null].filter(Boolean);
  const trailerCode =
    assignment.trailer?.trailerCode || assignment.trailer?.plateNumber || assignment.trailer?.fleetNumber;
  if (vehicleBits.length === 0 && !trailerCode) return null;
  const line = vehicleBits.length ? vehicleBits.join(' · ') : null;
  if (trailerCode) {
    return line ? `${line} · Trailer ${trailerCode}` : `Trailer ${trailerCode}`;
  }
  return line;
}

/**
 * Work Home (D2) — real active assignment visibility.
 * No execution actions (D3+). Presentation-only modernization (Phase 1).
 */
export default function WorkScreen() {
  const { canOperate, identityLinked, roleOk, logout } = useAuth();
  const router = useRouter();

  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle');
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [assignment, setAssignment] = useState<DriverWorkAssignment | null>(null);
  const { online } = useNetworkStatus();
  const [unreadAlerts, setUnreadAlerts] = useState(0);
  const [availability, setAvailability] = useState<DriverAvailability | null>(null);

  const load = useCallback(async () => {
    if (!canOperate) {
      setAssignment(null);
      setAvailability(null);
      setStatus('ready');
      setError(null);
      return;
    }
    setStatus('loading');
    setError(null);
    try {
      const [next, unread, nextAvailability] = await Promise.all([
        getActiveAssignment(),
        getUnreadNotificationCount().catch(() => 0),
        getDriverAvailability().catch(() => null),
      ]);
      setAssignment(next);
      setUnreadAlerts(unread);
      setAvailability(nextAvailability);
      setStatus('ready');
    } catch (e) {
      setAssignment(null);
      setRetryable(isRetryableWorkError(e));
      setError(e instanceof DriverWorkApiError ? e.message : 'Could not load work.');
      setStatus('error');
    }
  }, [canOperate]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const operationalLabel = workOperationalLabel(assignment);
  const statusOnDuty = availability?.availability !== 'unavailable';

  const header = (
    <DriverHeader
      title="Work"
      titleTestID="work-title"
      statusLabel={operationalLabel}
      statusAvailable={statusOnDuty}
      statusTestID="work-operational-state"
      subtitle={nextWorkActionHint(assignment)}
      subtitleTestID="work-next-hint"
    />
  );

  if (!roleOk) {
    return (
      <DriverScreen header={header} padded>
        <ScreenBlocked
          testID="work-blocked-role"
          title="Wrong account type"
          body="This app is for FleetX drivers only."
          theme={uxTheme}
          actionLabel="Sign out"
          actionTestID="work-blocked-logout"
          onAction={() => void logout()}
        />
      </DriverScreen>
    );
  }

  if (!identityLinked) {
    return (
      <DriverScreen header={header} padded>
        <ScreenBlocked
          testID="work-blocked-link"
          title="Driver account not linked"
          body="Your user is not linked to a Driver employee record. Contact your dispatcher. Work stays unavailable."
          theme={uxTheme}
          actionLabel="Sign out"
          actionTestID="work-blocked-logout"
          onAction={() => void logout()}
        />
      </DriverScreen>
    );
  }

  const shipment = assignment?.shipment;
  const showCard = status === 'ready' && assignment != null && assignment.isActive;
  const vehicleLine = assignment ? vehicleDisplay(assignment) : null;
  const unavailable = availability?.availability === 'unavailable';

  return (
    <DriverScreen header={header} scroll padded>
      {availability ? (
        <Text testID="work-availability" style={styles.availabilityMeta}>
          Availability: {availability.availability === 'available' ? 'Available' : 'Unavailable'}
        </Text>
      ) : null}

      {!online ? <OfflineBanner testID="work-offline" /> : null}

      {unreadAlerts > 0 ? (
        <Pressable
          testID="work-unread-alerts"
          onPress={() => router.push('/(tabs)/notifications' as Href)}
          style={styles.alertChip}
          accessibilityRole="button"
          accessibilityLabel={`${unreadAlerts} operational alerts`}>
          <Bell size={16} color={DriverTheme.colors.primary} />
          <Text style={styles.alertText}>
            {unreadAlerts} operational alert{unreadAlerts === 1 ? '' : 's'}
          </Text>
        </Pressable>
      ) : null}

      {status === 'loading' || status === 'idle' ? (
        <ScreenLoading testID="work-loading" label="Loading work…" theme={uxTheme} />
      ) : null}

      {status === 'error' ? (
        <ScreenError
          testID="work-error"
          retryTestID="work-retry"
          title="Could not load work"
          body={error || 'Could not load work.'}
          onRetry={retryable ? () => void load() : null}
          theme={uxTheme}
        />
      ) : null}

      {status === 'ready' && !showCard ? (
        <DriverCard>
          <EmptyState
            icon={
              <ClipboardCheck
                size={36}
                color={DriverTheme.colors.primary}
                accessibilityLabel="Ready for work"
              />
            }>
            <ScreenEmpty
              testID="work-empty"
              title={unavailable ? 'No active assignment' : "You're ready for your next trip"}
              body={
                unavailable
                  ? "You're marked unavailable. You won't be considered for new work. Change this in Account."
                  : 'No active assignment right now. Your next assignment will appear here when your dispatcher sends it.'
              }
              theme={uxTheme}
            />
            <StatusBadge
              label={unavailable ? 'Unavailable' : 'Available'}
              tone={unavailable ? 'danger' : 'available'}
            />
          </EmptyState>
        </DriverCard>
      ) : null}

      {showCard && assignment ? (
        <View style={styles.assignmentBlock}>
          <SectionHeader label="Active assignment" />
          <DriverCard testID="work-active-card">
            <View style={styles.cardTop}>
              <Text style={styles.assignmentCode} testID="work-assignment-code">
                {assignment.assignmentCode || `Assignment #${assignment.id}`}
              </Text>
              <StatusBadge
                label={assignment.status?.name ?? 'Unavailable'}
                tone={statusToneFromLabel(
                  assignment.status?.key ?? assignment.status?.name ?? 'Unavailable'
                )}
              />
            </View>
            <Text style={styles.statusDetail} testID="work-assignment-status">
              {assignment.status?.name ?? 'Unavailable'}
              {assignment.execution?.phase
                ? ` · ${phaseStatusLabel(assignment.execution.phase)}`
                : ''}
            </Text>
            <Text style={styles.shipment} testID="work-shipment-number">
              Shipment {shipment?.shipmentNumber ?? 'unavailable'}
            </Text>
            {shipment?.pickup?.company ? (
              <Text style={styles.meta} testID="work-customer">
                Site: {shipment.pickup.company}
              </Text>
            ) : null}

            {vehicleLine ? (
              <InfoRow label="Vehicle" value={vehicleLine} testID="work-vehicle" />
            ) : null}

            <View style={styles.route}>
              <InfoRow
                label="Pickup"
                value={shipment ? formatPlaceLine(shipment.pickup) : 'Unavailable'}
                testID="work-pickup"
                icon={<MapPin size={18} color={DriverTheme.colors.primary} />}
              />
              <View style={styles.routeConnector} accessibilityElementsHidden>
                <ArrowDown size={16} color={DriverTheme.colors.textMuted} />
              </View>
              <InfoRow
                label="Delivery"
                value={shipment ? formatPlaceLine(shipment.delivery) : 'Unavailable'}
                testID="work-delivery"
                icon={<Navigation size={18} color={DriverTheme.colors.navyElevated} />}
              />
            </View>

            {shipment?.plannedPickupDate || shipment?.plannedDeliveryDate ? (
              <Text style={styles.meta} testID="work-schedule">
                Pickup {shipment?.plannedPickupDate ?? '—'} · Delivery{' '}
                {shipment?.plannedDeliveryDate ?? '—'}
              </Text>
            ) : null}

            {(() => {
              const cta = workHomePrimaryCta(assignment);
              const goTrip = cta.kind === 'trip';
              return (
                <PrimaryButton
                  testID={goTrip ? 'work-open-trip' : 'work-view-assignment'}
                  label={cta.label}
                  accessibilityLabel={cta.label}
                  onPress={() => {
                    router.push(
                      (goTrip
                        ? `/active-trip/${assignment.id}`
                        : `/assignment/${assignment.id}`) as Href
                    );
                  }}
                />
              );
            })()}
            {assignment.status?.key === 'in_operation' ? (
              <SecondaryButton
                testID="work-view-assignment-details"
                label="View Assignment"
                accessibilityLabel="View Assignment"
                onPress={() => router.push(`/assignment/${assignment.id}` as Href)}
              />
            ) : null}
          </DriverCard>
        </View>
      ) : null}

      <View style={styles.actions}>
        {canOperate ? (
          <SecondaryButton
            testID="work-assignment-history"
            label="Assignment History"
            accessibilityLabel="Assignment History"
            onPress={() => router.push('/history' as Href)}
          />
        ) : null}

        <SecondaryButton
          testID="work-refresh"
          label="Refresh"
          accessibilityLabel="Refresh work"
          onPress={() => void load()}
        />
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  availabilityMeta: {
    fontSize: 12,
    color: '#94A3B8',
    marginBottom: 8,
  },
  alertChip: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 12,
    backgroundColor: 'rgba(30, 117, 255, 0.15)',
    borderWidth: 1,
    borderColor: 'rgba(30, 117, 255, 0.3)',
    marginBottom: 16,
    minHeight: 48,
  },
  alertText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#60A5FA',
  },
  assignmentBlock: {
    gap: 12,
    marginBottom: 16,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 4,
  },
  assignmentCode: {
    fontSize: 18,
    fontWeight: '700',
    color: '#FFFFFF',
    flex: 1,
  },
  statusDetail: {
    fontSize: 13,
    color: '#94A3B8',
    marginBottom: 2,
  },
  shipment: {
    fontSize: 15,
    fontWeight: '600',
    color: '#F8FAFC',
    marginTop: 4,
  },
  meta: {
    fontSize: 13,
    color: '#94A3B8',
    marginTop: 2,
  },
  route: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255, 255, 255, 0.08)',
    gap: 4,
  },
  routeConnector: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  actions: {
    marginTop: 24,
    gap: 12,
  },
});
