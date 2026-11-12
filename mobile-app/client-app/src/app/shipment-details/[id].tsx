// @ts-nocheck — lucide icon props vs RN ColorValue
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { Redirect, useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { ChevronLeft, FileText } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  ClientShipmentsApiError,
  formatCityCountry,
  formatPlaceSummary,
  getClientShipment,
  getClientShipmentPodFile,
  getClientShipmentPodStatus,
  getClientShipmentVerificationQr,
} from '@/features/shipments/shipmentsApi';
import {
  customerFacingShipmentStatus,
  shipmentStatusGuidance,
  buildCustomerJourney,
} from '@/features/shipments/shipmentWorkspace';
import { CustomerJourney } from '@/features/shipments/CustomerJourney';
import { ShipmentVerificationQrCard } from '@/features/shipments/ShipmentVerificationQrCard';
import { ProofOfDeliveryCard } from '@/features/shipments/ProofOfDeliveryCard';
import { findAuthorizedConversationForShipment } from '@/features/messaging/shipmentHandoff';
import type { ClientShipmentDetails, ClientVerificationQr } from '@/features/shipments/types';

function DetailRow({
  label,
  value,
  theme,
}: {
  label: string;
  value: string;
  theme: { text: string; textSecondary: string };
}) {
  return (
    <View style={styles.row} accessible>
      <Text style={[styles.rowLabel, { color: theme.textSecondary }]}>{label}</Text>
      <Text style={[styles.rowValue, { color: theme.text }]}>{value}</Text>
    </View>
  );
}

function displayText(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  return trimmed ? trimmed : null;
}

/**
 * C2.9-C3.6 Client Shipment Workspace.
 * GET /api/v1/client/shipments/{id} via getClientShipment only.
 * Distinct from protected /shipment/[id] communication handoff.
 * Messaging uses existing authorized conversations only — never auto-creates.
 */
export default function ShipmentDetailsScreen() {
  const theme = useTheme();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const shipmentId = String(Array.isArray(id) ? id[0] : id || '').trim();
  const { roleOk, identityLinked, ready, isAuthenticated } = useAuth();

  const [shipment, setShipment] = useState<ClientShipmentDetails | null>(null);
  const [loadedForId, setLoadedForId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [conversationState, setConversationState] = useState<
    'idle' | 'loading' | 'ready' | 'none' | 'error'
  >('idle');
  const [podState, setPodState] = useState<'idle' | 'checking' | 'available' | 'unavailable' | 'error'>(
    'idle'
  );
  const [podViewerOpen, setPodViewerOpen] = useState(false);
  const [podLoadingFile, setPodLoadingFile] = useState(false);
  const [podFileError, setPodFileError] = useState<string | null>(null);
  const [podUri, setPodUri] = useState<string | null>(null);
  const [verification, setVerification] = useState<ClientVerificationQr | null>(null);
  const [verificationLoading, setVerificationLoading] = useState(false);
  const [verificationError, setVerificationError] = useState<string | null>(null);

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

  // Route ID change: drop stale shipment immediately — never show previous ID's data.
  useEffect(() => {
    setShipment(null);
    setLoadedForId(null);
    setError(null);
    setErrorStatus(undefined);
    setErrorIsNetwork(false);
    setConversationId(null);
    setConversationState('idle');
    setPodState('idle');
    setPodViewerOpen(false);
    setPodLoadingFile(false);
    setPodFileError(null);
    setPodUri(null);
    setVerification(null);
    setVerificationLoading(false);
    setVerificationError(null);
    setLoading(Boolean(shipmentId));
    inFlightRef.current = false;
    requestSeqRef.current += 1;
  }, [shipmentId]);

  const load = useCallback(async () => {
    if (!roleOk || !identityLinked) {
      if (!mountedRef.current) return;
      setShipment(null);
      setLoadedForId(null);
      setLoading(false);
      return;
    }

    const idToLoad = shipmentIdRef.current;
    if (!idToLoad) {
      if (!mountedRef.current) return;
      setShipment(null);
      setLoadedForId(null);
      setError('Shipment id unavailable.');
      setErrorStatus(undefined);
      setErrorIsNetwork(false);
      setLoading(false);
      return;
    }

    // Prevent uncontrolled duplicate in-flight requests for the same load cycle.
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
    setShipment(null);
    setLoadedForId(null);

    let holdLoadingForSession = false;
    try {
      const result = await getClientShipment(idToLoad);

      if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;
      // Ignore if the route ID changed mid-request.
      if (shipmentIdRef.current !== idToLoad) return;

      setShipment(result);
      setLoadedForId(idToLoad);
      setError(null);
      setErrorStatus(undefined);
      setErrorIsNetwork(false);
    } catch (e) {
      if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;
      if (shipmentIdRef.current !== idToLoad) return;

      const err = e as ClientShipmentsApiError;
      // 401: shared API interceptor + AuthContext — do not reinterpret as empty details.
      if (err.statusCode === 401) {
        holdLoadingForSession = true;
        setShipment(null);
        setLoadedForId(null);
        return;
      }

      setShipment(null);
      setLoadedForId(null);
      setError(err.message || 'Failed to load shipment details');
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

  const loadConversation = useCallback(async (idToLoad: string) => {
    if (!roleOk || !identityLinked || !idToLoad) {
      setConversationId(null);
      setConversationState('idle');
      return;
    }
    setConversationState('loading');
    setConversationId(null);
    try {
      const found = await findAuthorizedConversationForShipment(idToLoad, 'dispatcher_customer');
      if (shipmentIdRef.current !== idToLoad) return;
      if (found?.id) {
        setConversationId(String(found.id));
        setConversationState('ready');
      } else {
        setConversationId(null);
        setConversationState('none');
      }
    } catch {
      if (shipmentIdRef.current !== idToLoad) return;
      setConversationId(null);
      setConversationState('error');
    }
  }, [roleOk, identityLinked]);

  const loadPodStatus = useCallback(async (idToLoad: string) => {
    if (!roleOk || !identityLinked || !idToLoad) {
      setPodState('idle');
      return;
    }
    setPodState('checking');
    setPodUri(null);
    setPodFileError(null);
    try {
      const available = await getClientShipmentPodStatus(idToLoad);
      if (shipmentIdRef.current !== idToLoad) return;
      setPodState(available ? 'available' : 'unavailable');
    } catch (e) {
      if (shipmentIdRef.current !== idToLoad) return;
      const err = e as ClientShipmentsApiError;
      if (err.statusCode === 401) {
        setPodState('idle');
        return;
      }
      if (err.statusCode === 403 || err.statusCode === 404) {
        setPodState('unavailable');
        return;
      }
      setPodState('error');
    }
  }, [roleOk, identityLinked]);

  const loadVerificationQr = useCallback(async (idToLoad: string) => {
    if (!roleOk || !identityLinked || !idToLoad) {
      setVerification(null);
      setVerificationLoading(false);
      setVerificationError(null);
      return;
    }
    setVerificationLoading(true);
    setVerificationError(null);
    try {
      const res = await getClientShipmentVerificationQr(idToLoad);
      if (shipmentIdRef.current !== idToLoad) return;
      setVerification(res);
      setVerificationError(null);
    } catch (e) {
      if (shipmentIdRef.current !== idToLoad) return;
      const err = e as ClientShipmentsApiError;
      if (err.statusCode === 401) {
        setVerification(null);
        setVerificationLoading(false);
        return;
      }
      setVerification(null);
      setVerificationError(err.message || 'Could not load verification code');
    } finally {
      if (shipmentIdRef.current === idToLoad) {
        setVerificationLoading(false);
      }
    }
  }, [roleOk, identityLinked]);

  useFocusEffect(
    useCallback(() => {
      void load();
      if (shipmentId) {
        void loadVerificationQr(shipmentId);
      }
    }, [load, loadVerificationQr, shipmentId])
  );

  useEffect(() => {
    if (!shipment || loadedForId !== shipmentId || !shipmentId) return;
    void loadConversation(shipmentId);
    void loadPodStatus(shipmentId);
    void loadVerificationQr(shipmentId);
  }, [shipment, loadedForId, shipmentId, loadConversation, loadPodStatus, loadVerificationQr]);

  const goBack = () => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    // Fallback when opened without stack history — return to Shipments tab.
    router.replace('/(tabs)/shipments');
  };

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Shipment details"
        testID="shipment-details-forbidden-role"
        icon={FileText}
        showBack
        explanation="Shipment details are only available for FleetX client accounts."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Shipment details"
        testID="shipment-details-unlinked"
        icon={FileText}
        showBack
        explanation="Your account is not linked to a company. Company-scoped shipment details cannot be loaded."
      />
    );
  }

  const backButton = (
    <View style={styles.backRow}>
      <TouchableOpacity
        testID="shipment-details-back"
        style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        onPress={goBack}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel="Go back to shipment list">
        <ChevronLeft size={20} color={theme.text} />
      </TouchableOpacity>
    </View>
  );

  const showLoading =
    loading || (shipmentId && loadedForId !== shipmentId && !error && !shipment);

  if (showLoading) {
    return (
      <GradientBackground>
        {backButton}
        <View style={styles.center} testID="shipment-details-loading" accessibilityLabel="Loading shipment">
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.muted, { color: theme.textSecondary }]}>Loading shipment…</Text>
        </View>
      </GradientBackground>
    );
  }

  if (error && !shipment) {
    const isForbidden = errorStatus === 403;
    const isNotFound = errorStatus === 404;
    const canRetry = !isForbidden && !isNotFound;

    return (
      <GradientBackground>
        {backButton}
        <View
          style={styles.centerPad}
          testID={
            isForbidden
              ? 'shipment-details-forbidden'
              : isNotFound
                ? 'shipment-details-not-found'
                : 'shipment-details-error'
          }
          accessibilityRole="summary">
          <Text style={[styles.errorTitle, { color: theme.text }]}>
            {isForbidden
              ? 'Access unavailable'
              : isNotFound
                ? 'Shipment unavailable'
                : errorIsNetwork
                  ? 'Connection problem'
                  : 'Could not load shipment'}
          </Text>
          <Text style={[styles.errorBody, { color: theme.textSecondary }]}>
            {isForbidden
              ? 'You do not have access to this shipment.'
              : isNotFound
                ? 'This shipment could not be found for your company.'
                : error}
          </Text>
          {canRetry ? (
            <Pressable
              testID="shipment-details-retry"
              style={[styles.retryBtn, { backgroundColor: theme.primary }]}
              onPress={() => void load()}
              accessibilityRole="button"
              accessibilityLabel="Retry loading this shipment">
              <Text style={styles.retryText}>Retry</Text>
            </Pressable>
          ) : null}
        </View>
      </GradientBackground>
    );
  }

  if (!shipment || loadedForId !== shipmentId) {
    return (
      <GradientBackground>
        {backButton}
        <View style={styles.centerPad} testID="shipment-details-error">
          <Text style={[styles.errorTitle, { color: theme.text }]}>Shipment unavailable</Text>
          <Text style={[styles.errorBody, { color: theme.textSecondary }]}>
            Shipment details could not be loaded.
          </Text>
          <Pressable
            testID="shipment-details-retry"
            style={[styles.retryBtn, { backgroundColor: theme.primary }]}
            onPress={() => void load()}
            accessibilityRole="button"
            accessibilityLabel="Retry loading this shipment">
            <Text style={styles.retryText}>Retry</Text>
          </Pressable>
        </View>
      </GradientBackground>
    );
  }

  const statusRaw = displayText(shipment.status?.name);
  const statusLabel = customerFacingShipmentStatus(statusRaw);
  const origin = formatCityCountry(shipment.pickupCity, shipment.pickupCountry);
  const destination = formatCityCountry(shipment.deliveryCity, shipment.deliveryCountry);
  const pickupPlace = formatPlaceSummary(
    shipment.pickupCompany,
    shipment.pickupCity,
    shipment.pickupCountry
  );
  const deliveryPlace = formatPlaceSummary(
    shipment.deliveryCompany,
    shipment.deliveryCity,
    shipment.deliveryCountry
  );
  const priorityRaw = displayText(shipment.priority);
  const priority = priorityRaw
    ? priorityRaw.charAt(0).toUpperCase() + priorityRaw.slice(1).toLowerCase()
    : null;
  const cargoType = displayText(shipment.cargoType);
  const cargoDescription = displayText(shipment.cargoDescription);
  const companyName = displayText(shipment.customerCompany?.companyName);
  const companyCode = displayText(shipment.customerCompany?.companyCode);
  const shipmentNumber = displayText(shipment.shipmentNumber) || 'Shipment';
  const reference = displayText(shipment.referenceNumber);
  const plannedPickup = displayText(shipment.plannedPickupDate);
  const plannedDelivery = displayText(shipment.plannedDeliveryDate);
  const actualPickup = displayText(shipment.actualPickupDate);
  const actualDelivery = displayText(shipment.actualDeliveryDate);
  const createdAt = displayText(shipment.createdAt);
  const updatedAt = displayText(shipment.updatedAt);
  const hasCargo = Boolean(cargoType || cargoDescription || shipment.packageCount != null);
  const hasCompany = Boolean(companyName || companyCode);
  const hasTimestamps = Boolean(createdAt || updatedAt);
  const journey = buildCustomerJourney(statusRaw, {
    plannedPickup,
    actualPickup,
    plannedDelivery,
    actualDelivery,
  });

  const openProgress = () => router.push(`/shipment-tracking/${shipmentId}`);
  const openSupportThread = () => {
    if (!conversationId) return;
    router.push(`/conversation/${conversationId}`);
  };

  const openPod = async () => {
    if (podState !== 'available') return;
    setPodViewerOpen(true);
    if (podUri) return;
    setPodLoadingFile(true);
    setPodFileError(null);
    try {
      const file = await getClientShipmentPodFile(shipmentId);
      if (shipmentIdRef.current !== shipmentId) return;
      setPodUri(file.uri);
    } catch (e) {
      if (shipmentIdRef.current !== shipmentId) return;
      const err = e as ClientShipmentsApiError;
      setPodFileError(err.message || 'Proof of delivery could not be loaded.');
    } finally {
      if (shipmentIdRef.current === shipmentId) {
        setPodLoadingFile(false);
      }
    }
  };

  const downloadPodOnWeb = () => {
    if (Platform.OS !== 'web' || !podUri) return;
    const anchor = document.createElement('a');
    anchor.href = podUri;
    anchor.download = 'proof-of-delivery.jpg';
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
  };

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screen} testID="shipment-details-screen">
        {backButton}
        <ScrollView
          contentContainerStyle={styles.scroll}
          testID="shipment-details-content"
          accessibilityLabel={`Shipment details for ${shipmentNumber}`}>
          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-details-header">
            <Text
              style={[styles.title, { color: theme.text }]}
              testID="shipment-details-number"
              accessibilityRole="header">
              {shipmentNumber}
            </Text>
            {reference ? (
              <Text style={[styles.meta, { color: theme.textSecondary }]}>Reference {reference}</Text>
            ) : null}
            <View style={styles.statusRow}>
              <View
                style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.15)' }]}
                testID="shipment-details-status"
                accessibilityLabel={statusRaw ? `Status ${statusLabel}` : 'Status unavailable'}>
                <Text style={[styles.statusPillText, { color: theme.primary }]}>{statusLabel}</Text>
              </View>
            </View>
            <Text
              style={[styles.guidance, { color: theme.textSecondary }]}
              testID="shipment-details-status-raw">
              {shipmentStatusGuidance(statusRaw)}
            </Text>
            {updatedAt ? (
              <Text style={[styles.meta, { color: theme.textSecondary }]}>Updated {updatedAt}</Text>
            ) : createdAt ? (
              <Text style={[styles.meta, { color: theme.textSecondary }]}>Created {createdAt}</Text>
            ) : null}
          </View>

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-details-lifecycle">
            <CustomerJourney model={journey} theme={theme} />
          </View>

          <ShipmentVerificationQrCard
            verification={verification}
            shipmentNumber={shipment?.shipmentNumber ?? null}
            loading={verificationLoading}
            error={verificationError}
            onRetry={() => void loadVerificationQr(shipmentId)}
            theme={{
              text: theme.text,
              textSecondary: theme.textSecondary,
              background: theme.background,
              card: theme.backgroundElement,
              border: theme.border,
              tint: theme.primary,
            }}
          />

          <ProofOfDeliveryCard shipmentId={shipmentId} />

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-details-actions">
            <Text style={[styles.sectionTitle, { color: theme.text }]}>What you can do</Text>
            <Pressable
              testID="shipment-details-progress"
              style={[styles.progressBtn, { backgroundColor: theme.primary }]}
              onPress={openProgress}
              accessibilityRole="button"
              accessibilityLabel="Open shipment progress and milestones">
              <Text style={styles.progressBtnText}>Shipment progress</Text>
            </Pressable>
            {conversationState === 'loading' || conversationState === 'idle' ? (
              <View style={styles.messageLoading} testID="shipment-details-message-loading">
                <ActivityIndicator color={theme.primary} />
                <Text style={[styles.muted, { color: theme.textSecondary }]}>Checking messages…</Text>
              </View>
            ) : conversationState === 'ready' && conversationId ? (
              <Pressable
                testID="shipment-details-contact-support"
                style={[styles.outlineBtn, { borderColor: theme.border }]}
                onPress={openSupportThread}
                accessibilityRole="button"
                accessibilityLabel="Contact FleetX Support"
                accessibilityHint="Opens your authorized support conversation for this shipment">
                <Text style={[styles.outlineBtnText, { color: theme.text }]}>Contact FleetX Support</Text>
              </Pressable>
            ) : conversationState === 'error' ? (
              <View testID="shipment-details-message-error">
                <Text style={[styles.absentHint, { color: theme.textSecondary }]}>
                  Messaging could not be loaded.
                </Text>
                <Pressable
                  testID="shipment-details-message-retry"
                  onPress={() => void loadConversation(shipmentId)}
                  accessibilityRole="button"
                  accessibilityLabel="Retry messaging">
                  <Text style={[styles.linkText, { color: theme.primary }]}>Retry</Text>
                </Pressable>
              </View>
            ) : (
              <Text
                style={[styles.absentHint, { color: theme.textSecondary }]}
                testID="shipment-details-message-unavailable">
                Messaging is not available yet for this shipment. FleetX Support must open the conversation first.
              </Text>
            )}
            {podState === 'checking' || podState === 'idle' ? (
              <View style={styles.messageLoading} testID="shipment-details-pod-loading">
                <ActivityIndicator color={theme.primary} />
                <Text style={[styles.muted, { color: theme.textSecondary }]}>
                  Checking proof of delivery…
                </Text>
              </View>
            ) : podState === 'available' ? (
              <Pressable
                testID="shipment-details-pod"
                style={[styles.outlineBtn, { borderColor: theme.border }]}
                onPress={() => void openPod()}
                accessibilityRole="button"
                accessibilityLabel="View proof of delivery">
                <Text style={[styles.outlineBtnText, { color: theme.text }]}>Proof of Delivery</Text>
              </Pressable>
            ) : podState === 'error' ? (
              <View testID="shipment-details-pod-error">
                <Text style={[styles.absentHint, { color: theme.textSecondary }]}>
                  Proof of delivery could not be checked.
                </Text>
                <Pressable
                  testID="shipment-details-pod-retry"
                  onPress={() => void loadPodStatus(shipmentId)}
                  accessibilityRole="button"
                  accessibilityLabel="Retry proof of delivery">
                  <Text style={[styles.linkText, { color: theme.primary }]}>Retry</Text>
                </Pressable>
              </View>
            ) : (
              <Text
                style={[styles.absentHint, { color: theme.textSecondary }]}
                testID="shipment-details-pod-unavailable">
                Proof of delivery is not available yet.
              </Text>
            )}
          </View>

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-details-route"
            accessibilityLabel={`Route from ${origin} to ${destination}`}>
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Pickup</Text>
            <DetailRow label="Location" value={pickupPlace} theme={theme} />
            <Text style={[styles.journeyDivider, { color: theme.textSecondary }]}>↓</Text>
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Delivery</Text>
            <DetailRow label="Location" value={deliveryPlace} theme={theme} />
            <View testID="shipment-details-dates" style={styles.datesAnchor}>
              <Text style={[styles.groupLabel, { color: theme.textSecondary }]}>Planned</Text>
              {plannedPickup ? (
                <DetailRow label="Pickup" value={plannedPickup} theme={theme} />
              ) : (
                <Text style={[styles.absentHint, { color: theme.textSecondary }]}>
                  Planned pickup not provided
                </Text>
              )}
              {plannedDelivery ? (
                <DetailRow label="Delivery" value={plannedDelivery} theme={theme} />
              ) : (
                <Text style={[styles.absentHint, { color: theme.textSecondary }]}>
                  Planned delivery not provided
                </Text>
              )}
              {actualPickup || actualDelivery ? (
                <>
                  <Text style={[styles.groupLabel, { color: theme.textSecondary }]}>Actual</Text>
                  {actualPickup ? (
                    <DetailRow label="Pickup" value={actualPickup} theme={theme} />
                  ) : null}
                  {actualDelivery ? (
                    <DetailRow label="Delivery" value={actualDelivery} theme={theme} />
                  ) : null}
                </>
              ) : null}
            </View>
          </View>

          {priority ? (
            <View
              style={[
                styles.card,
                { backgroundColor: theme.backgroundElement, borderColor: theme.border },
              ]}
              testID="shipment-details-priority">
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Priority</Text>
              <DetailRow label="Priority" value={priority} theme={theme} />
            </View>
          ) : null}

          {hasCargo ? (
            <View
              style={[
                styles.card,
                { backgroundColor: theme.backgroundElement, borderColor: theme.border },
              ]}
              testID="shipment-details-cargo">
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Cargo</Text>
              {cargoType ? <DetailRow label="Type" value={cargoType} theme={theme} /> : null}
              {cargoDescription ? (
                <DetailRow label="Description" value={cargoDescription} theme={theme} />
              ) : null}
              {shipment.packageCount != null ? (
                <DetailRow label="Packages" value={String(shipment.packageCount)} theme={theme} />
              ) : null}
            </View>
          ) : null}

          {hasCompany ? (
            <View
              style={[
                styles.card,
                { backgroundColor: theme.backgroundElement, borderColor: theme.border },
              ]}
              testID="shipment-details-company">
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Company</Text>
              {companyName ? <DetailRow label="Name" value={companyName} theme={theme} /> : null}
              {companyCode ? <DetailRow label="Code" value={companyCode} theme={theme} /> : null}
            </View>
          ) : null}

          {hasTimestamps ? (
            <View
              style={[
                styles.card,
                { backgroundColor: theme.backgroundElement, borderColor: theme.border },
              ]}
              testID="shipment-details-timestamps">
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Record</Text>
              {createdAt ? <DetailRow label="Created" value={createdAt} theme={theme} /> : null}
              {updatedAt ? <DetailRow label="Updated" value={updatedAt} theme={theme} /> : null}
            </View>
          ) : null}
        </ScrollView>
        <Modal
          visible={podViewerOpen}
          animationType="fade"
          transparent
          onRequestClose={() => setPodViewerOpen(false)}>
          <View style={styles.podOverlay} testID="shipment-details-pod-viewer">
            <View
              style={[
                styles.podCard,
                { backgroundColor: theme.backgroundElement, borderColor: theme.border },
              ]}>
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Proof of Delivery</Text>
              {podLoadingFile ? (
                <View style={styles.center} testID="shipment-details-pod-file-loading">
                  <ActivityIndicator color={theme.primary} />
                  <Text style={[styles.muted, { color: theme.textSecondary }]}>Loading proof…</Text>
                </View>
              ) : podUri ? (
                <Image
                  testID="shipment-details-pod-image"
                  source={{ uri: podUri }}
                  style={styles.podImage}
                  resizeMode="contain"
                  accessibilityLabel="Proof of delivery photo"
                />
              ) : (
                <Text
                  style={[styles.absentHint, { color: theme.textSecondary }]}
                  testID="shipment-details-pod-file-error">
                  {podFileError || 'Proof of delivery could not be loaded.'}
                </Text>
              )}
              {Platform.OS === 'web' && podUri ? (
                <Pressable
                  testID="shipment-details-pod-download"
                  style={[styles.progressBtn, { backgroundColor: theme.primary }]}
                  onPress={downloadPodOnWeb}
                  accessibilityRole="button"
                  accessibilityLabel="Download proof of delivery">
                  <Text style={styles.progressBtnText}>Download</Text>
                </Pressable>
              ) : null}
              <Pressable
                testID="shipment-details-pod-close"
                style={[styles.outlineBtn, { borderColor: theme.border }]}
                onPress={() => setPodViewerOpen(false)}
                accessibilityRole="button"
                accessibilityLabel="Close proof of delivery">
                <Text style={[styles.outlineBtnText, { color: theme.text }]}>Close</Text>
              </Pressable>
            </View>
          </View>
        </Modal>
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  scroll: { paddingBottom: 32, gap: 12 },
  backRow: { marginBottom: 8 },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  centerPad: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  muted: { fontSize: 13 },
  title: { fontSize: 22, fontWeight: '700', marginBottom: 8 },
  meta: { fontSize: 12, marginTop: 4 },
  guidance: { fontSize: 13, lineHeight: 18, marginTop: 8 },
  statusRow: { flexDirection: 'row', marginTop: 8 },
  statusPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  statusPillText: { fontSize: 11, fontWeight: '700' },
  progressBtn: {
    minHeight: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
  },
  progressBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },
  outlineBtn: {
    minHeight: 48,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
  },
  outlineBtnText: { fontWeight: '700', fontSize: 14 },
  messageLoading: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  linkText: { fontSize: 13, fontWeight: '700', marginTop: 8 },
  journeyDivider: {
    textAlign: 'center',
    fontSize: 16,
    marginVertical: 8,
  },
  datesAnchor: { marginTop: 8, gap: 8 },
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    gap: 8,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700', marginBottom: 4 },
  groupLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
    marginTop: 4,
  },
  absentHint: {
    fontSize: 12,
    fontStyle: 'italic',
    lineHeight: 16,
  },
  row: { gap: 2 },
  rowLabel: { fontSize: 12, fontWeight: '600' },
  rowValue: { fontSize: 14, lineHeight: 20 },
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
  podOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.55)',
    justifyContent: 'center',
    padding: 20,
  },
  podCard: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    gap: 12,
    maxHeight: '88%',
  },
  podImage: {
    width: '100%',
    height: 320,
    borderRadius: 12,
    backgroundColor: 'rgba(0, 0, 0, 0.2)',
  },
});
