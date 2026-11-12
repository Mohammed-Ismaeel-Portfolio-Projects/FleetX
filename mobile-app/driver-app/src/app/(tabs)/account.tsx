import { useCallback, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter, type Href } from 'expo-router';

import { OfflineBanner } from '@/components/OfflineBanner';
import {
  DangerButton,
  DriverCard,
  DriverHeader,
  DriverScreen,
  PrimaryButton,
  SecondaryButton,
  SectionHeader,
  StatusBadge,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { buildAccountView } from '@/features/driverApi/account';
import {
  availabilityExplanation,
  getDriverAvailability,
  mapAvailabilityReason,
  updateDriverAvailability,
  type DriverAvailability,
  type DriverAvailabilityKey,
} from '@/features/driverApi/availability';
import {
  getOwnPerformance,
  listOwnEvaluations,
  type DriverSelfEvaluationItem,
  type DriverSelfPerformance,
} from '@/features/driverApi/performance';
import { formatScoreLabel, formatTrend } from '@/features/driverApi/performanceDisplay';
import { DriverWorkApiError } from '@/features/driverApi/workTypes';
import { useAuth } from '@/lib/auth/AuthContext';
import { AppConfig } from '@/lib/config';
import { useNetworkStatus } from '@/lib/network/useNetworkStatus';
import {
  enableDriverPush,
  getDriverPushStatus,
  type DriverPushStatus,
} from '@/lib/push/pushRegistration';

const OFFLINE_AVAILABILITY_COPY =
  "You're offline. Availability can't be changed until the connection returns.";

function initialsFromName(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length >= 2) {
    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
  }
  return (parts[0]?.slice(0, 1) || 'D').toUpperCase();
}

/**
 * Account — identity/session from existing me/identity only (D1).
 * Availability mutation is server-authoritative (D13).
 */
export default function AccountScreen() {
  const { user, identity, roleOk, identityLinked, canOperate, logout } = useAuth();
  const account = buildAccountView(user, identity);
  const router = useRouter();
  const { online } = useNetworkStatus();
  const [pushStatus, setPushStatus] = useState<DriverPushStatus | null>(null);
  const [pushBusy, setPushBusy] = useState(false);
  const [availability, setAvailability] = useState<DriverAvailability | null>(null);
  const [availabilityBusy, setAvailabilityBusy] = useState(false);
  const [availabilityError, setAvailabilityError] = useState<string | null>(null);
  const [availabilitySuccess, setAvailabilitySuccess] = useState<string | null>(null);
  const [performance, setPerformance] = useState<DriverSelfPerformance | null>(null);
  const [evaluations, setEvaluations] = useState<DriverSelfEvaluationItem[]>([]);
  const [performanceError, setPerformanceError] = useState<string | null>(null);

  const loadAvailability = useCallback(async () => {
    if (!canOperate) {
      setAvailability(null);
      return;
    }
    try {
      const next = await getDriverAvailability();
      setAvailability(next);
      setAvailabilityError(null);
    } catch (e) {
      setAvailabilityError(
        e instanceof DriverWorkApiError ? e.message : 'Could not load availability.'
      );
    }
  }, [canOperate]);

  const loadPerformance = useCallback(async () => {
    if (!canOperate) {
      setPerformance(null);
      setEvaluations([]);
      return;
    }
    try {
      const [summary, history] = await Promise.all([getOwnPerformance(), listOwnEvaluations(1)]);
      setPerformance(summary);
      setEvaluations(history);
      setPerformanceError(null);
    } catch (e) {
      setPerformanceError(
        e instanceof DriverWorkApiError ? e.message : 'Could not load performance.'
      );
    }
  }, [canOperate]);

  useFocusEffect(
    useCallback(() => {
      if (!canOperate) {
        setPushStatus(null);
        setAvailability(null);
        setPerformance(null);
        setEvaluations([]);
        return;
      }
      void getDriverPushStatus().then(setPushStatus);
      void loadAvailability();
      void loadPerformance();
    }, [canOperate, loadAvailability, loadPerformance])
  );

  const onEnableAlerts = async () => {
    setPushBusy(true);
    try {
      const next = await enableDriverPush();
      setPushStatus(next);
    } finally {
      setPushBusy(false);
    }
  };

  const onSetAvailability = async (next: DriverAvailabilityKey) => {
    if (!online) {
      setAvailabilityError(OFFLINE_AVAILABILITY_COPY);
      setAvailabilitySuccess(null);
      return;
    }
    setAvailabilityBusy(true);
    setAvailabilityError(null);
    setAvailabilitySuccess(null);
    try {
      const current = await getDriverAvailability();
      setAvailability(current);
      if (!current.canChange && next !== current.availability) {
        setAvailabilityError(mapAvailabilityReason(current.reason));
        return;
      }
      const updated = await updateDriverAvailability(next);
      setAvailability(updated);
      setAvailabilitySuccess(
        updated.availability === 'available'
          ? "You're now available."
          : "You're now unavailable."
      );
    } catch (e) {
      const code = e instanceof DriverWorkApiError ? e.code : null;
      setAvailabilityError(
        mapAvailabilityReason(
          code ?? null,
          e instanceof DriverWorkApiError ? e.message : 'Could not update availability.'
        )
      );
      await loadAvailability();
    } finally {
      setAvailabilityBusy(false);
    }
  };

  const controlDisabled =
    !online ||
    availabilityBusy ||
    availability == null ||
    availability.canChange === false;

  const isAvailable = availability?.availability === 'available';
  const sessionBlocked = !roleOk || !identityLinked;

  return (
    <DriverScreen
      header={<DriverHeader title="Account" titleTestID="account-title" />}
      scroll
      padded>
      <Text style={styles.version}>FleetX Driver · v{AppConfig.appVersion}</Text>

      {!online ? <OfflineBanner testID="account-offline" /> : null}

      <SectionHeader label="Account" />
      <DriverCard testID="account-profile">
        <View style={styles.identityRow}>
          <View style={styles.avatar} accessibilityElementsHidden importantForAccessibility="no">
            <Text style={styles.avatarText}>{initialsFromName(account.displayName)}</Text>
          </View>
          <View style={styles.identityTexts}>
            <Text testID="account-name" style={styles.name} accessibilityRole="header">
              {account.displayName}
            </Text>
            <Text testID="account-email" style={styles.email}>
              {account.email}
            </Text>
            <StatusBadge
              label={roleOk ? 'Driver' : 'Not a driver account'}
              tone={roleOk ? 'assigned' : 'danger'}
            />
          </View>
        </View>
        {account.employeeNumber ? (
          <Text testID="account-employee" style={styles.meta}>
            Employee #: {account.employeeNumber}
          </Text>
        ) : (
          <Text style={styles.muted}>Employee number unavailable</Text>
        )}
        {account.companyName ? (
          <Text testID="account-company" style={styles.meta}>
            {account.companyName}
            {account.companyCode ? ` (${account.companyCode})` : ''}
          </Text>
        ) : (
          <Text style={styles.muted}>Company unavailable</Text>
        )}
      </DriverCard>

      {canOperate ? (
        <>
          <SectionHeader label="Overall Driver Performance" />
          <DriverCard testID="account-performance">
            {performanceError ? (
              <Text style={styles.errorCopy}>{performanceError}</Text>
            ) : !performance || !performance.scoreAvailable || performance.overallScore == null ? (
              <Text testID="account-performance-empty" style={styles.muted}>
                Not enough evaluation data
              </Text>
            ) : (
              <>
                <Text testID="account-performance-score" style={styles.name}>
                  Score {performance.overallScore}
                  {performance.scoreLabel ? ` · ${formatScoreLabel(performance.scoreLabel)}` : ''}
                </Text>
                <Text style={styles.meta}>
                  Confidence: {performance.confidence ?? '—'} · Maturity: {performance.maturity ?? '—'}
                </Text>
                <Text style={styles.meta}>
                  Trend: {formatTrend(performance.trend, performance.trendDelta)}
                </Text>
                <Text style={styles.meta}>
                  Evaluated assignments: {performance.evaluatedAssignmentsCount}
                </Text>
                {performance.lastEvaluatedAt ? (
                  <Text style={styles.meta}>Last evaluated: {performance.lastEvaluatedAt}</Text>
                ) : null}
                {performance.categoryScores
                  ? Object.entries(performance.categoryScores).map(([key, value]) =>
                      value.score == null ? null : (
                        <Text key={key} style={styles.meta}>
                          {key.replace(/_/g, ' ')}: {value.score}
                        </Text>
                      )
                    )
                  : null}
              </>
            )}
            {evaluations.length > 0 ? (
              <View style={{ marginTop: 10 }}>
                <Text style={styles.help}>Recent Trip Evaluations</Text>
                {evaluations.slice(0, 5).map((item) => (
                  <Pressable
                    key={item.evaluationId}
                    testID={`account-evaluation-${item.evaluationId}`}
                    onPress={() =>
                      router.push(`/evaluation/${item.evaluationId}` as Href)
                    }
                    accessibilityRole="button"
                    accessibilityLabel="Open trip evaluation">
                    <Text style={styles.metaLink}>
                      {item.assignmentCode || `#${item.assignmentId}`}:{' '}
                      {item.overallScore != null
                        ? `${item.overallScore}${item.scoreLabel ? ` · ${formatScoreLabel(item.scoreLabel)}` : ''}`
                        : item.status === 'incomplete'
                          ? 'Insufficient trip data'
                          : 'Score unavailable'}
                      {item.finalizedAt ? ` · ${item.finalizedAt}` : ''}
                    </Text>
                  </Pressable>
                ))}
              </View>
            ) : null}
          </DriverCard>
        </>
      ) : null}

      {canOperate ? (
        <>
          <SectionHeader label="Operations" />
          <DriverCard testID="account-availability">
            <View style={styles.availabilityHead}>
              <View
                style={[
                  styles.dot,
                  {
                    backgroundColor: availability
                      ? isAvailable
                        ? DriverTheme.colors.success
                        : DriverTheme.colors.textMuted
                      : DriverTheme.colors.textMuted,
                  },
                ]}
                accessibilityElementsHidden
                importantForAccessibility="no"
              />
              <Text testID="account-availability-value" style={styles.availabilityValue}>
                {availability
                  ? availability.availability === 'available'
                    ? 'Available'
                    : 'Unavailable'
                  : 'Loading…'}
              </Text>
              {availability ? (
                <StatusBadge
                  label={isAvailable ? 'Available' : 'Unavailable'}
                  tone={isAvailable ? 'available' : 'danger'}
                />
              ) : null}
            </View>
            {availability ? (
              <Text style={styles.help} testID="account-availability-help">
                {availabilityExplanation(availability.availability)}
              </Text>
            ) : null}
            {!online ? (
              <Text testID="account-availability-offline" style={styles.offlineCopy}>
                {OFFLINE_AVAILABILITY_COPY}
              </Text>
            ) : null}
            {online && availability && !availability.canChange ? (
              <Text testID="account-availability-blocked" style={styles.blockedCopy}>
                {mapAvailabilityReason(availability.reason)}
              </Text>
            ) : null}
            {availabilityError ? (
              <Text testID="account-availability-error" style={styles.errorCopy}>
                {availabilityError}
              </Text>
            ) : null}
            {availabilitySuccess ? (
              <Text testID="account-availability-success" style={styles.successCopy}>
                {availabilitySuccess}
              </Text>
            ) : null}
            <PrimaryButton
              testID="account-set-available"
              label={availabilityBusy ? 'Working…' : 'Set Available'}
              onPress={() => void onSetAvailability('available')}
              disabled={controlDisabled || availability?.availability === 'available'}
              accessibilityLabel="Set available"
            />
            <SecondaryButton
              testID="account-set-unavailable"
              label="Set Unavailable"
              onPress={() => void onSetAvailability('unavailable')}
              disabled={controlDisabled || availability?.availability === 'unavailable'}
              accessibilityLabel="Set unavailable"
            />
          </DriverCard>

          <DriverCard testID="account-offline-recovery">
            <Text style={styles.cardTitle}>Offline Data Sync & Recovery</Text>
            <Text style={styles.help}>
              Inspect queued offline actions, status updates, and retry failed operations.
            </Text>
            <SecondaryButton
              testID="account-open-offline-recovery"
              label="Inspect Offline Queue & Recovery"
              onPress={() => router.push('/offline-recovery' as Href)}
              accessibilityLabel="Inspect offline queue and recovery"
            />
          </DriverCard>
        </>
      ) : null}

      <SectionHeader label="Session" />
      <DriverCard testID="account-session" style={sessionBlocked ? styles.warnCard : undefined}>
        <Text style={styles.sessionLine}>Role: {roleOk ? 'Driver' : 'Not a driver account'}</Text>
        <Text style={styles.sessionLine}>Linkage: {identityLinked ? 'Linked' : 'Not linked'}</Text>
        <Text style={styles.sessionLine}>
          Operational access: {canOperate ? 'Ready' : 'Blocked'}
        </Text>
        {!identityLinked && account.unlinkReason ? (
          <Text style={styles.muted}>{account.unlinkReason}</Text>
        ) : null}
      </DriverCard>

      {canOperate ? (
        <>
          <SectionHeader label="Notifications" />
          <DriverCard testID="account-trip-alerts">
            <Text style={styles.cardTitle}>Trip alerts</Text>
            <Text style={styles.help}>
              {pushStatus?.message ??
                'Optional device notifications. Assignment status always comes from the app, not the alert.'}
            </Text>
            {pushStatus?.permission === 'granted' && pushStatus.registered ? null : (
              <PrimaryButton
                testID="account-enable-trip-alerts"
                label={pushBusy ? 'Working…' : 'Enable trip alerts'}
                onPress={() => void onEnableAlerts()}
                disabled={pushBusy || pushStatus?.permission === 'unavailable'}
                accessibilityLabel="Enable trip alerts"
              />
            )}
          </DriverCard>
        </>
      ) : null}

      <View style={styles.logoutWrap}>
        <DangerButton
          testID="account-logout"
          label="Sign out"
          onPress={() => void logout()}
          accessibilityLabel="Sign out"
        />
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  version: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
    marginBottom: DriverTheme.spacing.sm,
  },
  identityRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: DriverTheme.spacing.md,
    marginBottom: DriverTheme.spacing.sm,
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: DriverTheme.colors.infoSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.primary,
  },
  identityTexts: {
    flex: 1,
    minWidth: 0,
    gap: DriverTheme.spacing.xs,
  },
  name: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
  },
  email: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
  },
  meta: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  metaLink: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.primary,
    marginTop: 4,
    textTransform: 'capitalize',
  },
  muted: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textMuted,
  },
  availabilityHead: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: DriverTheme.spacing.sm,
  },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  availabilityValue: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
    flexGrow: 1,
    flexShrink: 1,
  },
  help: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textSecondary,
  },
  offlineCopy: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.warning,
  },
  blockedCopy: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.warning,
  },
  errorCopy: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.danger,
  },
  successCopy: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.success,
  },
  warnCard: {
    backgroundColor: DriverTheme.colors.dangerSoft,
    borderColor: DriverTheme.colors.dangerSoft,
  },
  sessionLine: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  cardTitle: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
  },
  logoutWrap: {
    marginTop: DriverTheme.spacing.md,
    marginBottom: DriverTheme.spacing.lg,
  },
});
