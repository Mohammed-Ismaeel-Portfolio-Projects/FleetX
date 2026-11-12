// @ts-nocheck — lucide icon props vs RN ColorValue
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { Redirect, useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { ChevronLeft, Navigation } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  ClientShipmentsApiError,
  formatCityCountry,
  formatLastKnownRecordedAt,
  getClientShipmentTracking,
} from '@/features/shipments/shipmentsApi';
import { customerFacingShipmentStatus } from '@/features/shipments/shipmentWorkspace';
import { ShipmentTrackingMap } from '@/features/shipments/ShipmentTrackingMap';
import {
  formatPlannedDeliveryLabel,
  locationFreshnessLabel,
} from '@/features/shipments/shipmentTrackingMap';
import type { ClientShipmentMilestone, ClientShipmentTracking } from '@/features/shipments/types';

const MILESTONE_ORDER = ['created', 'pickup', 'delivery'] as const;

function displayText(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  return trimmed ? trimmed : null;
}

function sortMilestones(milestones: ClientShipmentMilestone[]): ClientShipmentMilestone[] {
  const byKey = new Map(milestones.map((m) => [m.key, m]));
  const ordered: ClientShipmentMilestone[] = [];
  for (const key of MILESTONE_ORDER) {
    const row = byKey.get(key);
    if (row) ordered.push(row);
  }
  return ordered;
}

function MilestoneMetaRow({
  label,
  value,
  theme,
  testID,
}: {
  label: string;
  value: string | null;
  theme: { text: string; textSecondary: string };
  testID: string;
}) {
  const shown = value || 'Unavailable';
  return (
    <View style={styles.metaRow} accessible accessibilityLabel={`${label}: ${shown}`}>
      <Text style={[styles.metaLabel, { color: theme.textSecondary }]}>{label}</Text>
      <Text
        style={[styles.metaValue, { color: value ? theme.text : theme.textSecondary }]}
        testID={testID}
        numberOfLines={3}>
        {shown}
      </Text>
    </View>
  );
}

function MilestoneBlock({
  milestone,
  theme,
}: {
  milestone: ClientShipmentMilestone;
  theme: { text: string; textSecondary: string; border: string; backgroundElement: string };
}) {
  if (milestone.key === 'created') {
    const occurred = displayText(milestone.occurredAt);
    return (
      <View
        style={[styles.milestoneCard, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        testID="shipment-progress-milestone-created"
        accessibilityRole="summary"
        accessibilityLabel={
          occurred ? `Created milestone, occurred ${occurred}` : 'Created milestone, occurrence time unavailable'
        }>
        <Text style={[styles.milestoneTitle, { color: theme.text }]} accessibilityRole="header">
          Created
        </Text>
        <MilestoneMetaRow
          label="Occurred"
          value={occurred}
          theme={theme}
          testID="shipment-progress-milestone-created-occurred"
        />
      </View>
    );
  }

  if (milestone.key === 'pickup') {
    const planned = displayText(milestone.plannedAt);
    const actual = displayText(milestone.actualAt);
    return (
      <View
        style={[styles.milestoneCard, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        testID="shipment-progress-milestone-pickup"
        accessibilityRole="summary"
        accessibilityLabel={`Pickup milestone. Planned ${planned || 'unavailable'}. Actual ${actual || 'unavailable'}.`}>
        <Text style={[styles.milestoneTitle, { color: theme.text }]} accessibilityRole="header">
          Pickup
        </Text>
        <MilestoneMetaRow
          label="Planned"
          value={planned}
          theme={theme}
          testID="shipment-progress-milestone-pickup-planned"
        />
        <MilestoneMetaRow
          label="Actual"
          value={actual}
          theme={theme}
          testID="shipment-progress-milestone-pickup-actual"
        />
      </View>
    );
  }

  const planned = displayText(milestone.plannedAt);
  const actual = displayText(milestone.actualAt);
  return (
    <View
      style={[styles.milestoneCard, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
      testID="shipment-progress-milestone-delivery"
      accessibilityRole="summary"
      accessibilityLabel={`Delivery milestone. Planned ${planned || 'unavailable'}. Actual ${actual || 'unavailable'}.`}>
      <Text style={[styles.milestoneTitle, { color: theme.text }]} accessibilityRole="header">
        Delivery
      </Text>
      <MilestoneMetaRow
        label="Planned"
        value={planned}
        theme={theme}
        testID="shipment-progress-milestone-delivery-planned"
      />
      <MilestoneMetaRow
        label="Actual"
        value={actual}
        theme={theme}
        testID="shipment-progress-milestone-delivery-actual"
      />
    </View>
  );
}

/**
 * C2.7-D2 / C5.1 / C5.5-C4.3 — Client shipment progress + schematic journey overview.
 * Single load via getClientShipmentTracking; map consumes the same domain object.
 * No live GPS polling, Ops telemetry, arrival estimates, or native map dependency.
 */
export default function ShipmentTrackingScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const shipmentId = String(Array.isArray(id) ? id[0] : id || '').trim();
  const { roleOk, identityLinked, ready, isAuthenticated } = useAuth();

  const [tracking, setTracking] = useState<ClientShipmentTracking | null>(null);
  const [loadedForId, setLoadedForId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);

  const inFlightRef = useRef(false);
  const requestSeqRef = useRef(0);
  const mountedRef = useRef(true);
  const shipmentIdRef = useRef(shipmentId);
  shipmentIdRef.current = shipmentId;

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  // Route ID change: drop stale tracking immediately — never show previous ID's data.
  useEffect(() => {
    setTracking(null);
    setLoadedForId(null);
    setError(null);
    setErrorStatus(undefined);
    setErrorIsNetwork(false);
    setLoading(Boolean(shipmentId));
    inFlightRef.current = false;
    requestSeqRef.current += 1;
  }, [shipmentId]);

  const load = useCallback(async () => {
    if (!roleOk || !identityLinked) {
      if (!mountedRef.current) return;
      setTracking(null);
      setLoadedForId(null);
      setLoading(false);
      return;
    }

    const idToLoad = shipmentIdRef.current;
    if (!idToLoad) {
      if (!mountedRef.current) return;
      setTracking(null);
      setLoadedForId(null);
      setError('Shipment id unavailable.');
      setErrorStatus(undefined);
      setErrorIsNetwork(false);
      setLoading(false);
      return;
    }

    // Prevent duplicate concurrent fetches (focus + retry races).
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    const requestSeq = ++requestSeqRef.current;

    if (!mountedRef.current) {
      inFlightRef.current = false;
      return;
    }

    setLoading(true);
    setError(null);
    setErrorStatus(undefined);
    setErrorIsNetwork(false);
    // Clear any prior success payload while refetching this ID (no stale overlay).
    setTracking(null);
    setLoadedForId(null);

    let holdLoadingForSession = false;
    try {
      const result = await getClientShipmentTracking(idToLoad);

      if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;
      // Ignore if the route ID changed mid-request.
      if (shipmentIdRef.current !== idToLoad) return;

      setTracking(result);
      setLoadedForId(idToLoad);
      setError(null);
      setErrorStatus(undefined);
      setErrorIsNetwork(false);
    } catch (e) {
      if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;
      if (shipmentIdRef.current !== idToLoad) return;

      const err = e as ClientShipmentsApiError;
      // 401: shared API interceptor + AuthContext — do not reinterpret as empty progress.
      if (err.statusCode === 401) {
        holdLoadingForSession = true;
        setTracking(null);
        setLoadedForId(null);
        return;
      }

      setTracking(null);
      setLoadedForId(null);
      setError(err.message || 'Failed to load shipment progress');
      setErrorStatus(err.statusCode);
      setErrorIsNetwork(Boolean(err.isNetwork));
    } finally {
      if (requestSeq === requestSeqRef.current) {
        inFlightRef.current = false;
        if (mountedRef.current) {
          setLoading(holdLoadingForSession ? true : false);
        }
      }
    }
  }, [roleOk, identityLinked]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load, shipmentId])
  );

  const goBack = () => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    // Safe fallback: return to this shipment's details (not messaging handoff / bare tracking tab).
    if (shipmentId) {
      router.replace(`/shipment-details/${shipmentId}`);
      return;
    }
    router.replace('/(tabs)/shipments');
  };

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Shipment progress"
        testID="shipment-progress-forbidden-role"
        icon={Navigation}
        showBack
        explanation="Shipment progress is only available for FleetX client accounts."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Shipment progress"
        testID="shipment-progress-unlinked"
        icon={Navigation}
        showBack
        explanation="Your account is not linked to a company. Company-scoped shipment progress cannot be loaded."
      />
    );
  }

  const backButton = (
    <View style={styles.backRow}>
      <TouchableOpacity
        testID="shipment-progress-back"
        style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        onPress={goBack}
        activeOpacity={0.7}
        hitSlop={8}
        accessibilityRole="button"
        accessibilityLabel="Go back to shipment details"
        accessibilityHint="Returns to the previous screen or this shipment's details">
        <ChevronLeft size={20} color={theme.text} />
      </TouchableOpacity>
    </View>
  );

  const showLoading =
    loading || (Boolean(shipmentId) && loadedForId !== shipmentId && !error && !tracking);

  if (showLoading) {
    return (
      <GradientBackground>
        {backButton}
        <View
          style={styles.center}
          testID="shipment-progress-loading"
          accessibilityLabel="Loading shipment progress"
          accessibilityRole="progressbar">
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.muted, { color: theme.textSecondary }]}>Loading shipment progress…</Text>
        </View>
      </GradientBackground>
    );
  }

  if (error && !tracking) {
    const isForbidden = errorStatus === 403;
    const isNotFound = errorStatus === 404;
    // Retry only for network/server failures — not 403/404.
    const canRetry = !isForbidden && !isNotFound;

    return (
      <GradientBackground>
        {backButton}
        <View
          style={styles.centerPad}
          testID={
            isForbidden
              ? 'shipment-progress-forbidden'
              : isNotFound
                ? 'shipment-progress-not-found'
                : 'shipment-progress-error'
          }
          accessibilityRole="summary">
          <Text style={[styles.errorTitle, { color: theme.text }]}>
            {isForbidden
              ? 'Access unavailable'
              : isNotFound
                ? 'Shipment progress unavailable'
                : errorIsNetwork
                  ? 'Connection problem'
                  : 'Could not load shipment progress'}
          </Text>
          <Text style={[styles.errorBody, { color: theme.textSecondary }]}>
            {isForbidden
              ? 'You do not have access to this shipment.'
              : isNotFound
                ? 'Shipment progress could not be found for your company.'
                : error}
          </Text>
          {canRetry ? (
            <Pressable
              testID="shipment-progress-retry"
              style={[styles.retryBtn, { backgroundColor: theme.primary }]}
              onPress={() => {
                if (inFlightRef.current) return;
                void load();
              }}
              accessibilityRole="button"
              accessibilityLabel="Retry loading shipment progress"
              accessibilityHint="Attempts to load shipment progress again">
              <Text style={styles.retryText}>Retry</Text>
            </Pressable>
          ) : null}
        </View>
      </GradientBackground>
    );
  }

  if (!tracking || loadedForId !== shipmentId) {
    return (
      <GradientBackground>
        {backButton}
        <View style={styles.centerPad} testID="shipment-progress-error">
          <Text style={[styles.errorTitle, { color: theme.text }]}>Shipment progress unavailable</Text>
          <Text style={[styles.errorBody, { color: theme.textSecondary }]}>
            Shipment progress could not be loaded.
          </Text>
          <Pressable
            testID="shipment-progress-retry"
            style={[styles.retryBtn, { backgroundColor: theme.primary }]}
            onPress={() => {
              if (inFlightRef.current) return;
              void load();
            }}
            accessibilityRole="button"
            accessibilityLabel="Retry loading shipment progress">
            <Text style={styles.retryText}>Retry</Text>
          </Pressable>
        </View>
      </GradientBackground>
    );
  }

  const statusRaw = displayText(tracking.status?.name);
  const statusLabel = customerFacingShipmentStatus(statusRaw);
  const shipmentNumber = displayText(tracking.shipmentNumber) || 'Shipment';
  const origin = formatCityCountry(tracking.origin.city, tracking.origin.country);
  const destination = formatCityCountry(tracking.destination.city, tracking.destination.country);
  const milestones = sortMilestones(tracking.milestones);
  const lastKnown = tracking.lastKnownPosition;
  const recordedLabel = lastKnown
    ? formatLastKnownRecordedAt(lastKnown.recordedAt)
    : null;
  const freshness = tracking.locationFreshness;
  const freshnessLabel = locationFreshnessLabel(freshness.state);
  const plannedDeliveryLabel = formatPlannedDeliveryLabel(tracking.plannedDeliveryDate);

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screen} testID="shipment-progress-screen">
        {backButton}
        <ScrollView
          contentContainerStyle={styles.scroll}
          testID="shipment-progress-content"
          keyboardShouldPersistTaps="handled"
          accessibilityLabel={`Shipment progress for ${shipmentNumber}. Current status ${statusLabel}. Includes shipment journey overview.`}>
          <Text style={[styles.screenEyebrow, { color: theme.textSecondary }]} testID="shipment-progress-eyebrow">
            Shipment Progress
          </Text>

          {/* 1. Shipment identity */}
          <Text
            style={[styles.title, { color: theme.text }]}
            testID="shipment-progress-number"
            accessibilityRole="header"
            numberOfLines={2}>
            {shipmentNumber}
          </Text>

          {/* 2. Current status — independent of map geometry (C3.10) */}
          <View style={styles.statusBlock}>
            <Text style={[styles.statusCaption, { color: theme.textSecondary }]}>Current status</Text>
            <View
              style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.15)' }]}
              testID="shipment-progress-status"
              accessibilityRole="text"
              accessibilityLabel={
                statusLabel !== 'Unavailable' ? `Status ${statusLabel}` : 'Status unavailable'
              }>
              <Text style={[styles.statusPillText, { color: theme.primary }]} numberOfLines={2}>
                {statusLabel}
              </Text>
            </View>
          </View>

          {/* 3. Route summary (city names — not geometric map data) */}
          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-progress-route"
            accessibilityRole="summary"
            accessibilityLabel={`Route from ${origin} to ${destination}`}>
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Route</Text>
            <Text
              style={[styles.routeOrigin, { color: theme.text }]}
              testID="shipment-progress-origin"
              numberOfLines={3}>
              {origin}
            </Text>
            <Text style={[styles.routeArrow, { color: theme.textSecondary }]} accessibilityElementsHidden>
              ↓
            </Text>
            <Text
              style={[styles.routeDest, { color: theme.text }]}
              testID="shipment-progress-destination"
              numberOfLines={3}>
              {destination}
            </Text>
          </View>

          {/* 4. Journey map — same tracking domain object; no second API lifecycle */}
          <ShipmentTrackingMap tracking={tracking} theme={theme} />

          {/* 5. Planned delivery — customer timing fact (not an arrival estimate) */}
          {plannedDeliveryLabel ? (
            <View
              style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
              testID="shipment-progress-planned-delivery"
              accessible
              accessibilityRole="summary"
              accessibilityLabel={`Planned delivery ${plannedDeliveryLabel}`}>
              <Text style={[styles.plannedDeliveryCaption, { color: theme.textSecondary }]}>
                Planned delivery
              </Text>
              <Text
                style={[styles.plannedDeliveryValue, { color: theme.text }]}
                numberOfLines={2}
                testID="shipment-progress-planned-delivery-value">
                {plannedDeliveryLabel}
              </Text>
            </View>
          ) : null}

          {/* 6. Last-known + backend freshness (no raw coordinates) */}
          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-progress-last-known"
            accessibilityRole="summary"
            accessibilityLabel={
              lastKnown
                ? recordedLabel
                  ? `Last known shipment location. ${freshnessLabel}. Recorded ${recordedLabel}.`
                  : `Last known shipment location. ${freshnessLabel}.`
                : `Last known shipment location. ${freshnessLabel}.`
            }>
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Last known location</Text>
            <Text style={[styles.sectionHint, { color: theme.textSecondary }]}>
              Most recent recorded position for this shipment. This is not a live GPS feed.
            </Text>
            <Text
              style={[styles.freshnessInline, { color: theme.textSecondary }]}
              testID="shipment-progress-last-known-freshness"
              accessibilityLabel={`Location freshness: ${freshness.state}`}
              numberOfLines={3}>
              {freshnessLabel}
            </Text>
            {lastKnown ? (
              recordedLabel ? (
                <Text
                  style={[styles.recordedValue, { color: theme.textSecondary }]}
                  testID="shipment-progress-last-known-recorded"
                  numberOfLines={2}>
                  Recorded {recordedLabel}
                </Text>
              ) : (
                <Text
                  style={[styles.unavailableValue, { color: theme.textSecondary }]}
                  testID="shipment-progress-last-known-present"
                  numberOfLines={3}>
                  Position is available. Update time is unavailable.
                </Text>
              )
            ) : (
              <Text
                style={[styles.unavailableValue, { color: theme.textSecondary }]}
                testID="shipment-progress-last-known-unavailable"
                numberOfLines={3}>
                Location data is not available yet.
              </Text>
            )}
          </View>

          {/* 7. Derived milestones — not status-event history */}
          <View style={styles.milestonesSection}>
            <Text
              style={[styles.sectionTitle, { color: theme.text }]}
              testID="shipment-progress-milestones-title"
              accessibilityRole="header">
              Milestones
            </Text>
            <Text style={[styles.sectionHint, { color: theme.textSecondary }]}>
              Planned and actual shipment dates. This is not a status-event history.
            </Text>

            <View
              testID="shipment-progress-milestones"
              style={styles.milestones}
              accessibilityRole="list"
              accessibilityLabel="Shipment progress milestones">
              {milestones.map((m) => (
                <MilestoneBlock key={m.key} milestone={m} theme={theme} />
              ))}
            </View>
          </View>
        </ScrollView>
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  scroll: { paddingHorizontal: 16, paddingBottom: 48 },
  backRow: { paddingHorizontal: 12, paddingTop: 8, marginBottom: 4 },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  centerPad: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  muted: { fontSize: 13 },
  screenEyebrow: {
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.4,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  title: { fontSize: 24, fontWeight: '700', marginBottom: 14, lineHeight: 30 },
  statusBlock: { marginBottom: 16, gap: 6 },
  statusCaption: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  statusPill: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  statusPillText: { fontSize: 13, fontWeight: '700', flexShrink: 1 },
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
  },
  sectionTitle: { fontSize: 16, fontWeight: '700', marginBottom: 8 },
  sectionHint: { fontSize: 12, lineHeight: 17, marginBottom: 12 },
  plannedDeliveryCaption: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  plannedDeliveryValue: { fontSize: 16, fontWeight: '600', lineHeight: 22 },
  routeOrigin: { fontSize: 15, fontWeight: '600', lineHeight: 22, flexShrink: 1 },
  routeArrow: { fontSize: 14, marginVertical: 4, fontWeight: '600' },
  routeDest: { fontSize: 15, fontWeight: '600', lineHeight: 22, flexShrink: 1 },
  milestonesSection: { marginBottom: 8 },
  milestones: { gap: 12 },
  recordedValue: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  freshnessInline: { fontSize: 13, lineHeight: 18, fontWeight: '600', marginBottom: 8 },
  unavailableValue: { fontSize: 14, lineHeight: 20, fontWeight: '500' },
  milestoneCard: {
    borderWidth: 1.5,
    borderRadius: 14,
    padding: 14,
    gap: 8,
  },
  milestoneTitle: { fontSize: 15, fontWeight: '700', marginBottom: 2 },
  metaRow: { gap: 2 },
  metaLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  metaValue: { fontSize: 14, lineHeight: 20, fontWeight: '500', flexShrink: 1 },
  errorTitle: {
    fontSize: 17,
    fontWeight: '700',
    marginBottom: 8,
    textAlign: 'center',
  },
  errorBody: {
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    marginBottom: 16,
  },
  retryBtn: {
    minHeight: 48,
    minWidth: 140,
    paddingHorizontal: 20,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  retryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },
});
