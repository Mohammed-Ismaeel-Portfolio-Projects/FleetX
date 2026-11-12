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
import { ChevronLeft, FileText } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  ClientShipmentRequestsApiError,
  getClientShipmentRequest,
} from '@/features/requests/requestsApi';
import { customerFacingShipmentStatus } from '@/features/shipments/shipmentWorkspace';
import {
  canResubmitRequest,
  canSubmitRequest,
  formatRequestStatusLabel,
  isChangesRequestedStatus,
  isConvertedStatus,
  isDraftStatus,
  isEditableRequestStatus,
  isRejectedStatus,
  linkedShipmentId,
  requestStatusGuidance,
} from '@/features/requests/requestStatus';
import type { ClientShipmentRequestDetails } from '@/features/requests/types';
import { hasCoordinatePair } from '@/features/requests/coordinates';

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

function displayText(value: string | number | boolean | null | undefined): string | null {
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (value == null) return null;
  const trimmed = String(value).trim();
  return trimmed ? trimmed : null;
}

/**
 * C3.4 / C3.5 Request Detail — GET /api/v1/client/shipment-requests/{id}.
 * Converted requests observe linked_shipment and open the existing Shipment Workspace.
 * The Client App does not approve, convert, or create shipments.
 */
export default function RequestDetailsScreen() {
  const theme = useTheme();
  const router = useRouter();
  const params = useLocalSearchParams<{ id: string; submitted?: string; resubmitted?: string }>();
  const requestId = String(Array.isArray(params.id) ? params.id[0] : params.id || '').trim();
  const justSubmitted = String(params.submitted || '') === '1';
  const justResubmitted = String(params.resubmitted || '') === '1';
  const { roleOk, identityLinked, ready, isAuthenticated } = useAuth();

  const [request, setRequest] = useState<ClientShipmentRequestDetails | null>(null);
  const [loadedForId, setLoadedForId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);

  const inFlightRef = useRef(false);
  const requestSeqRef = useRef(0);
  const mountedRef = useRef(true);
  const requestIdRef = useRef(requestId);
  requestIdRef.current = requestId;

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    setRequest(null);
    setLoadedForId(null);
    setError(null);
    setErrorStatus(undefined);
    setErrorIsNetwork(false);
    setLoading(Boolean(requestId));
    inFlightRef.current = false;
    requestSeqRef.current += 1;
  }, [requestId]);

  const load = useCallback(async () => {
    if (!roleOk || !identityLinked) {
      if (!mountedRef.current) return;
      setRequest(null);
      setLoadedForId(null);
      setLoading(false);
      return;
    }

    const idToLoad = requestIdRef.current;
    if (!idToLoad) {
      setError('Request id unavailable.');
      setLoading(false);
      return;
    }

    if (inFlightRef.current) return;
    inFlightRef.current = true;
    const requestSeq = ++requestSeqRef.current;
    setLoading(true);
    setError(null);
    setRequest(null);
    setLoadedForId(null);

    let holdLoadingForSession = false;
    try {
      const result = await getClientShipmentRequest(idToLoad);
      if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;
      if (requestIdRef.current !== idToLoad) return;
      setRequest(result);
      setLoadedForId(idToLoad);
    } catch (e) {
      if (!mountedRef.current || requestSeq !== requestSeqRef.current) return;
      const err = e as ClientShipmentRequestsApiError;
      if (err.statusCode === 401) {
        holdLoadingForSession = true;
        setRequest(null);
        return;
      }
      setRequest(null);
      setLoadedForId(null);
      setError(err.message || 'Failed to load shipment request');
      setErrorStatus(err.statusCode);
      setErrorIsNetwork(Boolean(err.isNetwork));
    } finally {
      if (requestSeq === requestSeqRef.current) {
        inFlightRef.current = false;
        if (mountedRef.current) setLoading(holdLoadingForSession ? true : false);
      }
    }
  }, [roleOk, identityLinked]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load, requestId])
  );

  const goBack = () => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    router.replace('/(tabs)/requests');
  };

  const openEdit = () => {
    if (!requestId) return;
    router.push(`/request-form?id=${encodeURIComponent(requestId)}`);
  };

  const openReview = () => {
    if (!requestId) return;
    router.push(`/request-form?id=${encodeURIComponent(requestId)}&mode=review`);
  };

  const openLinkedShipment = (shipmentId: string) => {
    const id = String(shipmentId || '').trim();
    if (!id) return;
    router.push(`/shipment-details/${id}`);
  };

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Request"
        testID="request-details-forbidden-role"
        icon={FileText}
        showBack
        explanation="Shipment requests are only available for FleetX client accounts."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Request"
        testID="request-details-unlinked"
        icon={FileText}
        showBack
        explanation="Your account is not linked to a company. Company-scoped shipment requests cannot be loaded."
      />
    );
  }

  const backButton = (
    <View style={styles.backRow}>
      <TouchableOpacity
        testID="request-details-back"
        style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        onPress={goBack}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel="Go back to requests">
        <ChevronLeft size={20} color={theme.text} />
      </TouchableOpacity>
    </View>
  );

  const showLoading = loading || (requestId && loadedForId !== requestId && !error && !request);

  if (showLoading) {
    return (
      <GradientBackground>
        {backButton}
        <View style={styles.center} testID="request-details-loading">
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.muted, { color: theme.textSecondary }]}>Loading request…</Text>
        </View>
      </GradientBackground>
    );
  }

  if (error && !request) {
    const isForbidden = errorStatus === 403;
    const isNotFound = errorStatus === 404;
    return (
      <GradientBackground>
        {backButton}
        <View
          style={styles.centerPad}
          testID={
            isForbidden
              ? 'request-details-forbidden'
              : isNotFound
                ? 'request-details-not-found'
                : 'request-details-error'
          }>
          <Text style={[styles.errorTitle, { color: theme.text }]}>
            {isForbidden
              ? 'Access unavailable'
              : isNotFound
                ? 'Request unavailable'
                : errorIsNetwork
                  ? 'Connection problem'
                  : 'Could not load request'}
          </Text>
          <Text style={[styles.errorBody, { color: theme.textSecondary }]}>{error}</Text>
          {!isForbidden && !isNotFound ? (
            <Pressable
              testID="request-details-retry"
              style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
              onPress={() => void load()}
              accessibilityRole="button"
              accessibilityLabel="Retry loading this request">
              <Text style={styles.primaryBtnText}>Retry</Text>
            </Pressable>
          ) : null}
        </View>
      </GradientBackground>
    );
  }

  if (!request) {
    return (
      <GradientBackground>
        {backButton}
        <View style={styles.center} testID="request-details-missing">
          <Text style={[styles.errorTitle, { color: theme.text }]}>Request unavailable</Text>
        </View>
      </GradientBackground>
    );
  }

  const statusRaw = request.status?.name ?? null;
  const converted = isConvertedStatus(statusRaw);
  const shipmentId = linkedShipmentId(request.linkedShipment);
  const shipmentNumber = displayText(request.linkedShipment?.shipmentNumber);
  const shipmentStatusLabel = request.linkedShipment?.status?.name
    ? customerFacingShipmentStatus(request.linkedShipment.status.name)
    : null;
  const number = (request.requestNumber && String(request.requestNumber).trim()) || 'Request';
  const created = displayText(request.createdAt);
  const updated = displayText(request.updatedAt);
  const pickupPlace = [request.pickupCompany, request.pickupCity, request.pickupCountry]
    .filter((p) => p && String(p).trim())
    .join(', ');
  const deliveryPlace = [request.deliveryCompany, request.deliveryCity, request.deliveryCountry]
    .filter((p) => p && String(p).trim())
    .join(', ');

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screen} testID="request-details">
        {backButton}
        <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
          {justSubmitted || justResubmitted ? (
            <View
              testID="request-details-success"
              style={[styles.successCard, { borderColor: 'rgba(34, 197, 94, 0.45)' }]}>
              <Text style={styles.successTitle}>
                {justResubmitted ? 'Request resubmitted successfully' : 'Request submitted successfully'}
              </Text>
              <Text style={[styles.successBody, { color: theme.textSecondary }]}>
                FleetX has received your request and it is now awaiting review. This is not an
                approval and not a shipment.
              </Text>
            </View>
          ) : null}

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="request-details-header">
            <Text style={[styles.number, { color: theme.text }]}>{number}</Text>
            <View style={[styles.statusPill, { backgroundColor: 'rgba(30, 117, 255, 0.15)' }]}>
              <Text style={[styles.statusPillText, { color: theme.primary }]}>
                {formatRequestStatusLabel(statusRaw)}
              </Text>
            </View>
            {created ? (
              <Text style={[styles.meta, { color: theme.textSecondary }]}>Created {created}</Text>
            ) : null}
            <Text style={[styles.guidance, { color: theme.textSecondary }]} testID="request-details-guidance">
              {requestStatusGuidance(statusRaw)}
            </Text>
          </View>

          {isChangesRequestedStatus(statusRaw) && request.reviewFeedback?.message ? (
            <View
              testID="request-details-review-feedback"
              style={[styles.feedbackCard, styles.feedbackChanges]}>
              <Text style={styles.feedbackTitle}>FleetX requested changes</Text>
              <Text style={[styles.feedbackHint, { color: theme.textSecondary }]}>
                Please review the following feedback, update the request, then resubmit.
              </Text>
              <Text style={[styles.feedbackMessage, { color: theme.text }]} testID="request-details-review-feedback-message">
                {request.reviewFeedback.message}
              </Text>
            </View>
          ) : null}

          {isRejectedStatus(statusRaw) && request.reviewFeedback?.message ? (
            <View
              testID="request-details-rejection-feedback"
              style={[styles.feedbackCard, styles.feedbackRejected]}>
              <Text style={styles.feedbackRejectedTitle}>FleetX could not approve this request</Text>
              <Text style={[styles.feedbackHint, { color: theme.textSecondary }]}>
                Why this request was not approved:
              </Text>
              <Text
                style={[styles.feedbackMessage, { color: theme.text }]}
                testID="request-details-rejection-feedback-message">
                {request.reviewFeedback.message}
              </Text>
            </View>
          ) : null}

          {converted ? (
            <View
              style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
              testID="request-details-converted">
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Shipment</Text>
              <Text style={[styles.guidance, { color: theme.textSecondary }]}>
                This request is now a shipment. Follow it in the shipment workspace.
              </Text>
              {shipmentNumber ? (
                <DetailRow label="Shipment" value={shipmentNumber} theme={theme} />
              ) : null}
              {shipmentStatusLabel ? (
                <DetailRow label="Shipment status" value={shipmentStatusLabel} theme={theme} />
              ) : null}
              {updated ? (
                <DetailRow label="Last update" value={updated} theme={theme} />
              ) : created ? (
                <DetailRow label="Created" value={created} theme={theme} />
              ) : null}
              {shipmentId ? (
                <Pressable
                  testID="request-details-open-shipment"
                  style={[styles.primaryBtn, { backgroundColor: theme.primary, marginTop: 8 }]}
                  onPress={() => openLinkedShipment(shipmentId)}
                  accessibilityRole="button"
                  accessibilityLabel="Open shipment">
                  <Text style={styles.primaryBtnText}>Open Shipment</Text>
                </Pressable>
              ) : (
                <Text
                  style={[styles.muted, { color: theme.textSecondary, marginTop: 8 }]}
                  testID="request-details-missing-shipment">
                  Shipment created, but shipment details are not currently available.
                </Text>
              )}
            </View>
          ) : null}

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="request-details-pickup">
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Pickup</Text>
            {pickupPlace ? <DetailRow label="Location" value={pickupPlace} theme={theme} /> : null}
            {displayText(request.pickupAddress) ? (
              <DetailRow label="Address" value={displayText(request.pickupAddress)} theme={theme} />
            ) : null}
            {displayText(request.pickupContact) ? (
              <DetailRow label="Contact" value={displayText(request.pickupContact)} theme={theme} />
            ) : null}
            {displayText(request.pickupPhone) ? (
              <DetailRow label="Phone" value={displayText(request.pickupPhone)} theme={theme} />
            ) : null}
            {hasCoordinatePair(request.pickupLatitude, request.pickupLongitude) ? (
              <DetailRow label="Map point" value="Selected" theme={theme} />
            ) : null}
            {!pickupPlace && !request.pickupAddress ? (
              <Text style={[styles.muted, { color: theme.textSecondary }]}>Not provided yet</Text>
            ) : null}
          </View>

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="request-details-delivery">
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Delivery</Text>
            {deliveryPlace ? <DetailRow label="Location" value={deliveryPlace} theme={theme} /> : null}
            {displayText(request.deliveryAddress) ? (
              <DetailRow label="Address" value={displayText(request.deliveryAddress)} theme={theme} />
            ) : null}
            {displayText(request.deliveryContact) ? (
              <DetailRow label="Contact" value={displayText(request.deliveryContact)} theme={theme} />
            ) : null}
            {displayText(request.deliveryPhone) ? (
              <DetailRow label="Phone" value={displayText(request.deliveryPhone)} theme={theme} />
            ) : null}
            {hasCoordinatePair(request.deliveryLatitude, request.deliveryLongitude) ? (
              <DetailRow label="Map point" value="Selected" theme={theme} />
            ) : null}
            {!deliveryPlace && !request.deliveryAddress ? (
              <Text style={[styles.muted, { color: theme.textSecondary }]}>Not provided yet</Text>
            ) : null}
          </View>

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="request-details-cargo">
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Cargo</Text>
            {displayText(request.cargoType) ? (
              <DetailRow label="Type" value={displayText(request.cargoType)} theme={theme} />
            ) : null}
            {displayText(request.cargoDescription) ? (
              <DetailRow label="Description" value={displayText(request.cargoDescription)} theme={theme} />
            ) : null}
            {request.packageCount != null ? (
              <DetailRow label="Packages" value={String(request.packageCount)} theme={theme} />
            ) : null}
            {request.cargoWeight != null ? (
              <DetailRow label="Weight" value={String(request.cargoWeight)} theme={theme} />
            ) : null}
            {request.cargoVolume != null ? (
              <DetailRow label="Volume" value={String(request.cargoVolume)} theme={theme} />
            ) : null}
            <DetailRow label="Hazardous" value={request.hazardousCargo ? 'Yes' : 'No'} theme={theme} />
            <DetailRow
              label="Temperature controlled"
              value={request.temperatureControlled ? 'Yes' : 'No'}
              theme={theme}
            />
          </View>

          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="request-details-schedule">
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Requested schedule</Text>
            {displayText(request.requestedPickupDate) ? (
              <DetailRow
                label="Pickup date"
                value={displayText(request.requestedPickupDate)}
                theme={theme}
              />
            ) : (
              <Text style={[styles.muted, { color: theme.textSecondary }]}>No pickup date requested</Text>
            )}
            {displayText(request.requestedDeliveryDate) ? (
              <DetailRow
                label="Delivery date"
                value={displayText(request.requestedDeliveryDate)}
                theme={theme}
              />
            ) : null}
          </View>

          {displayText(request.specialInstructions) || displayText(request.clientNotes) ? (
            <View
              style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
              testID="request-details-notes">
              <Text style={[styles.sectionTitle, { color: theme.text }]}>Notes</Text>
              {displayText(request.specialInstructions) ? (
                <DetailRow
                  label="Special instructions"
                  value={displayText(request.specialInstructions)}
                  theme={theme}
                />
              ) : null}
              {displayText(request.clientNotes) ? (
                <DetailRow label="Your notes" value={displayText(request.clientNotes)} theme={theme} />
              ) : null}
            </View>
          ) : null}

          {updated ? (
            <Text style={[styles.footerMeta, { color: theme.textSecondary }]} testID="request-details-updated">
              Last updated {updated}
            </Text>
          ) : null}

          {isDraftStatus(statusRaw) ? (
            <Pressable
              testID="request-details-continue"
              style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
              onPress={openEdit}
              accessibilityRole="button"
              accessibilityLabel="Continue editing draft">
              <Text style={styles.primaryBtnText}>Continue Editing</Text>
            </Pressable>
          ) : null}

          {isChangesRequestedStatus(statusRaw) ? (
            <Pressable
              testID="request-details-edit-resubmit"
              style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
              onPress={openEdit}
              accessibilityRole="button"
              accessibilityLabel="Edit and resubmit this request">
              <Text style={styles.primaryBtnText}>Edit and Resubmit</Text>
            </Pressable>
          ) : null}

          {canSubmitRequest(statusRaw) || canResubmitRequest(statusRaw) ? (
            <Pressable
              testID="request-details-review"
              style={[styles.outlineBtn, { borderColor: theme.border }]}
              onPress={openReview}
              accessibilityRole="button"
              accessibilityLabel="Review request">
              <Text style={[styles.outlineBtnText, { color: theme.text }]}>Review</Text>
            </Pressable>
          ) : null}

          {isEditableRequestStatus(statusRaw) || converted ? null : (
            <Text style={[styles.muted, { color: theme.textSecondary, textAlign: 'center' }]}>
              {statusRaw === 'submitted' || statusRaw === 'under_review'
                ? 'Awaiting FleetX review'
                : statusRaw === 'approved'
                  ? 'Approved — FleetX will create a shipment after conversion'
                  : null}
            </Text>
          )}
        </ScrollView>
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
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  centerPad: { paddingVertical: 28, alignItems: 'center' },
  muted: { fontSize: 13, lineHeight: 18 },
  errorTitle: { fontSize: 16, fontWeight: '700', marginBottom: 8, textAlign: 'center' },
  errorBody: { fontSize: 13, lineHeight: 18, textAlign: 'center', marginBottom: 16 },
  successCard: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 14,
    backgroundColor: 'rgba(34, 197, 94, 0.12)',
  },
  successTitle: { color: '#4ADE80', fontSize: 15, fontWeight: '700', marginBottom: 6 },
  successBody: { fontSize: 13, lineHeight: 18 },
  feedbackCard: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 14,
  },
  feedbackChanges: {
    borderColor: 'rgba(245, 158, 11, 0.5)',
    backgroundColor: 'rgba(245, 158, 11, 0.12)',
  },
  feedbackRejected: {
    borderColor: 'rgba(239, 68, 68, 0.45)',
    backgroundColor: 'rgba(239, 68, 68, 0.1)',
  },
  feedbackTitle: { color: '#FBBF24', fontSize: 15, fontWeight: '700', marginBottom: 6 },
  feedbackRejectedTitle: { color: '#F87171', fontSize: 15, fontWeight: '700', marginBottom: 6 },
  feedbackHint: { fontSize: 13, lineHeight: 18, marginBottom: 8 },
  feedbackMessage: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  card: { borderWidth: 1.5, borderRadius: 16, padding: 16 },
  number: { fontSize: 20, fontWeight: '700', marginBottom: 8 },
  statusPill: {
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    marginBottom: 8,
  },
  statusPillText: { fontSize: 12, fontWeight: '700' },
  meta: { fontSize: 12, marginBottom: 8 },
  guidance: { fontSize: 13, lineHeight: 18 },
  sectionTitle: { fontSize: 15, fontWeight: '700', marginBottom: 10 },
  row: { marginBottom: 8 },
  rowLabel: { fontSize: 12, fontWeight: '600', marginBottom: 2 },
  rowValue: { fontSize: 14, lineHeight: 20 },
  footerMeta: { fontSize: 12, marginTop: 4 },
  primaryBtn: {
    minHeight: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  primaryBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },
  outlineBtn: {
    minHeight: 48,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  outlineBtnText: { fontWeight: '700', fontSize: 14 },
});
