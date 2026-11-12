import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Linking, Platform, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useCameraPermissions } from 'expo-camera';

import {
  BottomActionBar,
  DriverCard,
  DriverHeader,
  DriverScreen,
  PrimaryButton,
  SecondaryButton,
  StatusBadge,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { getAssignment, verifyAssignment } from '@/features/driverApi/work';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { verifyPhaseAllowed } from '@/lib/driver/assignmentState';
import { ExpoCameraQrScanner } from '@/features/verification/ExpoCameraQrScanner';
import { parseVerificationChallenge } from '@/features/verification/parseChallenge';
import { useAuth } from '@/lib/auth/AuthContext';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

type EventKind = 'pickup' | 'delivery';

const SCAN_DEBOUNCE_MS = 2500;

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

function mapVerifyError(error: DriverWorkApiError, event: EventKind): string {
  const raw = (error.message || '').toLowerCase();
  if (error.statusCode === 404) {
    return 'This QR code does not belong to this assignment.';
  }
  if (error.statusCode === 422) {
    return 'This QR is not a valid verification code. Ask for the shipment QR.';
  }
  if (raw.includes('expir')) return 'This QR has expired. Ask for a new shipment QR.';
  if (raw.includes('revok')) return 'This QR is no longer valid. Ask for a new shipment QR.';
  if (raw.includes('already') || raw.includes('consumed')) {
    return event === 'pickup'
      ? 'Pickup has already been verified.'
      : 'Delivery has already been verified.';
  }
  if (raw.includes('event') || raw.includes('phase') || raw.includes('match')) {
    return 'This QR code does not belong to this assignment.';
  }
  if (raw.includes('network') || error.statusCode === 0 || (error.statusCode ?? 0) >= 500) {
    return event === 'pickup'
      ? 'Pickup verification could not be completed. Try again.'
      : 'Delivery verification could not be completed. Try again.';
  }
  return error.message || 'Verification failed. Try again.';
}

function ScanFrame() {
  return (
    <View pointerEvents="none" style={styles.frameOverlay} accessibilityElementsHidden>
      <View style={styles.frameBox}>
        <View style={[styles.corner, styles.cornerTL]} />
        <View style={[styles.corner, styles.cornerTR]} />
        <View style={[styles.corner, styles.cornerBL]} />
        <View style={[styles.corner, styles.cornerBR]} />
      </View>
    </View>
  );
}

/**
 * D5 QR verification + D9 phase gate and scan debounce.
 */
export default function VerifyTripScreen() {
  const { id, event: eventParam } = useLocalSearchParams<{ id: string; event?: string }>();
  const assignmentId = String(id || '');
  const event: EventKind = eventParam === 'delivery' ? 'delivery' : 'pickup';
  const router = useRouter();
  const { canOperate } = useAuth();
  const [permission, requestPermission] = useCameraPermissions();

  const [gateStatus, setGateStatus] = useState<'loading' | 'ready' | 'blocked' | 'error'>(
    'loading'
  );
  const [gateError, setGateError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [stationaryOk, setStationaryOk] = useState(false);
  const [scanning, setScanning] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const submitLock = useRef(false);
  const lastScanAt = useRef(0);

  const title = event === 'pickup' ? 'Verify Pickup' : 'Verify Delivery';
  const goActiveTrip = () => router.replace(`/active-trip/${assignmentId}` as Href);

  const loadGate = useCallback(async () => {
    if (!canOperate || !assignmentId) {
      setGateStatus('blocked');
      setGateError('Verification is unavailable for this account.');
      return;
    }
    setGateStatus('loading');
    setGateError(null);
    try {
      const assignment = await getAssignment(assignmentId);
      if (!assignment.isActive || assignment.status?.key !== 'in_operation') {
        setGateStatus('blocked');
        setGateError('This trip is no longer in operation.');
        return;
      }
      const phase = assignment.execution?.phase ?? null;
      if (!verifyPhaseAllowed(phase, event)) {
        setGateStatus('blocked');
        setGateError(
          `Verification is not available in the current phase (${phase ?? 'unknown'}). Return to Active Trip.`
        );
        return;
      }
      setGateStatus('ready');
    } catch (e) {
      setRetryable(isRetryableWorkError(e));
      setGateError(
        e instanceof DriverWorkApiError ? e.message : 'Could not load verification context.'
      );
      setGateStatus('error');
    }
  }, [assignmentId, canOperate, event]);

  useEffect(() => {
    const initial = setTimeout(() => void loadGate(), 0);
    return () => clearTimeout(initial);
  }, [loadGate]);

  const onScanned = useCallback(
    async (result: { data: string }) => {
      const now = Date.now();
      if (
        !stationaryOk ||
        !scanning ||
        submitLock.current ||
        submitting ||
        now - lastScanAt.current < SCAN_DEBOUNCE_MS
      ) {
        return;
      }
      lastScanAt.current = now;

      const challenge = parseVerificationChallenge(result.data);
      if (!challenge) {
        setError('Unrecognized QR. Use the FleetX verification code for this shipment.');
        return;
      }

      submitLock.current = true;
      setScanning(false);
      setSubmitting(true);
      setError(null);
      try {
        if (!canOperate) {
          throw new DriverWorkApiError('Driver account cannot operate.', 403);
        }
        await verifyAssignment(assignmentId, event, challenge);
        setSuccess(event === 'pickup' ? 'Pickup verified successfully' : 'Delivery verified successfully');
      } catch (e) {
        setError(
          e instanceof DriverWorkApiError
            ? mapVerifyError(e, event)
            : event === 'pickup'
              ? 'Pickup verification could not be completed. Try again.'
              : 'Delivery verification could not be completed. Try again.'
        );
        setScanning(true);
      } finally {
        setSubmitting(false);
        submitLock.current = false;
      }
    },
    [assignmentId, canOperate, event, scanning, stationaryOk, submitting]
  );

  const header = (
    <DriverHeader
      title={title}
      titleTestID="verify-title"
      statusLabel={event === 'pickup' ? 'Pickup' : 'Delivery'}
      statusAvailable
      onBack={() => router.back()}
    />
  );

  if (gateStatus === 'loading') {
    return (
      <DriverScreen header={header} padded safeBottom>
        <ScreenLoading
          testID="verify-loading"
          label="Loading verification…"
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  if (gateStatus === 'error' || gateStatus === 'blocked') {
    return (
      <DriverScreen
        header={header}
        padded
        safeBottom
        footer={
          <BottomActionBar>
            <SecondaryButton
              testID="verify-gate-back"
              label="Back to Active Trip"
              onPress={goActiveTrip}
            />
          </BottomActionBar>
        }>
        <ScreenError
          testID="verify-gate-error"
          retryTestID="verify-gate-retry"
          title={gateStatus === 'blocked' ? 'Verification unavailable' : 'Could not load'}
          body={gateError || 'Could not open verification.'}
          onRetry={gateStatus === 'error' && retryable ? () => void loadGate() : null}
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  const showScanner = stationaryOk && permission?.granted && !success;
  const showPermission = stationaryOk && permission && !permission.granted;
  const footer = !stationaryOk ? (
    <BottomActionBar>
      <PrimaryButton
        testID="verify-stationary-confirm"
        label="I confirm the vehicle is stopped"
        accessibilityLabel="I confirm the vehicle is stopped"
        onPress={() => setStationaryOk(true)}
      />
    </BottomActionBar>
  ) : showPermission ? (
    <BottomActionBar>
      {permission.canAskAgain ? (
        <PrimaryButton
          testID="verify-permission-request"
          label="Allow camera"
          onPress={() => void requestPermission()}
        />
      ) : (
        <PrimaryButton
          testID="verify-permission-settings"
          label="Open settings"
          onPress={() => void Linking.openSettings()}
        />
      )}
    </BottomActionBar>
  ) : success ? (
    <BottomActionBar>
      <PrimaryButton
        testID="verify-continue"
        label={event === 'pickup' ? 'Continue toward delivery' : 'Continue trip'}
        accessibilityLabel={
          event === 'pickup' ? 'Continue toward delivery' : 'Continue trip'
        }
        onPress={goActiveTrip}
      />
    </BottomActionBar>
  ) : null;

  return (
    <DriverScreen
      header={header}
      padded
      testID="verify-trip"
      footer={footer}
      safeBottom={!footer}>
      <View style={styles.body}>
        <DriverCard>
          <View style={styles.contextTop}>
            <StatusBadge
              label={event === 'pickup' ? 'Pickup' : 'Delivery'}
              tone={event === 'pickup' ? 'ready' : 'in_operation'}
            />
          </View>
          <Text style={styles.bodyText}>
            Waiting for customer QR code. Ask the customer to open shipment verification.
          </Text>
          <Text style={styles.meta}>
            Scanning alone does not change trip status. The server confirms a valid code.
          </Text>
        </DriverCard>

        {!stationaryOk ? (
          <DriverCard testID="verify-stationary">
            <Text style={styles.cardTitle}>Safety check</Text>
            <Text style={styles.meta}>
              Confirm the vehicle is stopped and it is safe to scan the verification QR.
            </Text>
          </DriverCard>
        ) : null}

        {showPermission ? (
          <DriverCard testID="verify-permission">
            <Text style={styles.cardTitle}>Camera access needed</Text>
            <Text style={styles.meta}>
              FleetX uses the camera only to scan the verification QR at the site.
            </Text>
            <StatusBadge
              label={permission.canAskAgain ? 'Permission required' : 'Permission blocked'}
              tone="danger"
            />
          </DriverCard>
        ) : null}

        {showScanner ? (
          <View style={styles.scannerCard} testID="verify-scanner">
            {submitting ? (
              <View style={styles.loadingBox} testID="verify-submitting">
                <ActivityIndicator color={DriverTheme.colors.primary} />
                <Text style={styles.bodyText}>Scanning verification code… Verifying with server…</Text>
              </View>
            ) : (
              <>
                <ExpoCameraQrScanner
                  active={scanning && !submitting}
                  onScanned={(r) => void onScanned(r)}
                  testID="qr-scanner-camera"
                />
                <ScanFrame />
              </>
            )}
          </View>
        ) : null}

        {showScanner && !submitting ? (
          <Text style={styles.instruction} testID="verify-scan-hint">
            {event === 'pickup' ? 'Scan pickup QR' : 'Scan delivery QR'}. Position the QR
            code inside the frame.
          </Text>
        ) : null}

        {error ? (
          <Text testID="verify-error" style={styles.error}>
            {error}
          </Text>
        ) : null}

        {success ? (
          <DriverCard testID="verify-success">
            <StatusBadge label="Verified" tone="available" />
            <Text style={styles.cardTitle}>{success}</Text>
            <Text style={styles.bodyText}>
              {event === 'pickup'
                ? 'Shipment is now in transit.'
                : 'Continue to Proof of Delivery.'}
            </Text>
          </DriverCard>
        ) : null}

        <SecondaryButton
          testID="verify-close"
          label={Platform.OS === 'web' ? 'Back' : 'Cancel'}
          disabled={submitting}
          onPress={() => router.back()}
        />
      </View>
    </DriverScreen>
  );
}

const CORNER = 22;
const CORNER_W = 3;

const styles = StyleSheet.create({
  body: {
    flex: 1,
    paddingTop: DriverTheme.spacing.lg,
    paddingBottom: DriverTheme.spacing.md,
    gap: DriverTheme.spacing.md,
  },
  contextTop: {
    marginBottom: DriverTheme.spacing.xs,
  },
  cardTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
  },
  bodyText: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  instruction: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
    textAlign: 'center',
  },
  scannerCard: {
    flex: 1,
    minHeight: 280,
    borderRadius: DriverTheme.radius.large,
    overflow: 'hidden',
    backgroundColor: DriverTheme.colors.navy,
    borderWidth: 1,
    borderColor: DriverTheme.colors.navyElevated,
  },
  loadingBox: {
    flex: 1,
    minHeight: 200,
    alignItems: 'center',
    justifyContent: 'center',
    gap: DriverTheme.spacing.sm,
    backgroundColor: DriverTheme.colors.surface,
  },
  frameOverlay: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    alignItems: 'center',
    justifyContent: 'center',
  },
  frameBox: {
    width: '62%',
    aspectRatio: 1,
    maxWidth: 260,
  },
  corner: {
    position: 'absolute',
    width: CORNER,
    height: CORNER,
    borderColor: DriverTheme.colors.primary,
  },
  cornerTL: {
    top: 0,
    left: 0,
    borderTopWidth: CORNER_W,
    borderLeftWidth: CORNER_W,
  },
  cornerTR: {
    top: 0,
    right: 0,
    borderTopWidth: CORNER_W,
    borderRightWidth: CORNER_W,
  },
  cornerBL: {
    bottom: 0,
    left: 0,
    borderBottomWidth: CORNER_W,
    borderLeftWidth: CORNER_W,
  },
  cornerBR: {
    bottom: 0,
    right: 0,
    borderBottomWidth: CORNER_W,
    borderRightWidth: CORNER_W,
  },
  error: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.danger,
    textAlign: 'center',
  },
});
