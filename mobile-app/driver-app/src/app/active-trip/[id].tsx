import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Linking,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { Flag, MapPin, Navigation } from 'lucide-react-native';

import { OfflineBanner } from '@/components/OfflineBanner';
import {
  BottomActionBar,
  DangerButton,
  DriverCard,
  DriverHeader,
  DriverScreen,
  InfoRow,
  PrimaryButton,
  SecondaryButton,
  SectionHeader,
  StatusBadge,
  TripProgress,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import {
  nextActionForPhase,
  phaseStageTitle,
  phaseStatusLabel,
  phaseWhatNow,
} from '@/features/driverApi/executionPhase';
import {
  getAssignment,
  getTripMap,
  completeAssignment,
  transitionExecutionPhase,
} from '@/features/driverApi/work';
import { formatPlaceLine } from '@/features/driverApi/workMapper';
import type { DeviceFix, DriverGeoPoint, DriverTripMap } from '@/features/driverApi/tripTypes';
import { mobileRealtime } from '@/features/messaging/realtime';
import {
  DriverWorkApiError,
  isRetryableWorkError,
  type DriverWorkAssignment,
} from '@/features/driverApi/workTypes';
import { TripAwarenessBanner } from '@/features/notifications/TripAwarenessBanner';
import { useNetworkStatus } from '@/lib/network/useNetworkStatus';
import { isVehicleMoving, MOTION_DEFER_HINT } from '@/lib/safety/drivingMotion';
import { ReportIssueSheet } from '@/features/pod/ReportIssueSheet';
import { DriverTripMapView } from '@/features/tracking/DriverTripMapView';
import {
  resolveAutoMapFocus,
  resolveNavigationTarget,
  type MapFocusMode,
} from '@/features/tracking/mapFocusMode';
import { openExternalMaps } from '@/features/tracking/openExternalMaps';
import {
  createExpoLocationProvider,
  type LocationPermissionState,
  type LocationSubscription,
} from '@/features/tracking/locationProvider';
import { TelemetryPublisher, type TelemetryPublisherStatus } from '@/features/tracking/TelemetryPublisher';
import { useAuth } from '@/lib/auth/AuthContext';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';
import {
  getOwnCurrentAssignmentEvaluation,
  type DriverSelfCurrentAssignmentEvaluation,
} from '@/features/driverApi/performance';
import { CurrentTripPerformanceCard } from '@/features/performance/CurrentTripPerformanceCard';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

/**
 * Active Trip (D4–D6): execution CTAs + professional schematic map + foreground telemetry.
 */
export default function ActiveTripScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const assignmentId = String(id || '');
  const router = useRouter();
  const { canOperate } = useAuth();

  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
  const [error, setError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [assignment, setAssignment] = useState<DriverWorkAssignment | null>(null);
  const [trip, setTrip] = useState<DriverTripMap | null>(null);
  const [tripMapLoading, setTripMapLoading] = useState(true);
  const [tripPerformance, setTripPerformance] =
    useState<DriverSelfCurrentAssignmentEvaluation | null>(null);
  const [tripPerformanceLoading, setTripPerformanceLoading] = useState(false);
  const [tripPerformanceError, setTripPerformanceError] = useState<string | null>(null);
  const [mutating, setMutating] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [permission, setPermission] = useState<LocationPermissionState>('unknown');
  const [deviceFix, setDeviceFix] = useState<DeviceFix | null>(null);
  const [mapTick, setMapTick] = useState(0);
  const [mapFocusOverride, setMapFocusOverride] = useState<MapFocusMode | null>(null);
  const [mapsHint, setMapsHint] = useState<string | null>(null);
  const [issueOpen, setIssueOpen] = useState(false);
  const [issueMode, setIssueMode] = useState<'issue' | 'fail'>('issue');
  const [awarenessCritical, setAwarenessCritical] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [publisherStatus, setPublisherStatus] = useState<TelemetryPublisherStatus | null>(null);
  const { online } = useNetworkStatus();
  const mutateLock = useRef(false);
  const publisherRef = useRef<TelemetryPublisher | null>(null);
  const watchRef = useRef<LocationSubscription | null>(null);
  const locationProvider = useRef(createExpoLocationProvider()).current;

  const load = useCallback(async () => {
    if (!canOperate || !assignmentId) {
      setStatus('error');
      setRetryable(false);
      setError('Active trip is unavailable.');
      return;
    }
    setStatus('loading');
    setTripMapLoading(true);
    setError(null);
    try {
      const [next, map] = await Promise.all([
        getAssignment(assignmentId),
        getTripMap(assignmentId).catch(() => null),
      ]);
      setAssignment(next);
      setTrip(map);
      setTripMapLoading(false);
      setStatus('ready');
    } catch (e) {
      setAssignment(null);
      setTrip(null);
      setTripMapLoading(false);
      setRetryable(isRetryableWorkError(e));
      setError(
        e instanceof DriverWorkApiError
          ? e.statusCode === 404
            ? 'This trip is no longer available.'
            : e.message
          : 'Could not load active trip.'
      );
      setStatus('error');
    }
  }, [assignmentId, canOperate]);

  /** Performance is auxiliary — never tied to GPS samples. */
  const loadTripPerformance = useCallback(async () => {
    if (!canOperate || !assignmentId) {
      setTripPerformance(null);
      setTripPerformanceError(null);
      return;
    }
    setTripPerformanceLoading(true);
    try {
      const next = await getOwnCurrentAssignmentEvaluation(assignmentId);
      setTripPerformance(next);
      setTripPerformanceError(null);
    } catch (e) {
      setTripPerformance(null);
      setTripPerformanceError(
        e instanceof DriverWorkApiError ? e.message : 'Performance unavailable'
      );
    } finally {
      setTripPerformanceLoading(false);
    }
  }, [assignmentId, canOperate]);

  const teardownTelemetry = useCallback(() => {
    publisherRef.current?.stop();
    publisherRef.current = null;
    watchRef.current?.remove();
    watchRef.current = null;
  }, []);

  const stopTelemetry = useCallback(() => {
    teardownTelemetry();
    setPublisherStatus(null);
  }, [teardownTelemetry]);

  const startTelemetry = useCallback(async () => {
    stopTelemetry();
    if (!canOperate || !assignmentId) return;

    const perm = await locationProvider.getPermissionState();
    setPermission(perm);
    if (perm !== 'granted') return;

    const publisher = new TelemetryPublisher(assignmentId);
    publisherRef.current = publisher;
    setPublisherStatus(publisher.getStatus());

    try {
      const sub = await locationProvider.watchPosition(
        (fix) => {
          setDeviceFix(fix);
          void publisher.onFix(fix).then(() => {
            setPublisherStatus(publisher.getStatus());
            if (publisher.getStatus() === 'stopped') {
              void load();
            }
          });
        },
        () => setPermission((p) => (p === 'granted' ? p : 'denied'))
      );
      watchRef.current = sub;
    } catch {
      setPermission('denied');
    }
  }, [assignmentId, canOperate, load, locationProvider, stopTelemetry]);

  useFocusEffect(
    useCallback(() => {
      void load();
      void loadTripPerformance();
      return () => stopTelemetry();
    }, [load, loadTripPerformance, stopTelemetry])
  );

  useEffect(() => {
    if (status !== 'ready' || !assignment) return;
    const stale = !assignment.isActive || assignment.status?.key !== 'in_operation';
    if (stale || awarenessCritical) {
      teardownTelemetry();
      const timer = setTimeout(() => setPublisherStatus(null), 0);
      return () => clearTimeout(timer);
    }
    const timer = setTimeout(() => void startTelemetry(), 0);
    return () => {
      clearTimeout(timer);
      stopTelemetry();
    };
  }, [assignment, awarenessCritical, startTelemetry, status, stopTelemetry, teardownTelemetry]);

  useEffect(() => {
    if (status !== 'ready' || !assignmentId) return;

    let lastTimestamp = 0;

    const unsubPromise = mobileRealtime.subscribeAssignmentRoute(
      assignmentId,
      (payload) => {
        if (!payload || typeof payload !== 'object') return;

        const eventAssignmentId = String(payload.assignment_id || '');
        const eventShipmentId = String(payload.shipment_id || '');
        if (
          eventAssignmentId &&
          eventAssignmentId !== assignmentId &&
          assignment?.shipment?.id &&
          eventShipmentId !== assignment.shipment.id
        ) {
          return;
        }

        const timestampStr = payload.metadata?.broadcast_at || payload.metadata?.created_at;
        const eventTime = timestampStr ? new Date(timestampStr).getTime() : Date.now();
        if (lastTimestamp > 0 && eventTime <= lastTimestamp) {
          return;
        }

        const rawCoords =
          payload.route?.geometry?.coordinates || payload.route?.coordinates;
        if (!Array.isArray(rawCoords) || rawCoords.length < 2) {
          return;
        }

        const normalized: DriverGeoPoint[] = [];
        for (const pt of rawCoords) {
          if (Array.isArray(pt) && pt.length >= 2) {
            normalized.push({ latitude: Number(pt[0]), longitude: Number(pt[1]) });
          } else if (pt && typeof pt === 'object' && 'latitude' in pt && 'longitude' in pt) {
            normalized.push({ latitude: Number(pt.latitude), longitude: Number(pt.longitude) });
          }
        }

        if (normalized.length < 2) return;

        lastTimestamp = eventTime;

        setTrip((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            route: { coordinates: normalized },
            routeAvailable: true,
          };
        });
      }
    );

    return () => {
      void unsubPromise.then((unsub) => unsub());
    };
  }, [assignmentId, status, assignment?.shipment?.id]);

  const requestLocation = useCallback(async () => {
    setPermission('requesting');
    const next = await locationProvider.requestPermission();
    setPermission(next);
    if (next === 'granted') {
      void startTelemetry();
    }
  }, [locationProvider, startTelemetry]);

  const runPhaseMutation = useCallback(
    async (command: Parameters<typeof transitionExecutionPhase>[1]) => {
      if (mutateLock.current || mutating) return;
      mutateLock.current = true;
      setMutating(true);
      setActionError(null);
      try {
        const next = await transitionExecutionPhase(assignmentId, command);
        setAssignment(next);
        setTripMapLoading(true);
        const map = await getTripMap(assignmentId).catch(() => null);
        setTrip(map);
        setTripMapLoading(false);
        void loadTripPerformance();
      } catch (e) {
        const msg =
          e instanceof DriverWorkApiError ? e.message : 'Could not update trip phase.';
        setActionError(msg);
        if (e instanceof DriverWorkApiError && (e.statusCode === 404 || e.statusCode === 409)) {
          stopTelemetry();
          await load();
        }
      } finally {
        setMutating(false);
        mutateLock.current = false;
      }
    },
    [assignmentId, load, loadTripPerformance, mutating, stopTelemetry]
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await Promise.all([load(), loadTripPerformance()]);
    } finally {
      setRefreshing(false);
    }
  }, [load, loadTripPerformance]);

  const openIssueSheet = useCallback(
    (mode: 'issue' | 'fail') => {
      if (isVehicleMoving(deviceFix?.speed ?? null)) {
        setActionError(MOTION_DEFER_HINT);
        return;
      }
      setIssueMode(mode);
      setIssueOpen(true);
    },
    [deviceFix?.speed]
  );
  const runComplete = useCallback(async () => {
    if (mutateLock.current || mutating) return;
    mutateLock.current = true;
    setMutating(true);
    setActionError(null);
    try {
      const result = await completeAssignment(assignmentId);
      stopTelemetry();
      const ref = encodeURIComponent(result.assignment.shipment?.shipmentNumber || '');
      const at = encodeURIComponent(result.completedAt || '');
      router.replace(
        `/delivery-complete/${assignmentId}?ref=${ref}&at=${at}` as Href
      );
    } catch (e) {
      const msg =
        e instanceof DriverWorkApiError ? e.message : 'Could not complete assignment.';
      setActionError(msg);
      if (e instanceof DriverWorkApiError && (e.statusCode === 404 || e.statusCode === 409)) {
        stopTelemetry();
        await load();
      }
    } finally {
      setMutating(false);
      mutateLock.current = false;
    }
  }, [assignmentId, load, mutating, router, stopTelemetry]);

  const phaseForMap = assignment?.execution?.phase ?? trip?.execution.phase ?? null;
  const statusKeyForMap = assignment?.status?.key ?? null;
  const isActiveForMap = assignment?.isActive ?? false;

  useEffect(() => {
    setMapFocusOverride(null);
  }, [phaseForMap, statusKeyForMap, isActiveForMap]);

  const onOpenInMaps = useCallback(async () => {
    setMapsHint(null);
    const kind = resolveNavigationTarget(phaseForMap, statusKeyForMap, isActiveForMap);
    const coords =
      kind === 'pickup'
        ? trip?.shipment?.pickup.coordinates
        : trip?.shipment?.delivery.coordinates;
    if (!coords) {
      setMapsHint('Location details unavailable');
      return;
    }
    const label =
      kind === 'pickup'
        ? trip?.shipment?.pickup.company || trip?.shipment?.pickup.city || 'Pickup'
        : trip?.shipment?.delivery.company || trip?.shipment?.delivery.city || 'Delivery';
    const ok = await openExternalMaps({
      latitude: coords.latitude,
      longitude: coords.longitude,
      label,
    });
    if (!ok) {
      setMapsHint('Could not open Maps on this device.');
    }
  }, [isActiveForMap, phaseForMap, statusKeyForMap, trip]);

  const backHeader = (
    <DriverHeader title="Trip" onBack={() => router.replace('/(tabs)')} />
  );

  if (status === 'loading') {
    return (
      <DriverScreen header={backHeader} padded safeBottom>
        <ScreenLoading testID="active-trip-loading" label="Loading trip…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (status === 'error' || !assignment) {
    return (
      <DriverScreen header={backHeader} padded safeBottom>
        <View style={styles.issueRow}>
          <ScreenError
            testID="active-trip-error"
            retryTestID="active-trip-retry"
            title="Could not load trip"
            body={error || 'Could not load trip.'}
            onRetry={retryable ? () => void load() : null}
            theme={uxTheme}
          />
          <SecondaryButton
            label="Back to Work"
            onPress={() => router.replace('/(tabs)')}
          />
        </View>
      </DriverScreen>
    );
  }

  const shipment = assignment.shipment;
  const statusKey = assignment.status?.key;
  const stale =
    !assignment.isActive ||
    statusKey !== 'in_operation' ||
    awarenessCritical;
  const phase = assignment.execution?.phase ?? trip?.execution.phase ?? null;
  const stage = assignment.execution?.stage ?? trip?.execution.stage ?? null;
  const action = nextActionForPhase(phase);
  const place = stage === 'delivery' ? shipment?.delivery : shipment?.pickup;
  const planned =
    stage === 'delivery' ? shipment?.plannedDeliveryDate : shipment?.plannedPickupDate;

  const pubStatus = publisherStatus;
  const trackingLabel = stale
    ? 'UNAVAILABLE'
    : permission !== 'granted'
      ? 'UNAVAILABLE'
      : pubStatus === 'active'
        ? 'ACTIVE'
        : pubStatus === 'limited' || pubStatus === 'error'
          ? 'LIMITED'
          : trip?.trackingStatus === 'active'
            ? 'ACTIVE'
            : trip?.trackingStatus === 'limited'
              ? 'LIMITED'
              : 'UNAVAILABLE';

  const permissionLabel =
    permission === 'granted'
      ? null
      : permission === 'requesting'
        ? 'Requesting location permission…'
        : 'Location services are disabled. Enable GPS for live driver tracking.';

  const moving = isVehicleMoving(deviceFix?.speed ?? null);
  const interactiveBlocked = moving && !stale;
  const showPrimaryCta = !stale && !interactiveBlocked && action.kind !== 'none';

  const mapFocus = mapFocusOverride ?? resolveAutoMapFocus(phase, statusKey, assignment.isActive);

  const navKind = resolveNavigationTarget(phase, statusKey, assignment.isActive);
  const navCoords =
    navKind === 'pickup'
      ? trip?.shipment?.pickup.coordinates
      : trip?.shipment?.delivery.coordinates;
  const mapsDisabled = !navCoords;

  const trackingTone =
    trackingLabel === 'ACTIVE'
      ? 'available'
      : trackingLabel === 'LIMITED'
        ? 'in_operation'
        : 'neutral';

  const footer = showPrimaryCta ? (
    <BottomActionBar>
      {actionError ? (
        <Text testID="active-trip-action-error" style={styles.errorText}>
          {actionError}
        </Text>
      ) : null}
      {action.kind === 'mutate' ? (
        <PrimaryButton
          testID="active-trip-cta"
          loadingTestID="active-trip-cta-loading"
          label={action.label}
          accessibilityLabel={action.label}
          disabled={mutating}
          loading={mutating}
          onPress={() => void runPhaseMutation(action.command)}
        />
      ) : null}
      {action.kind === 'verify' ? (
        <PrimaryButton
          testID="active-trip-cta"
          label={action.label}
          accessibilityLabel={action.label}
          onPress={() =>
            router.push(`/verify-trip/${assignment.id}?event=${action.event}` as Href)
          }
        />
      ) : null}
      {action.kind === 'refresh' ? (
        <PrimaryButton
          testID="active-trip-cta"
          loadingTestID="active-trip-cta-loading"
          label={action.label}
          accessibilityLabel={action.label}
          disabled={mutating}
          loading={mutating}
          onPress={() => void load()}
        />
      ) : null}
      {action.kind === 'pod' ? (
        <PrimaryButton
          testID="active-trip-cta"
          label={action.label}
          accessibilityLabel={action.label}
          onPress={() => router.push(`/pod/${assignment.id}` as Href)}
        />
      ) : null}
      {action.kind === 'complete' ? (
        <PrimaryButton
          testID="active-trip-cta"
          loadingTestID="active-trip-cta-loading"
          label={action.label}
          accessibilityLabel={action.label}
          disabled={mutating}
          loading={mutating}
          onPress={() => void runComplete()}
        />
      ) : null}
    </BottomActionBar>
  ) : null;

  return (
    <DriverScreen
      header={
        <DriverHeader
          title="Trip"
          subtitle={assignment.assignmentCode || `Assignment #${assignment.id}`}
          statusLabel={assignment.status?.name ?? null}
          statusAvailable={!stale}
          onBack={() => router.replace('/(tabs)')}
        />
      }
      footer={footer}
      safeBottom={!showPrimaryCta}>
      <ScrollView
        style={styles.scrollFlex}
        contentContainerStyle={styles.scroll}
        testID="active-trip"
        keyboardShouldPersistTaps="handled"
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => void onRefresh()}
            tintColor={DriverTheme.colors.primary}
            colors={[DriverTheme.colors.primary]}
          />
        }>
        {!online ? <OfflineBanner testID="active-trip-offline" /> : null}

        <View style={styles.headerBlock} testID="active-trip-header">
          <Text style={styles.statusLine} testID="active-trip-status">
            {phaseStageTitle(stage)} · {phaseStatusLabel(phase)}
          </Text>
          <Text style={styles.shipmentLine} testID="active-trip-header-shipment">
            {shipment?.shipmentNumber ?? 'Shipment unavailable'}
          </Text>
          <StatusBadge label={`Tracking ${trackingLabel}`} tone={trackingTone} />
        </View>

        <TripAwarenessBanner
          assignmentId={assignmentId}
          speedKmh={deviceFix?.speed ?? null}
          theme={uxTheme}
          stale={!assignment.isActive || statusKey !== 'in_operation'}
          onCriticalChange={setAwarenessCritical}
        />

        {stale ? (
          <DriverCard style={styles.warnCard} testID="active-trip-stale">
            <Text style={styles.warnTitle}>
              {statusKey === 'cancelled'
                ? 'Assignment cancelled'
                : 'Trip no longer in operation'}
            </Text>
            <Text style={styles.meta}>
              Tracking stopped. Further trip actions are blocked. Refresh or return to Work.
            </Text>
            <SecondaryButton
              testID="active-trip-refresh"
              label="Refresh"
              onPress={() => void load()}
            />
            <SecondaryButton
              testID="active-trip-stale-work"
              label="Back to Work"
              onPress={() => router.replace('/(tabs)')}
            />
          </DriverCard>
        ) : null}

        <View style={styles.mapShell} testID="active-trip-map-section">
          <DriverTripMapView
            key={`map-${mapTick}`}
            trip={trip}
            deviceFix={!stale ? deviceFix : null}
            theme={uxTheme}
            trackingLabel={trackingLabel}
            permissionLabel={permissionLabel}
            focus={mapFocus}
            mapLoading={tripMapLoading}
            locationLoading={
              !stale && permission === 'granted' && deviceFix == null && !tripMapLoading
            }
            executionPhase={phase}
            assignmentStatusLabel={assignment.status?.name ?? phaseStatusLabel(phase)}
            assignmentStatusKey={statusKey ?? null}
            online={online}
            mapsActionDisabled={mapsDisabled}
            mapsActionHint={mapsHint}
            onOpenInMaps={() => void onOpenInMaps()}
            onRecenter={() => {
              setMapFocusOverride('driver');
              setMapTick((n) => n + 1);
            }}
            onFitTrip={() => {
              setMapFocusOverride('overview');
              setMapTick((n) => n + 1);
            }}
          />
        </View>

        {!stale && permission !== 'granted' ? (
          <DriverCard testID="active-trip-location-gate">
            {permission === 'blocked' ? (
              <PrimaryButton
                testID="active-trip-open-settings"
                label="Open settings"
                accessibilityLabel="Open settings"
                onPress={() => void Linking.openSettings()}
              />
            ) : (
              <PrimaryButton
                testID="active-trip-request-location"
                label="Allow location"
                accessibilityLabel="Allow location"
                onPress={() => void requestLocation()}
              />
            )}
          </DriverCard>
        ) : null}

        <SectionHeader label="Current phase" />
        <DriverCard testID="active-trip-phase">
          <TripProgress phase={phase} />
          <Text style={styles.cardTitle} testID="active-trip-phase-title">
            {phaseStageTitle(stage)}
          </Text>
          <Text style={styles.body} testID="active-trip-phase-status">
            {phaseStatusLabel(phase)}
          </Text>
        </DriverCard>

        <SectionHeader label="What's happening now" />
        <DriverCard testID="active-trip-next">
          <Text style={styles.body} testID="active-trip-what-now">
            {phaseWhatNow(phase)}
          </Text>
          {!showPrimaryCta && actionError ? (
            <Text testID="active-trip-action-error" style={styles.errorText}>
              {actionError}
            </Text>
          ) : null}

          {interactiveBlocked ? (
            <Text testID="active-trip-motion-hint" style={styles.meta}>
              {MOTION_DEFER_HINT}
            </Text>
          ) : null}

          {!stale && action.kind === 'none' ? (
            <Text testID="active-trip-cta-none" style={styles.meta}>
              {action.hint}
            </Text>
          ) : null}

          {!stale && !interactiveBlocked ? (
            <View style={styles.issueRow}>
              <SecondaryButton
                testID="active-trip-report-issue"
                label="Report an issue"
                accessibilityLabel="Report an issue"
                icon={<Flag size={18} color={DriverTheme.colors.primary} />}
                onPress={() => openIssueSheet('issue')}
              />
              {stage === 'delivery' &&
                phase !== 'delivery_verified' &&
                phase !== 'pod_submitted' &&
                phase !== 'delivery_failed' ? (
                <DangerButton
                  testID="active-trip-fail-delivery"
                  label="Failed delivery"
                  accessibilityLabel="Failed delivery"
                  onPress={() => openIssueSheet('fail')}
                />
              ) : null}
            </View>
          ) : null}

          {stale ? (
            <Text style={styles.meta}>
              Execution actions are unavailable until the trip is restored.
            </Text>
          ) : null}
        </DriverCard>

        <CurrentTripPerformanceCard
          evaluation={tripPerformance}
          loading={tripPerformanceLoading}
          error={tripPerformanceError}
          title="Current Trip Performance"
          showRecentEvents
          testID="active-trip-performance"
        />

        <SectionHeader label="Destination" />
        <DriverCard testID="active-trip-location">
          <InfoRow
            label={stage === 'delivery' ? 'Going to delivery' : 'Going to pickup'}
            value={place ? formatPlaceLine(place) : 'Unavailable'}
            icon={
              stage === 'delivery' ? (
                <Navigation size={18} color={DriverTheme.colors.navyElevated} />
              ) : (
                <MapPin size={18} color={DriverTheme.colors.primary} />
              )
            }
          />
          {place?.address ? <Text style={styles.meta}>{place.address}</Text> : null}
          {planned ? <Text style={styles.meta}>Planned: {planned}</Text> : null}
        </DriverCard>

        <DriverCard testID="active-trip-shipment">
          <Text style={styles.sectionLabel}>Shipment</Text>
          <Text style={styles.body}>{shipment?.shipmentNumber ?? 'Unavailable'}</Text>
          <Text style={styles.meta}>{shipment?.status?.name ?? 'Status unavailable'}</Text>
        </DriverCard>

        <DriverCard testID="active-trip-assets">
          <Text style={styles.sectionLabel}>Vehicle / Trailer</Text>
          <Text style={styles.body}>
            Vehicle:{' '}
            {assignment.vehicle?.plateNumber ||
              assignment.vehicle?.fleetNumber ||
              'Unavailable'}
          </Text>
          <Text style={styles.body}>
            Trailer:{' '}
            {assignment.trailer?.trailerCode ||
              assignment.trailer?.plateNumber ||
              'None'}
          </Text>
        </DriverCard>
      </ScrollView>

      <ReportIssueSheet
        visible={issueOpen}
        assignmentId={assignmentId}
        mode={issueMode}
        onClose={() => setIssueOpen(false)}
        onReported={() => {
          setIssueOpen(false);
          void load();
        }}
      />
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  scrollFlex: { flex: 1 },
  scroll: {
    padding: DriverTheme.spacing.lg,
    gap: DriverTheme.spacing.md,
    paddingBottom: DriverTheme.spacing.xxl,
  },
  headerBlock: {
    gap: DriverTheme.spacing.sm,
  },
  statusLine: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
  },
  shipmentLine: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  mapShell: {
    backgroundColor: DriverTheme.colors.surface,
    borderRadius: DriverTheme.radius.large,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    overflow: 'hidden',
    ...DriverTheme.elevation.card,
  },
  cardTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    marginTop: DriverTheme.spacing.sm,
  },
  sectionLabel: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  body: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  warnCard: {
    backgroundColor: DriverTheme.colors.dangerSoft,
    borderColor: '#FECACA',
  },
  warnTitle: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.danger,
  },
  errorText: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.danger,
  },
  issueRow: {
    gap: DriverTheme.spacing.sm,
    marginTop: DriverTheme.spacing.sm,
  },
});
