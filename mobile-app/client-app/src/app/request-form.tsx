// @ts-nocheck — lucide icon props vs RN ColorValue
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Redirect, useLocalSearchParams, useRouter } from 'expo-router';
import { ChevronLeft, MapPin, Package } from 'lucide-react-native';

import { Checkbox, GradientBackground, InputField } from '@/components/product';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  ClientShipmentRequestsApiError,
  createClientShipmentRequest,
  getClientShipmentRequest,
  resubmitClientShipmentRequest,
  submitClientShipmentRequest,
  updateClientShipmentRequest,
} from '@/features/requests/requestsApi';
import {
  canResubmitRequest,
  canSubmitRequest,
  formatRequestStatusLabel,
  isChangesRequestedStatus,
  isEditableRequestStatus,
} from '@/features/requests/requestStatus';
import {
  emptyRequestForm,
  formFromDetails,
  formToWritePayload,
  mapApiFieldErrors,
  splitApiFieldErrors,
  validateFormDraft,
  validateFormForSubmit,
  type RequestFormField,
  type RequestFormValues,
} from '@/features/requests/requestForm';
import type { ClientReviewFeedback } from '@/features/requests/types';
import { formatRequestDateLabel } from '@/features/requests/requestDates';
import { RequestDateField } from '@/features/requests/RequestDateField';
import { RequestLocationSummary } from '@/features/requests/RequestLocationSummary';
import { RequestStepIndicator } from '@/features/requests/RequestStepIndicator';
import { ClientLocationPicker, type ClientLocationSelection } from '@/features/requests/ClientLocationPicker';
import { ClientRoutePreview } from '@/features/requests/ClientRoutePreview';
import { asCoordinate, hasCoordinatePair } from '@/features/requests/coordinates';
import {
  CARGO_TYPE_OPTIONS,
  TRAILER_TYPE_OPTIONS,
  cargoTypeLabel,
  trailerTypeLabel,
} from '@/features/requests/shipmentRequestEnums';
import {
  firstFieldError,
  firstInvalidStep,
  type RequestEditStep,
} from '@/features/requests/requestWizard';
import {
  validateEditStep,
  validateEditStepsThrough,
} from '@/features/requests/requestStepValidation';
import { RequestPricingEstimateCard } from '@/features/requests/RequestPricingEstimateCard';
import { useRequestPricingEstimate } from '@/features/requests/useRequestPricingEstimate';

function Section({
  title,
  hint,
  children,
  theme,
  onEdit,
  editTestID,
  editDisabled,
}: {
  title: string;
  hint?: string;
  children: ReactNode;
  theme: { text: string; textSecondary: string; backgroundElement: string; border: string; primary: string };
  onEdit?: () => void;
  editTestID?: string;
  editDisabled?: boolean;
}) {
  return (
    <View style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
      <View style={styles.sectionHeader}>
        <Text style={[styles.sectionTitle, { color: theme.text, flex: 1 }]}>{title}</Text>
        {onEdit ? (
          <Pressable
            testID={editTestID}
            onPress={onEdit}
            disabled={editDisabled}
            accessibilityRole="button"
            accessibilityLabel={`Edit ${title}`}
            hitSlop={8}
            style={styles.sectionEditBtn}>
            <Text style={[styles.sectionEditText, { color: theme.primary, opacity: editDisabled ? 0.5 : 1 }]}>
              Edit
            </Text>
          </Pressable>
        ) : null}
      </View>
      {hint ? <Text style={[styles.sectionHint, { color: theme.textSecondary }]}>{hint}</Text> : null}
      {children}
    </View>
  );
}

function ReviewRow({
  label,
  value,
  theme,
}: {
  label: string;
  value: string;
  theme: { text: string; textSecondary: string };
}) {
  return (
    <View style={styles.reviewRow}>
      <Text style={[styles.reviewLabel, { color: theme.textSecondary }]}>{label}</Text>
      <Text style={[styles.reviewValue, { color: theme.text }]}>{value}</Text>
    </View>
  );
}

function display(value: string, fallback = 'Not provided'): string {
  const trimmed = value.trim();
  return trimmed ? trimmed : fallback;
}

function cargoReviewLine(values: RequestFormValues): string {
  const parts: string[] = [];
  if (values.packageCount.trim()) parts.push(`${values.packageCount.trim()} packages`);
  if (values.cargoWeight.trim()) parts.push(`${values.cargoWeight.trim()} weight`);
  if (values.cargoVolume.trim()) parts.push(`${values.cargoVolume.trim()} volume`);
  return parts.join(' · ');
}

/**
 * C3.4 create / edit draft / review / submit / resubmit.
 * POST create, PATCH update, POST submit, POST resubmit — client endpoints only.
 */
export default function RequestFormScreen() {
  const theme = useTheme();
  const router = useRouter();
  const params = useLocalSearchParams<{ id?: string; mode?: string }>();
  const initialId = String(Array.isArray(params.id) ? params.id[0] : params.id || '').trim();
  const startReview = String(params.mode || '') === 'review';
  const { roleOk, identityLinked, ready, isAuthenticated } = useAuth();

  const [requestId, setRequestId] = useState<string | null>(initialId || null);
  const [statusName, setStatusName] = useState<string | null>(initialId ? null : 'draft');
  const [requestNumber, setRequestNumber] = useState<string | null>(null);
  const [reviewFeedback, setReviewFeedback] = useState<ClientReviewFeedback | null>(null);
  const [mode, setMode] = useState<'edit' | 'review'>(startReview && initialId ? 'review' : 'edit');
  const [editStep, setEditStep] = useState<RequestEditStep>(1);
  const [values, setValues] = useState<RequestFormValues>(emptyRequestForm());
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<RequestFormField, string>>>({});
  const [loading, setLoading] = useState(Boolean(initialId));
  const [saving, setSaving] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [banner, setBanner] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pickerStop, setPickerStop] = useState<'pickup' | 'delivery' | null>(null);
  /** Android MapView must not mount until Modal onShow — otherwise blank/non-interactive surface. */
  const [pickerSurfaceReady, setPickerSurfaceReady] = useState(false);
  /** When true, edit mode shows “Back to review” after section Edit from Review (05). */
  const [resumeReviewAfterEdit, setResumeReviewAfterEdit] = useState(false);

  const saveInFlight = useRef(false);
  const submitInFlight = useRef(false);
  const loadInFlight = useRef(false);
  const requestIdRef = useRef(requestId);
  requestIdRef.current = requestId;

  const setField = (field: RequestFormField, value: string | boolean | number | null) => {
    setValues((prev) => ({ ...prev, [field]: value }));
    setFieldErrors((prev) => {
      if (!prev[field]) return prev;
      const next = { ...prev };
      delete next[field];
      return next;
    });
  };

  const applyMapSelection = (stop: 'pickup' | 'delivery', selection: ClientLocationSelection) => {
    setValues((prev) =>
      stop === 'pickup'
        ? {
            ...prev,
            pickupLatitude: selection.latitude,
            pickupLongitude: selection.longitude,
            pickupMapLabel: selection.label || 'Location selected',
            pickupDetectedCity: selection.city || '',
            pickupDetectedCountry: selection.country || '',
          }
        : {
            ...prev,
            deliveryLatitude: selection.latitude,
            deliveryLongitude: selection.longitude,
            deliveryMapLabel: selection.label || 'Location selected',
            deliveryDetectedCity: selection.city || '',
            deliveryDetectedCountry: selection.country || '',
          }
    );
    setFieldErrors((prev) => {
      const next = { ...prev };
      if (stop === 'pickup') {
        delete next.pickupLatitude;
        delete next.pickupLongitude;
      } else {
        delete next.deliveryLatitude;
        delete next.deliveryLongitude;
      }
      return next;
    });
    setPickerSurfaceReady(false);
    setPickerStop(null);
  };

  const applyDetectedPlace = (stop: 'pickup' | 'delivery') => {
    setValues((prev) => {
      if (stop === 'pickup') {
        return {
          ...prev,
          pickupCity: prev.pickupCity.trim() ? prev.pickupCity : prev.pickupDetectedCity,
          pickupCountry: prev.pickupCountry.trim() ? prev.pickupCountry : prev.pickupDetectedCountry,
        };
      }
      return {
        ...prev,
        deliveryCity: prev.deliveryCity.trim() ? prev.deliveryCity : prev.deliveryDetectedCity,
        deliveryCountry: prev.deliveryCountry.trim() ? prev.deliveryCountry : prev.deliveryDetectedCountry,
      };
    });
  };

  const applyFieldErrors = (errors: Partial<Record<RequestFormField, string>>, fallback: string) => {
    setFieldErrors(errors);
    const step = firstInvalidStep(errors);
    if (step) setEditStep(step);
    setError(firstFieldError(errors) || fallback);
    setMode('edit');
  };

  const editReviewSection = (step: RequestEditStep) => {
    setResumeReviewAfterEdit(true);
    setMode('edit');
    setEditStep(step);
    setError(null);
    setBanner(null);
    setFieldErrors({});
  };

  const returnToReview = () => {
    const submitErrors = validateFormForSubmit(values);
    if (Object.keys(submitErrors).length > 0) {
      applyFieldErrors(submitErrors, 'Complete the required fields before returning to review.');
      return;
    }
    setResumeReviewAfterEdit(false);
    setMode('review');
    setFieldErrors({});
    setError(null);
    setBanner(null);
  };

  /** Local step gate — no API. Returns true when navigation may proceed. */
  const applyStepValidation = (
    result: { valid: boolean; errors: Partial<Record<RequestFormField, string>> },
    fallback: string
  ): boolean => {
    if (result.valid) {
      setFieldErrors({});
      setError(null);
      return true;
    }
    applyFieldErrors(result.errors, fallback);
    return false;
  };

  const tryContinue = () => {
    if (busy) return;
    const result = validateEditStep(editStep, values);
    if (!applyStepValidation(result, 'Fix the highlighted fields before continuing.')) {
      return;
    }
    if (editStep < 5) {
      setEditStep((step) => (step + 1) as RequestEditStep);
    }
  };

  const trySelectEditStep = (target: RequestEditStep) => {
    if (busy) return;
    if (target === editStep) return;
    if (target < editStep) {
      setFieldErrors({});
      setError(null);
      setEditStep(target);
      return;
    }
    // Forward: require every prerequisite step 1..target-1 to be valid.
    const through = (target - 1) as RequestEditStep;
    const result = validateEditStepsThrough(through, values, 1);
    if (!applyStepValidation(result, 'Complete earlier steps before continuing.')) {
      return;
    }
    setEditStep(target);
  };

  const applyServerRecord = (details: Awaited<ReturnType<typeof getClientShipmentRequest>>) => {
    setRequestId(details.id);
    requestIdRef.current = details.id;
    setStatusName(details.status?.name ?? null);
    setRequestNumber(details.requestNumber);
    setReviewFeedback(
      isChangesRequestedStatus(details.status?.name) ? details.reviewFeedback : null
    );
    setValues(formFromDetails(details));
  };

  const loadExisting = useCallback(async () => {
    if (!initialId || !roleOk || !identityLinked) {
      setLoading(false);
      return;
    }
    if (loadInFlight.current) return;
    loadInFlight.current = true;
    setLoading(true);
    setError(null);
    try {
      const details = await getClientShipmentRequest(initialId);
      applyServerRecord(details);
      if (!isEditableRequestStatus(details.status?.name) && String(params.mode || '') !== 'review') {
        setMode('review');
      }
    } catch (e) {
      const err = e as ClientShipmentRequestsApiError;
      if (err.statusCode === 401) return;
      setError(err.message || 'Failed to load request');
    } finally {
      loadInFlight.current = false;
      setLoading(false);
    }
  }, [initialId, roleOk, identityLinked, params.mode]);

  useEffect(() => {
    void loadExisting();
  }, [loadExisting]);

  const persist = async (): Promise<boolean> => {
    const draftErrors = validateFormDraft(values);
    if (Object.keys(draftErrors).length > 0) {
      applyFieldErrors(draftErrors, 'Please correct the highlighted fields.');
      return false;
    }

    if (saveInFlight.current || submitInFlight.current) return false;
    saveInFlight.current = true;
    setSaving(true);
    setError(null);
    setBanner(null);

    try {
      const payload = formToWritePayload(values);
      const currentId = requestIdRef.current;
      const details = currentId
        ? await updateClientShipmentRequest(currentId, payload)
        : await createClientShipmentRequest(payload);
      applyServerRecord(details);
      setFieldErrors({});
      setBanner('Draft saved. FleetX has not received this request yet.');
      return true;
    } catch (e) {
      const err = e as ClientShipmentRequestsApiError;
      if (err.fieldErrors) applyFieldErrors(mapApiFieldErrors(err.fieldErrors), err.message || 'Could not save draft');
      else setError(err.message || 'Could not save draft');
      return false;
    } finally {
      saveInFlight.current = false;
      setSaving(false);
    }
  };

  const goReview = async () => {
    const submitErrors = validateFormForSubmit(values);
    if (Object.keys(submitErrors).length > 0) {
      applyFieldErrors(submitErrors, 'Complete the required fields before review.');
      return;
    }
    const ok = await persist();
    if (ok) {
      setBanner(null);
      setResumeReviewAfterEdit(false);
      setMode('review');
    }
  };

  const submitOrResubmit = async () => {
    if (submitInFlight.current || saveInFlight.current) return;

    let id = requestIdRef.current;
    if (!id) {
      const persisted = await persist();
      if (!persisted) return;
      id = requestIdRef.current;
    }

    if (!id) {
      setError('Save the request as a draft before submitting.');
      return;
    }
    if (!canSubmitRequest(statusName) && !canResubmitRequest(statusName)) {
      setError('This request cannot be submitted in its current status.');
      return;
    }

    const submitErrors = validateFormForSubmit(values);
    if (Object.keys(submitErrors).length > 0) {
      applyFieldErrors(submitErrors, 'Complete the required shipment information before submitting.');
      return;
    }

    submitInFlight.current = true;
    setSubmitting(true);
    setError(null);
    setBanner(null);

    let draftPersistedForSubmit = false;
    try {
      const saved = await updateClientShipmentRequest(id, formToWritePayload(values));
      applyServerRecord(saved);
      draftPersistedForSubmit = true;
      const resubmit = canResubmitRequest(saved.status?.name);

      console.log(`[CLIENT SUBMISSION] Submitting request ID: ${saved.id} (resubmit: ${resubmit})`);

      const result = resubmit
        ? await resubmitClientShipmentRequest(saved.id)
        : await submitClientShipmentRequest(saved.id);

      console.log(`[CLIENT SUBMISSION SUCCESS] Request Number: ${result.requestNumber}, Status: ${result.status?.name}`);

      // Success only after backend confirmation — navigate to detail success banner.
      router.replace(
        `/request-details/${result.id}?${resubmit ? 'resubmitted=1' : 'submitted=1'}`
      );
    } catch (e) {
      const err = e as ClientShipmentRequestsApiError;
      console.error('[CLIENT SUBMISSION FAILED]', {
        statusCode: err.statusCode,
        message: err.message,
        fieldErrors: err.fieldErrors,
      });

      if (err.fieldErrors && Object.keys(err.fieldErrors).length > 0) {
        const { mapped, unmappedMessages } = splitApiFieldErrors(err.fieldErrors);
        if (Object.keys(mapped).length > 0) {
          applyFieldErrors(
            mapped,
            unmappedMessages[0] ||
              err.message ||
              "We couldn't submit this shipment request. Please review the information and try again."
          );
          if (unmappedMessages.length > 0) {
            setError(
              [firstFieldError(mapped), ...unmappedMessages].filter(Boolean).join(' ') ||
                err.message
            );
          }
        } else {
          setMode('review');
          setError(
            unmappedMessages[0] ||
              err.message ||
              "We couldn't submit this shipment request. Please review the information and try again."
          );
        }
      } else if (err.isNetwork && draftPersistedForSubmit) {
        setMode('review');
        setError(
          "We couldn't confirm whether submission completed. Check your requests list before submitting again — your draft was saved."
        );
      } else {
        setMode('review');
        setError(
          err.message ||
            "We couldn't submit this shipment request. Please review the information and try again."
        );
      }
    } finally {
      submitInFlight.current = false;
      setSubmitting(false);
    }
  };

  const goBack = () => {
    if (mode === 'review') {
      setResumeReviewAfterEdit(false);
      setMode('edit');
      setEditStep(5);
      return;
    }
    if (resumeReviewAfterEdit) {
      returnToReview();
      return;
    }
    if (editStep > 1) {
      setEditStep((step) => (step - 1) as RequestEditStep);
      return;
    }
    if (router.canGoBack()) {
      router.back();
      return;
    }
    router.replace('/(tabs)/requests');
  };

  const pricingEstimate = useRequestPricingEstimate(values, mode === 'review');

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (!roleOk || !identityLinked) {
    return <Redirect href="/(tabs)/requests" />;
  }

  const busy = saving || submitting;
  const isResubmit = canResubmitRequest(statusName);
  const pickupDateLabel = formatRequestDateLabel(values.requestedPickupDate);
  const deliveryDateLabel = formatRequestDateLabel(values.requestedDeliveryDate);
  const cargoLine = cargoReviewLine(values);
  const pickupCoordinate = asCoordinate(values.pickupLatitude, values.pickupLongitude);
  const deliveryCoordinate = asCoordinate(values.deliveryLatitude, values.deliveryLongitude);
  const pickupHasCoords = hasCoordinatePair(values.pickupLatitude, values.pickupLongitude);
  const deliveryHasCoords = hasCoordinatePair(values.deliveryLatitude, values.deliveryLongitude);
  const reviewReadinessErrors = mode === 'review' ? validateFormForSubmit(values) : {};
  const reviewReady = mode === 'review' && Object.keys(reviewReadinessErrors).length === 0;
  const canPressSubmit =
    reviewReady &&
    !busy &&
    (canSubmitRequest(statusName) || canResubmitRequest(statusName));

  const screenTitle =
    mode === 'review'
      ? 'Review transport request'
      : requestId
        ? isResubmit
          ? 'Edit request'
          : 'Edit draft'
        : 'Create Transport Request';

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.screen} testID={mode === 'review' ? 'request-review' : 'request-form'}>
        <View style={styles.topBar}>
          <TouchableOpacity
            testID="request-form-back"
            style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            onPress={goBack}
            accessibilityRole="button"
            accessibilityLabel={mode === 'review' ? 'Back to editing' : 'Go back'}>
            <ChevronLeft size={20} color={theme.text} />
          </TouchableOpacity>
          <View style={styles.topText}>
            <Text style={[styles.title, { color: theme.text }]}>{screenTitle}</Text>
            {requestNumber ? (
              <Text style={[styles.subtitle, { color: theme.textSecondary }]}>{requestNumber}</Text>
            ) : (
              <Text style={[styles.subtitle, { color: theme.textSecondary }]}>
                {mode === 'review'
                  ? 'Confirm details before sending to FleetX'
                  : 'Save as draft anytime'}
              </Text>
            )}
          </View>
        </View>

        {loading ? (
          <View style={styles.center} testID="request-form-loading">
            <ActivityIndicator color={theme.primary} />
          </View>
        ) : (
          <ScrollView
            style={styles.flex}
            contentContainerStyle={styles.scroll}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            <RequestStepIndicator
              current={editStep}
              reviewMode={mode === 'review'}
              onSelectEditStep={(step) => {
                setMode('edit');
                trySelectEditStep(step);
              }}
              onSelectReview={() => void goReview()}
              theme={theme}
            />

            {statusName ? (
              <Text style={[styles.statusLine, { color: theme.textSecondary }]}>
                Status {formatRequestStatusLabel(statusName)}
              </Text>
            ) : null}

            {isChangesRequestedStatus(statusName) && reviewFeedback?.message ? (
              <View testID="request-form-review-feedback" style={styles.reviewFeedbackCard}>
                <Text style={styles.reviewFeedbackTitle}>FleetX requested changes</Text>
                <Text style={styles.reviewFeedbackHint}>
                  Please update the request using this feedback, then resubmit.
                </Text>
                <Text style={styles.reviewFeedbackMessage} testID="request-form-review-feedback-message">
                  {reviewFeedback.message}
                </Text>
              </View>
            ) : null}

            {banner ? (
              <View testID="request-form-draft-saved" style={styles.banner}>
                <Text style={styles.bannerText}>{banner}</Text>
              </View>
            ) : null}

            {error ? (
              <View testID="request-form-error" style={styles.errorBanner}>
                <Text style={styles.errorBannerText}>{error}</Text>
                {initialId ? (
                  <Pressable
                    testID="request-form-retry"
                    onPress={() => void loadExisting()}
                    disabled={loading}
                    style={[styles.retryBtn, { backgroundColor: theme.primary }, loading && { opacity: 0.6 }]}
                    accessibilityRole="button"
                    accessibilityLabel="Retry loading this request"
                    accessibilityHint="Attempts to reload the request form"
                    accessibilityState={{ disabled: loading, busy: loading }}>
                    <Text style={styles.retryText}>{loading ? 'Retrying…' : 'Retry'}</Text>
                  </Pressable>
                ) : null}
              </View>
            ) : null}

            {mode === 'review' ? (
              <>
                <View
                  testID="request-review-readiness"
                  style={[
                    styles.readinessCard,
                    {
                      borderColor: reviewReady ? 'rgba(34, 197, 94, 0.45)' : 'rgba(239, 68, 68, 0.45)',
                      backgroundColor: reviewReady ? 'rgba(34, 197, 94, 0.08)' : 'rgba(239, 68, 68, 0.08)',
                    },
                  ]}>
                  <Text
                    style={[
                      styles.readinessTitle,
                      { color: reviewReady ? '#15803D' : '#B91C1C' },
                    ]}>
                    {reviewReady ? 'Ready to submit' : 'Not ready to submit'}
                  </Text>
                  <Text style={[styles.readinessBody, { color: theme.textSecondary }]}>
                    {reviewReady
                      ? isResubmit
                        ? 'Review the details below, then resubmit to FleetX for another look.'
                        : 'Review the details below, then submit to FleetX for operations review.'
                      : firstFieldError(reviewReadinessErrors) ||
                        'Complete the required shipment information before submitting.'}
                  </Text>
                  {!reviewReady ? (
                    <Pressable
                      testID="request-review-fix-readiness"
                      onPress={() =>
                        applyFieldErrors(
                          reviewReadinessErrors,
                          'Complete the required shipment information before submitting.'
                        )
                      }
                      style={styles.readinessFixBtn}
                      accessibilityRole="button"
                      accessibilityLabel="Fix required fields">
                      <Text style={[styles.sectionEditText, { color: theme.primary }]}>
                        Fix required fields
                      </Text>
                    </Pressable>
                  ) : null}
                </View>

                <Section
                  title="Shipment details"
                  theme={theme}
                  onEdit={() => editReviewSection(1)}
                  editTestID="request-review-edit-details"
                  editDisabled={busy}>
                  <ReviewRow label="Your reference" value={display(values.referenceNumber)} theme={theme} />
                  {requestNumber ? (
                    <ReviewRow label="FleetX request" value={requestNumber} theme={theme} />
                  ) : null}
                  <ReviewRow label="Purpose / notes" value={display(values.clientNotes)} theme={theme} />
                </Section>

                <Section
                  title="Route"
                  theme={theme}
                  onEdit={() => editReviewSection(2)}
                  editTestID="request-review-edit-route"
                  editDisabled={busy}>
                  <View testID="request-review-route" style={styles.routeStack}>
                    <ClientRoutePreview
                      testID="request-review-map"
                      pickup={pickupCoordinate}
                      delivery={deliveryCoordinate}
                    />
                    <RequestLocationSummary
                      testID="request-location-pickup-card"
                      title="Pickup"
                      company={values.pickupCompany}
                      address={values.pickupAddress}
                      city={values.pickupCity}
                      country={values.pickupCountry}
                      contact={values.pickupContact}
                      phone={values.pickupPhone}
                      mapLabel={values.pickupMapLabel}
                      hasCoordinates={pickupHasCoords}
                      dateLabel={pickupDateLabel}
                      theme={theme}
                    />
                    <View style={styles.routeJoin}>
                      <View style={[styles.routeLine, { backgroundColor: theme.border }]} />
                      <Text style={[styles.routeArrow, { color: theme.textSecondary }]}>↓</Text>
                    </View>
                    <RequestLocationSummary
                      testID="request-location-delivery-card"
                      title="Delivery"
                      company={values.deliveryCompany}
                      address={values.deliveryAddress}
                      city={values.deliveryCity}
                      country={values.deliveryCountry}
                      contact={values.deliveryContact}
                      phone={values.deliveryPhone}
                      mapLabel={values.deliveryMapLabel}
                      hasCoordinates={deliveryHasCoords}
                      dateLabel={deliveryDateLabel}
                      theme={theme}
                    />
                  </View>
                </Section>

                <Section
                  title="Cargo"
                  theme={theme}
                  onEdit={() => editReviewSection(3)}
                  editTestID="request-review-edit-cargo"
                  editDisabled={busy}>
                  <ReviewRow
                    label="Type"
                    value={display(cargoTypeLabel(values.cargoType) ?? values.cargoType)}
                    theme={theme}
                  />
                  <ReviewRow
                    label="Trailer"
                    value={display(trailerTypeLabel(values.trailerType) ?? values.trailerType)}
                    theme={theme}
                  />
                  <ReviewRow label="Description" value={display(values.cargoDescription)} theme={theme} />
                  {cargoLine ? <ReviewRow label="Quantity" value={cargoLine} theme={theme} /> : null}
                  <ReviewRow label="Packages" value={display(values.packageCount)} theme={theme} />
                  <ReviewRow label="Weight" value={display(values.cargoWeight)} theme={theme} />
                  <ReviewRow label="Volume" value={display(values.cargoVolume)} theme={theme} />
                </Section>

                <Section
                  title="Schedule"
                  theme={theme}
                  onEdit={() => editReviewSection(4)}
                  editTestID="request-review-edit-schedule"
                  editDisabled={busy}>
                  <ReviewRow
                    label="Requested pickup"
                    value={pickupDateLabel || display(values.requestedPickupDate)}
                    theme={theme}
                  />
                  <ReviewRow
                    label="Requested delivery"
                    value={deliveryDateLabel || display(values.requestedDeliveryDate)}
                    theme={theme}
                  />
                </Section>

                <Section
                  title="Special requirements"
                  theme={theme}
                  onEdit={() => editReviewSection(5)}
                  editTestID="request-review-edit-notes"
                  editDisabled={busy}>
                  <ReviewRow
                    label="Hazardous cargo"
                    value={values.hazardousCargo ? 'Yes' : 'No'}
                    theme={theme}
                  />
                  <ReviewRow
                    label="Temperature controlled"
                    value={values.temperatureControlled ? 'Yes' : 'No'}
                    theme={theme}
                  />
                  <ReviewRow
                    label="Special instructions"
                    value={display(values.specialInstructions)}
                    theme={theme}
                  />
                </Section>

                <RequestPricingEstimateCard state={pricingEstimate} theme={theme} />

                <Text testID="request-review-submit-hint" style={[styles.confirmHint, { color: theme.textSecondary }]}>
                  Submitting sends this request to FleetX for review. It does not create a shipment
                  and does not mean approval. The dispatch team will determine the appropriate vehicle,
                  trailer, driver, and operational resources. The displayed cost is an estimate. The
                  final price is calculated when the request is submitted.
                </Text>

                <Pressable
                  testID="request-form-edit"
                  style={[styles.outlineBtn, { borderColor: theme.border, opacity: busy ? 0.6 : 1 }]}
                  onPress={() => editReviewSection(1)}
                  disabled={busy}
                  accessibilityRole="button"
                  accessibilityLabel="Edit request from the beginning">
                  <Text style={[styles.outlineBtnText, { color: theme.text }]}>Edit all details</Text>
                </Pressable>

                <Pressable
                  testID={isResubmit ? 'request-resubmit' : 'request-submit'}
                  style={[
                    styles.primaryBtn,
                    {
                      backgroundColor: theme.primary,
                      opacity: canPressSubmit ? 1 : 0.55,
                    },
                  ]}
                  onPress={() => void submitOrResubmit()}
                  disabled={!canPressSubmit}
                  accessibilityRole="button"
                  accessibilityLabel={
                    submitting
                      ? 'Submitting request'
                      : isResubmit
                        ? 'Resubmit request'
                        : 'Submit request'
                  }
                  accessibilityState={{ busy: submitting, disabled: !canPressSubmit }}>
                  {submitting ? (
                    <View style={styles.submitBusyRow}>
                      <ActivityIndicator color="#FFFFFF" />
                      <Text style={styles.primaryBtnText}>Submitting…</Text>
                    </View>
                  ) : (
                    <Text style={styles.primaryBtnText}>
                      {isResubmit ? 'Resubmit Request' : 'Submit Request'}
                    </Text>
                  )}
                </Pressable>
                {!reviewReady ? (
                  <Text
                    testID="request-review-submit-blocked-hint"
                    style={[styles.confirmHint, { color: theme.textSecondary, marginTop: 4 }]}>
                    Submit stays unavailable until required shipment information is complete.
                  </Text>
                ) : null}
              </>
            ) : (
              <>
                {editStep === 1 ? (
                  <Section
                    title="Request details"
                    hint="Identify this request for your records. FleetX will still generate a request number."
                    theme={theme}>
                    <InputField
                      label="Your reference"
                      value={values.referenceNumber}
                      onChangeText={(t) => setField('referenceNumber', t)}
                      autoCapitalize="none"
                      placeholder="PO-2026-00452"
                      testID="request-field-referenceNumber"
                    />
                    <Text style={[styles.areaLabel, { color: theme.textSecondary }]}>
                      Purpose / notes for dispatch
                    </Text>
                    <TextInput
                      testID="request-field-clientNotes"
                      style={[
                        styles.area,
                        { color: theme.text, borderColor: theme.border, backgroundColor: theme.inputBg },
                      ]}
                      value={values.clientNotes}
                      onChangeText={(t) => setField('clientNotes', t)}
                      multiline
                      textAlignVertical="top"
                      placeholder="What is this transport for?"
                      placeholderTextColor={theme.textSecondary}
                    />
                  </Section>
                ) : null}

                {editStep === 2 ? (
                  <>
                    <RequestLocationSummary
                      testID="request-location-pickup-preview"
                      title="Pickup"
                      company={values.pickupCompany}
                      address={values.pickupAddress}
                      city={values.pickupCity}
                      country={values.pickupCountry}
                      contact={values.pickupContact}
                      phone={values.pickupPhone}
                      mapLabel={values.pickupMapLabel}
                      hasCoordinates={pickupHasCoords}
                      theme={theme}
                    />
                    <Section
                      title="Pickup location"
                      hint="City and country are required so FleetX can plan pickup. Select the map point separately — it does not replace your address."
                      theme={theme}>
                      <InputField
                        label="Company / place name"
                        value={values.pickupCompany}
                        onChangeText={(t) => setField('pickupCompany', t)}
                        autoCapitalize="words"
                        testID="request-field-pickupCompany"
                      />
                      <InputField
                        label="City *"
                        value={values.pickupCity}
                        onChangeText={(t) => setField('pickupCity', t)}
                        error={fieldErrors.pickupCity}
                        autoCapitalize="words"
                        testID="request-field-pickupCity"
                      />
                      <InputField
                        label="Country *"
                        value={values.pickupCountry}
                        onChangeText={(t) => setField('pickupCountry', t)}
                        error={fieldErrors.pickupCountry}
                        autoCapitalize="words"
                        testID="request-field-pickupCountry"
                      />
                      <InputField
                        label="Address"
                        value={values.pickupAddress}
                        onChangeText={(t) => setField('pickupAddress', t)}
                        autoCapitalize="sentences"
                        testID="request-field-pickupAddress"
                      />
                      <InputField
                        label="Contact name"
                        value={values.pickupContact}
                        onChangeText={(t) => setField('pickupContact', t)}
                        autoCapitalize="words"
                        testID="request-field-pickupContact"
                      />
                      <InputField
                        label="Contact phone"
                        value={values.pickupPhone}
                        onChangeText={(t) => setField('pickupPhone', t)}
                        keyboardType="phone-pad"
                        testID="request-field-pickupPhone"
                      />
                      <Pressable
                        testID="request-pickup-map"
                        onPress={() => {
                          setPickerSurfaceReady(false);
                          setPickerStop('pickup');
                        }}
                        disabled={busy}
                        style={[styles.mapBtn, { borderColor: theme.primary, backgroundColor: `${theme.primary}14` }]}
                        accessibilityRole="button"
                        accessibilityLabel="Select pickup on map">
                        <MapPin size={16} color={theme.primary} />
                        <Text style={[styles.mapBtnText, { color: theme.primary }]}>
                          {pickupHasCoords ? 'Change pickup on map' : 'Select pickup on map'}
                        </Text>
                      </Pressable>
                      {fieldErrors.pickupLatitude ? (
                        <Text style={styles.fieldError}>{fieldErrors.pickupLatitude}</Text>
                      ) : null}
                      {fieldErrors.pickupLongitude ? (
                        <Text style={styles.fieldError}>{fieldErrors.pickupLongitude}</Text>
                      ) : null}
                      {pickupHasCoords ? (
                        <Text testID="request-pickup-location" style={[styles.selectedLine, { color: theme.text }]}>
                          📍 {values.pickupMapLabel || 'Location selected'}
                        </Text>
                      ) : (
                        <Text testID="request-pickup-location" style={[styles.sectionHint, { color: theme.textSecondary }]}>
                          Map point not selected yet
                        </Text>
                      )}
                      {values.pickupDetectedCity || values.pickupDetectedCountry ? (
                        <Pressable
                          testID="request-pickup-apply-detected"
                          onPress={() => applyDetectedPlace('pickup')}
                          accessibilityRole="button"
                          accessibilityLabel="Use detected city and country if empty">
                          <Text style={[styles.applyDetected, { color: theme.primary }]}>
                            Detected: {[values.pickupDetectedCity, values.pickupDetectedCountry].filter(Boolean).join(', ')}. Tap to fill empty city/country.
                          </Text>
                        </Pressable>
                      ) : null}
                    </Section>

                    <RequestLocationSummary
                      testID="request-location-delivery-preview"
                      title="Delivery"
                      company={values.deliveryCompany}
                      address={values.deliveryAddress}
                      city={values.deliveryCity}
                      country={values.deliveryCountry}
                      contact={values.deliveryContact}
                      phone={values.deliveryPhone}
                      mapLabel={values.deliveryMapLabel}
                      hasCoordinates={deliveryHasCoords}
                      theme={theme}
                    />
                    <Section
                      title="Delivery location"
                      hint="City and country are required for dispatch planning. Select the map point separately — it does not replace your address."
                      theme={theme}>
                      <InputField
                        label="Company / place name"
                        value={values.deliveryCompany}
                        onChangeText={(t) => setField('deliveryCompany', t)}
                        autoCapitalize="words"
                        testID="request-field-deliveryCompany"
                      />
                      <InputField
                        label="City *"
                        value={values.deliveryCity}
                        onChangeText={(t) => setField('deliveryCity', t)}
                        error={fieldErrors.deliveryCity}
                        autoCapitalize="words"
                        testID="request-field-deliveryCity"
                      />
                      <InputField
                        label="Country *"
                        value={values.deliveryCountry}
                        onChangeText={(t) => setField('deliveryCountry', t)}
                        error={fieldErrors.deliveryCountry}
                        autoCapitalize="words"
                        testID="request-field-deliveryCountry"
                      />
                      <InputField
                        label="Address"
                        value={values.deliveryAddress}
                        onChangeText={(t) => setField('deliveryAddress', t)}
                        autoCapitalize="sentences"
                        testID="request-field-deliveryAddress"
                      />
                      <InputField
                        label="Contact name"
                        value={values.deliveryContact}
                        onChangeText={(t) => setField('deliveryContact', t)}
                        autoCapitalize="words"
                        testID="request-field-deliveryContact"
                      />
                      <InputField
                        label="Contact phone"
                        value={values.deliveryPhone}
                        onChangeText={(t) => setField('deliveryPhone', t)}
                        keyboardType="phone-pad"
                        testID="request-field-deliveryPhone"
                      />
                      <Pressable
                        testID="request-delivery-map"
                        onPress={() => {
                          setPickerSurfaceReady(false);
                          setPickerStop('delivery');
                        }}
                        disabled={busy}
                        style={[styles.mapBtn, { borderColor: theme.primary, backgroundColor: `${theme.primary}14` }]}
                        accessibilityRole="button"
                        accessibilityLabel="Select delivery on map">
                        <MapPin size={16} color={theme.primary} />
                        <Text style={[styles.mapBtnText, { color: theme.primary }]}>
                          {deliveryHasCoords ? 'Change delivery on map' : 'Select delivery on map'}
                        </Text>
                      </Pressable>
                      {fieldErrors.deliveryLatitude ? (
                        <Text style={styles.fieldError}>{fieldErrors.deliveryLatitude}</Text>
                      ) : null}
                      {fieldErrors.deliveryLongitude ? (
                        <Text style={styles.fieldError}>{fieldErrors.deliveryLongitude}</Text>
                      ) : null}
                      {deliveryHasCoords ? (
                        <Text testID="request-delivery-location" style={[styles.selectedLine, { color: theme.text }]}>
                          📍 {values.deliveryMapLabel || 'Location selected'}
                        </Text>
                      ) : (
                        <Text testID="request-delivery-location" style={[styles.sectionHint, { color: theme.textSecondary }]}>
                          Map point not selected yet
                        </Text>
                      )}
                      {values.deliveryDetectedCity || values.deliveryDetectedCountry ? (
                        <Pressable
                          testID="request-delivery-apply-detected"
                          onPress={() => applyDetectedPlace('delivery')}
                          accessibilityRole="button"
                          accessibilityLabel="Use detected city and country if empty">
                          <Text style={[styles.applyDetected, { color: theme.primary }]}>
                            Detected: {[values.deliveryDetectedCity, values.deliveryDetectedCountry].filter(Boolean).join(', ')}. Tap to fill empty city/country.
                          </Text>
                        </Pressable>
                      ) : null}
                    </Section>
                  </>
                ) : null}

                {editStep === 3 ? (
                  <Section
                    title="Cargo"
                    hint="Describe what needs to move. Dispatch uses this to plan equipment — you are not assigning a vehicle."
                    theme={theme}>
                    <Text style={[styles.areaLabel, { color: theme.textSecondary }]}>Cargo type *</Text>
                    <View style={styles.chipRow} testID="request-field-cargoType">
                      {CARGO_TYPE_OPTIONS.map((option) => {
                        const selected = values.cargoType === option.value;
                        return (
                          <Pressable
                            key={option.value}
                            testID={`request-cargo-type-${option.value}`}
                            onPress={() => setField('cargoType', option.value)}
                            accessibilityRole="button"
                            accessibilityState={{ selected }}
                            accessibilityLabel={`Cargo type ${option.label}`}
                            style={[
                              styles.chip,
                              {
                                borderColor: selected ? theme.primary : theme.border,
                                backgroundColor: selected ? `${theme.primary}22` : theme.inputBg,
                              },
                            ]}>
                            <Text style={[styles.chipText, { color: selected ? theme.primary : theme.text }]}>
                              {option.label}
                            </Text>
                          </Pressable>
                        );
                      })}
                    </View>
                    {fieldErrors.cargoType ? (
                      <Text style={styles.fieldError}>{fieldErrors.cargoType}</Text>
                    ) : null}

                    <Text style={[styles.areaLabel, { color: theme.textSecondary, marginTop: 12 }]}>
                      Trailer type *
                    </Text>
                    <View style={styles.chipRow} testID="request-field-trailerType">
                      {TRAILER_TYPE_OPTIONS.map((option) => {
                        const selected = values.trailerType === option.value;
                        return (
                          <Pressable
                            key={option.value}
                            testID={`request-trailer-type-${option.value}`}
                            onPress={() => setField('trailerType', option.value)}
                            accessibilityRole="button"
                            accessibilityState={{ selected }}
                            accessibilityLabel={`Trailer type ${option.label}`}
                            style={[
                              styles.chip,
                              {
                                borderColor: selected ? theme.primary : theme.border,
                                backgroundColor: selected ? `${theme.primary}22` : theme.inputBg,
                              },
                            ]}>
                            <Text style={[styles.chipText, { color: selected ? theme.primary : theme.text }]}>
                              {option.label}
                            </Text>
                          </Pressable>
                        );
                      })}
                    </View>
                    {fieldErrors.trailerType ? (
                      <Text style={styles.fieldError}>{fieldErrors.trailerType}</Text>
                    ) : null}

                    <Text style={[styles.areaLabel, { color: theme.textSecondary, marginTop: 12 }]}>
                      Description *
                    </Text>
                    <TextInput
                      testID="request-field-cargoDescription"
                      style={[
                        styles.area,
                        {
                          color: theme.text,
                          borderColor: fieldErrors.cargoDescription ? '#EF4444' : theme.border,
                          backgroundColor: theme.inputBg,
                        },
                      ]}
                      value={values.cargoDescription}
                      onChangeText={(t) => setField('cargoDescription', t)}
                      multiline
                      textAlignVertical="top"
                      placeholder="What needs to be moved?"
                      placeholderTextColor={theme.textSecondary}
                    />
                    {fieldErrors.cargoDescription ? (
                      <Text style={styles.fieldError}>{fieldErrors.cargoDescription}</Text>
                    ) : null}
                    <InputField
                      label="Package count"
                      value={values.packageCount}
                      onChangeText={(t) => setField('packageCount', t)}
                      error={fieldErrors.packageCount}
                      keyboardType="number-pad"
                      testID="request-field-packageCount"
                    />
                    <InputField
                      label="Total weight"
                      value={values.cargoWeight}
                      onChangeText={(t) => setField('cargoWeight', t)}
                      error={fieldErrors.cargoWeight}
                      keyboardType="decimal-pad"
                      testID="request-field-cargoWeight"
                    />
                    <InputField
                      label="Volume"
                      value={values.cargoVolume}
                      onChangeText={(t) => setField('cargoVolume', t)}
                      error={fieldErrors.cargoVolume}
                      keyboardType="decimal-pad"
                      testID="request-field-cargoVolume"
                    />
                    <View style={styles.cargoHintRow}>
                      <Package size={14} color={theme.textSecondary} />
                      <Text style={[styles.sectionHint, { color: theme.textSecondary, marginBottom: 0 }]}>
                        Weight and volume have no unit field on this contract. Enter the numeric values your
                        team already uses with dispatch.
                      </Text>
                    </View>
                  </Section>
                ) : null}

                {editStep === 4 ? (
                  <Section
                    title="Schedule"
                    hint="Requested dates only. Time windows are not part of this request contract."
                    theme={theme}>
                    <RequestDateField
                      label="Pickup date"
                      value={values.requestedPickupDate}
                      onChange={(t) => setField('requestedPickupDate', t)}
                      error={fieldErrors.requestedPickupDate}
                      testID="request-field-requestedPickupDate"
                      openTestID="request-date-pickup-open"
                      helper="Optional. Delivery must be on or after pickup when both are set."
                      theme={theme}
                    />
                    <RequestDateField
                      label="Delivery date"
                      value={values.requestedDeliveryDate}
                      onChange={(t) => setField('requestedDeliveryDate', t)}
                      error={fieldErrors.requestedDeliveryDate}
                      testID="request-field-requestedDeliveryDate"
                      openTestID="request-date-delivery-open"
                      theme={theme}
                    />
                  </Section>
                ) : null}

                {editStep === 5 ? (
                  <Section
                    title="Requirements & notes"
                    hint="These are shipment requirements for dispatch. They do not assign a vehicle or driver."
                    theme={theme}>
                    <Checkbox
                      testID="request-field-hazardousCargo"
                      label="Hazardous cargo"
                      checked={values.hazardousCargo}
                      onChange={(v) => setField('hazardousCargo', v)}
                    />
                    <Checkbox
                      testID="request-field-temperatureControlled"
                      label="Temperature controlled"
                      checked={values.temperatureControlled}
                      onChange={(v) => setField('temperatureControlled', v)}
                    />
                    <Text style={[styles.areaLabel, { color: theme.textSecondary, marginTop: 12 }]}>
                      Special instructions
                    </Text>
                    <TextInput
                      testID="request-field-specialInstructions"
                      style={[
                        styles.area,
                        { color: theme.text, borderColor: theme.border, backgroundColor: theme.inputBg },
                      ]}
                      value={values.specialInstructions}
                      onChangeText={(t) => setField('specialInstructions', t)}
                      multiline
                      textAlignVertical="top"
                      placeholder="Loading, site access, or handling notes"
                      placeholderTextColor={theme.textSecondary}
                    />
                  </Section>
                ) : null}

                <View style={styles.footerNav}>
                  {editStep > 1 ? (
                    <Pressable
                      testID="request-step-back"
                      style={[styles.outlineBtn, styles.footerBtn, { borderColor: theme.border }]}
                      onPress={() => {
                        setFieldErrors({});
                        setError(null);
                        setEditStep((step) => (step - 1) as RequestEditStep);
                      }}
                      disabled={busy}
                      accessibilityRole="button"
                      accessibilityLabel="Previous step">
                      <Text style={[styles.outlineBtnText, { color: theme.text }]}>Back</Text>
                    </Pressable>
                  ) : null}
                  {editStep < 5 ? (
                    <Pressable
                      testID="request-step-continue"
                      style={[
                        styles.primaryBtn,
                        styles.footerBtn,
                        { backgroundColor: theme.primary, opacity: busy ? 0.6 : 1 },
                      ]}
                      onPress={tryContinue}
                      disabled={busy}
                      accessibilityRole="button"
                      accessibilityLabel="Continue to next step">
                      <Text style={styles.primaryBtnText}>Continue</Text>
                    </Pressable>
                  ) : (
                    <Pressable
                      testID="request-go-review"
                      style={[
                        styles.primaryBtn,
                        styles.footerBtn,
                        { backgroundColor: theme.primary, opacity: busy ? 0.6 : 1 },
                      ]}
                      onPress={() => void goReview()}
                      disabled={busy}
                      accessibilityRole="button"
                      accessibilityLabel="Review request">
                      <Text style={styles.primaryBtnText}>Review</Text>
                    </Pressable>
                  )}
                </View>

                {editStep < 5 ? (
                  <Pressable
                    testID="request-go-review"
                    style={[styles.outlineBtn, { borderColor: theme.border, opacity: busy ? 0.6 : 1 }]}
                    onPress={() => void goReview()}
                    disabled={busy}
                    accessibilityRole="button"
                    accessibilityLabel="Review request">
                    <Text style={[styles.outlineBtnText, { color: theme.text }]}>Review</Text>
                  </Pressable>
                ) : null}

                {resumeReviewAfterEdit ? (
                  <Pressable
                    testID="request-return-to-review"
                    style={[styles.outlineBtn, { borderColor: theme.primary, opacity: busy ? 0.6 : 1 }]}
                    onPress={returnToReview}
                    disabled={busy}
                    accessibilityRole="button"
                    accessibilityLabel="Back to review">
                    <Text style={[styles.outlineBtnText, { color: theme.primary }]}>Back to review</Text>
                  </Pressable>
                ) : null}

                <Pressable
                  testID="request-save-draft"
                  style={[
                    styles.outlineBtn,
                    { borderColor: theme.border, opacity: busy ? 0.6 : 1 },
                  ]}
                  onPress={() => void persist()}
                  disabled={busy}
                  accessibilityRole="button"
                  accessibilityLabel="Save draft"
                  accessibilityState={{ busy: saving, disabled: busy }}>
                  {saving ? (
                    <ActivityIndicator color={theme.primary} />
                  ) : (
                    <Text style={[styles.outlineBtnText, { color: theme.text }]}>Save Draft</Text>
                  )}
                </Pressable>
              </>
            )}
          </ScrollView>
        )}
      </View>
      <Modal
        visible={pickerStop != null}
        animationType="slide"
        presentationStyle="fullScreen"
        onShow={() => setPickerSurfaceReady(true)}
        onRequestClose={() => {
          setPickerSurfaceReady(false);
          setPickerStop(null);
        }}>
        <View style={styles.pickerModalHost} collapsable={false}>
          {pickerStop && pickerSurfaceReady ? (
            <ClientLocationPicker
              stop={pickerStop}
              initialCoordinate={pickerStop === 'pickup' ? pickupCoordinate : deliveryCoordinate}
              onConfirm={(selection) => applyMapSelection(pickerStop, selection)}
              onCancel={() => {
                setPickerSurfaceReady(false);
                setPickerStop(null);
              }}
            />
          ) : pickerStop ? (
            <View style={styles.pickerModalLoading} testID="request-map-picker-preparing">
              <ActivityIndicator color={theme.primary} />
            </View>
          ) : null}
        </View>
      </Modal>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  flex: { flex: 1 },
  pickerModalHost: { flex: 1, backgroundColor: '#0B1220' },
  pickerModalLoading: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scroll: { paddingBottom: 32, gap: 12 },
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 12 },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  topText: { flex: 1 },
  title: { fontSize: 18, fontWeight: '700' },
  subtitle: { fontSize: 12, marginTop: 2 },
  statusLine: { fontSize: 12, fontWeight: '600' },
  reviewFeedbackCard: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 14,
    borderColor: 'rgba(245, 158, 11, 0.5)',
    backgroundColor: 'rgba(245, 158, 11, 0.12)',
  },
  reviewFeedbackTitle: { color: '#FBBF24', fontSize: 15, fontWeight: '700', marginBottom: 6 },
  reviewFeedbackHint: { color: 'rgba(255,255,255,0.7)', fontSize: 13, lineHeight: 18, marginBottom: 8 },
  reviewFeedbackMessage: { color: '#FFFFFF', fontSize: 14, lineHeight: 20, fontWeight: '600' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: { borderWidth: 1.5, borderRadius: 16, padding: 16 },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 6,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700' },
  sectionEditBtn: {
    paddingVertical: 2,
    paddingHorizontal: 4,
  },
  sectionEditText: {
    fontSize: 13,
    fontWeight: '700',
  },
  readinessCard: {
    borderWidth: 1.5,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    gap: 6,
  },
  readinessTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  readinessBody: {
    fontSize: 13,
    lineHeight: 18,
  },
  readinessFixBtn: {
    alignSelf: 'flex-start',
    marginTop: 4,
  },
  submitBusyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  sectionHint: { fontSize: 12, lineHeight: 17, marginBottom: 12 },
  areaLabel: { fontSize: 14, fontWeight: '600', marginBottom: 6, paddingLeft: 4 },
  area: {
    minHeight: 88,
    borderWidth: 1.5,
    borderRadius: 12,
    padding: 12,
    fontSize: 15,
    marginBottom: 12,
  },
  fieldError: { color: '#EF4444', fontSize: 12, marginBottom: 8, paddingLeft: 4 },
  banner: {
    borderRadius: 12,
    padding: 12,
    backgroundColor: 'rgba(30, 117, 255, 0.12)',
  },
  bannerText: { color: '#1E75FF', fontSize: 13, fontWeight: '600' },
  errorBanner: {
    borderRadius: 12,
    padding: 12,
    backgroundColor: 'rgba(239, 68, 68, 0.12)',
    gap: 10,
  },
  errorBannerText: { color: '#F87171', fontSize: 13, lineHeight: 18 },
  retryBtn: {
    alignSelf: 'flex-start',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
  },
  retryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 13 },
  reviewRow: { marginBottom: 8 },
  reviewLabel: { fontSize: 12, fontWeight: '600', marginBottom: 2 },
  reviewValue: { fontSize: 14, lineHeight: 20 },
  confirmHint: { fontSize: 13, lineHeight: 18, marginTop: 4 },
  primaryBtn: {
    minHeight: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
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
  footerNav: { flexDirection: 'row', gap: 10 },
  footerBtn: { flex: 1 },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  chip: {
    borderWidth: 1.5,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  chipText: { fontSize: 12, fontWeight: '700' },
  cargoHintRow: { flexDirection: 'row', gap: 8, alignItems: 'flex-start', marginTop: 4 },
  mapBtn: {
    minHeight: 48,
    borderRadius: 12,
    borderWidth: 1.5,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 4,
    marginBottom: 8,
  },
  mapBtnText: { fontWeight: '700', fontSize: 14 },
  selectedLine: { fontSize: 14, fontWeight: '700', marginBottom: 8 },
  applyDetected: { fontSize: 13, lineHeight: 18, fontWeight: '600', marginBottom: 8 },
  routeStack: { gap: 0 },
  routeJoin: { alignItems: 'center', height: 28, justifyContent: 'center' },
  routeLine: { position: 'absolute', width: 2, top: 0, bottom: 0 },
  routeArrow: { fontSize: 16, fontWeight: '700' },
});
