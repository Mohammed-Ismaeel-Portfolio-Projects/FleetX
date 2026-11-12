import { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import { Camera, MapPin, PenLine, AlertTriangle } from 'lucide-react-native';


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
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { getOutcome, submitPod } from '@/features/driverApi/work';
import { DriverWorkApiError, isRetryableWorkError } from '@/features/driverApi/workTypes';
import { podEntryAllowed } from '@/lib/driver/assignmentState';
import {
  SignaturePad,
  type SignatureStroke,
} from '@/features/pod/SignaturePad';
import { persistPodEvidenceFiles } from '@/features/pod/podStorageHelper';
import { useAuth } from '@/lib/auth/AuthContext';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

type Step = 'capture' | 'review' | 'submitting' | 'success' | 'error';
type ConditionStatus = 'good' | 'damaged' | 'partial' | 'refused';

const POD_STEPS = ['Capture', 'Review', 'Submit'] as const;

const CONDITION_OPTIONS: Array<{ key: ConditionStatus; label: string; desc: string }> = [
  { key: 'good', label: 'Good', desc: 'Package delivered without visible issues' },
  { key: 'damaged', label: 'Damaged', desc: 'Package arrived damaged' },
  { key: 'partial', label: 'Partial', desc: 'Partial delivery completed' },
  { key: 'refused', label: 'Refused', desc: 'Receiver refused delivery' },
];

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

function podProgressIndex(step: Step): number {
  if (step === 'review') return 1;
  if (step === 'submitting' || step === 'success') return 2;
  return 0;
}

function PodProgress({ step }: { step: Step }) {
  const current = podProgressIndex(step);
  const last = POD_STEPS.length - 1;
  return (
    <View
      style={styles.progress}
      accessibilityRole="progressbar"
      accessibilityLabel={`Proof of delivery progress: ${POD_STEPS[current]}`}>
      {POD_STEPS.map((label, index) => {
        const done = index < current;
        const active = index === current;
        const lineOn = DriverTheme.colors.primary;
        const lineOff = DriverTheme.colors.border;
        const tone = done || active ? DriverTheme.colors.primary : DriverTheme.colors.border;
        const textColor = active
          ? DriverTheme.colors.primary
          : done
            ? DriverTheme.colors.textPrimary
            : DriverTheme.colors.textMuted;
        return (
          <View key={label} style={styles.progressStep}>
            <View style={styles.progressTrack}>
              <View
                style={[
                  styles.progressLine,
                  { backgroundColor: index === 0 ? 'transparent' : index <= current ? lineOn : lineOff },
                ]}
              />
              <View style={[styles.progressDot, { backgroundColor: tone }, active && styles.progressDotActive]} />
              <View
                style={[
                  styles.progressLine,
                  { backgroundColor: index === last ? 'transparent' : index < current ? lineOn : lineOff },
                ]}
              />
            </View>
            <Text style={[styles.progressLabel, { color: textColor }]}>{label}</Text>
          </View>
        );
      })}
    </View>
  );
}

/**
 * POD evidence capture flow (D7 + POD-03):
 * Captures Receiver Name, Role, Condition, Notes, GPS Snapshot, Photo, and Signature.
 */
export default function PodScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const assignmentId = String(id || '');
  const router = useRouter();
  const { canOperate } = useAuth();

  const [gateStatus, setGateStatus] = useState<'loading' | 'ready' | 'blocked' | 'error'>(
    'loading'
  );
  const [gateError, setGateError] = useState<string | null>(null);
  const [retryable, setRetryable] = useState(true);
  const [step, setStep] = useState<Step>('capture');

  // Shipment Context Details
  const [shipmentRef, setShipmentRef] = useState<string | null>(null);
  const [deliveryLoc, setDeliveryLoc] = useState<string | null>(null);

  // Form Evidence Metadata
  const [receiverName, setReceiverName] = useState('');
  const [receiverRole, setReceiverRole] = useState('');
  const [conditionStatus, setConditionStatus] = useState<ConditionStatus>('good');
  const [deliveryNotes, setDeliveryNotes] = useState('');
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [strokes, setStrokes] = useState<SignatureStroke[]>([]);

  // GPS Evidence Snapshot
  const [gpsLocation, setGpsLocation] = useState<{ latitude: number; longitude: number } | null>(null);
  const [gpsWarning, setGpsWarning] = useState<string | null>(null);

  const [error, setError] = useState<string | null>(null);
  const [confirmedAt, setConfirmedAt] = useState<string | null>(null);
  const [stationaryOk, setStationaryOk] = useState(false);
  const submitLock = useRef(false);

  // Capture single-shot GPS snapshot
  const captureGpsSnapshot = useCallback(async () => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        setGpsWarning('Location unavailable. Evidence will be submitted without GPS confirmation.');
        return;
      }
      const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      if (loc?.coords) {
        setGpsLocation({ latitude: loc.coords.latitude, longitude: loc.coords.longitude });
        setGpsWarning(null);
      }
    } catch {
      setGpsWarning('Location unavailable. Evidence will be submitted without GPS confirmation.');
    }
  }, []);

  const loadGate = useCallback(async () => {
    if (!canOperate || !assignmentId) {
      setGateStatus('blocked');
      setGateError('Proof of delivery is unavailable for this account.');
      return;
    }
    setGateStatus('loading');
    setGateError(null);
    try {
      const { assignment, outcome } = await getOutcome(assignmentId);
      if (!assignment.isActive || assignment.status?.key !== 'in_operation') {
        setGateStatus('blocked');
        setGateError('This assignment is no longer active.');
        return;
      }
      if (outcome.podSubmitted) {
        router.replace(`/active-trip/${assignmentId}` as Href);
        return;
      }
      if (!podEntryAllowed(outcome.executionPhase)) {
        setGateStatus('blocked');
        setGateError(
          'Delivery must be verified before submitting proof. Return to Active Trip.'
        );
        return;
      }

      setShipmentRef(assignment.shipment?.shipmentNumber ?? `Assignment #${assignmentId}`);
      setDeliveryLoc(assignment.shipment?.delivery?.address ?? null);
      setGateStatus('ready');
      void captureGpsSnapshot();
    } catch (e) {
      setRetryable(isRetryableWorkError(e));
      setGateError(
        e instanceof DriverWorkApiError ? e.message : 'Could not verify POD eligibility.'
      );
      setGateStatus('error');
    }
  }, [assignmentId, canOperate, captureGpsSnapshot, router]);

  useFocusEffect(
    useCallback(() => {
      void loadGate();
    }, [loadGate])
  );

  const pickPhoto = useCallback(async () => {
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      setError('Camera permission is required to capture delivery photo.');
      return;
    }
    const result = await ImagePicker.launchCameraAsync({
      quality: 0.7,
      allowsEditing: false,
    });
    if (!result.canceled && result.assets[0]?.uri) {
      setPhotoUri(result.assets[0].uri);
      setError(null);
    }
  }, []);

  const goReview = () => {
    if (!receiverName || receiverName.trim().length < 2) {
      setError('Receiver name is required (minimum 2 characters).');
      return;
    }
    if (!photoUri) {
      setError('Capture a delivery photo first.');
      return;
    }
    if (strokes.length === 0) {
      setError('Recipient signature is required.');
      return;
    }
    setError(null);
    setStep('review');
  };

  const submit = async () => {
    if (!canOperate || !receiverName || !photoUri || strokes.length === 0 || submitLock.current) return;
    submitLock.current = true;
    setStep('submitting');
    setError(null);
    try {
      const { photoUri: persistentPhotoUri, signatureUri: persistentSigUri } =
        await persistPodEvidenceFiles(assignmentId, photoUri, strokes);

      const result = await submitPod(assignmentId, {
        photo: { uri: persistentPhotoUri, name: 'delivery.jpg', type: 'image/jpeg' },
        signature: { uri: persistentSigUri, name: 'signature.json', type: 'application/json' },
        receiverName: receiverName.trim(),
        receiverRole: receiverRole.trim() || undefined,
        conditionStatus,
        deliveryNotes: deliveryNotes.trim() || undefined,
        latitude: gpsLocation?.latitude,
        longitude: gpsLocation?.longitude,
      });

      setConfirmedAt(result.pod.confirmedAt);
      setStep('success');
    } catch (e) {
      setError(e instanceof DriverWorkApiError ? e.message : 'Could not submit proof of delivery.');
      setStep('error');
    } finally {
      submitLock.current = false;
    }
  };

  const goActiveTrip = () => router.replace(`/active-trip/${assignmentId}` as Href);

  const header = (
    <DriverHeader
      title="Delivery Evidence"
      titleTestID="pod-title"
      statusLabel={step === 'success' ? 'Submitted' : 'Delivery verified'}
      statusAvailable={step === 'success'}
      onBack={() => router.back()}
    />
  );

  if (gateStatus === 'loading') {
    return (
      <DriverScreen header={header} padded safeBottom>
        <ScreenLoading testID="pod-loading" label="Checking eligibility…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (gateStatus === 'error' || gateStatus === 'blocked') {
    return (
      <DriverScreen
        header={header}
        padded
        footer={
          <BottomActionBar>
            <SecondaryButton
              testID="pod-gate-back"
              label="Back to Active Trip"
              onPress={goActiveTrip}
            />
          </BottomActionBar>
        }>
        <ScreenError
          testID="pod-gate-error"
          retryTestID="pod-gate-retry"
          title={gateStatus === 'blocked' ? 'POD unavailable' : 'Could not load POD'}
          body={gateError || 'Could not open proof of delivery.'}
          onRetry={gateStatus === 'error' && retryable ? () => void loadGate() : null}
          theme={uxTheme}
        />
      </DriverScreen>
    );
  }

  const captureOpen = step === 'capture' || step === 'error';
  const footer = captureOpen && !stationaryOk ? (
    <BottomActionBar>
      <PrimaryButton
        testID="pod-stationary-confirm"
        label="I confirm the vehicle is stopped"
        accessibilityLabel="I confirm the vehicle is stopped"
        onPress={() => setStationaryOk(true)}
      />
    </BottomActionBar>
  ) : captureOpen && stationaryOk ? (
    <BottomActionBar>
      <PrimaryButton
        testID="pod-go-review"
        label="Review Evidence"
        accessibilityLabel="Review evidence"
        onPress={goReview}
      />
    </BottomActionBar>
  ) : step === 'review' ? (
    <BottomActionBar>
      <PrimaryButton
        testID="pod-submit"
        label="Submit Delivery Evidence"
        accessibilityLabel="Submit delivery evidence"
        onPress={() => void submit()}
      />
      <SecondaryButton
        testID="pod-back-capture"
        label="Edit"
        onPress={() => setStep('capture')}
      />
    </BottomActionBar>
  ) : step === 'success' ? (
    <BottomActionBar>
      <PrimaryButton testID="pod-continue" label="Finish trip" onPress={goActiveTrip} />
    </BottomActionBar>
  ) : null;

  return (
    <DriverScreen
      header={header}
      scroll
      padded
      testID="pod-screen"
      footer={footer}
      safeBottom={!footer}>
      <View style={styles.stack}>
        <View style={styles.headerInfoCard}>
          <Text style={styles.lead}>Collect Delivery Evidence</Text>
          {shipmentRef ? (
            <Text style={styles.shipmentMeta}>Shipment: {shipmentRef}</Text>
          ) : null}
          {deliveryLoc ? (
            <Text style={styles.meta}>Destination: {deliveryLoc}</Text>
          ) : null}
        </View>

        <PodProgress step={step} />

        {captureOpen ? (
          <View style={styles.section} testID="pod-capture">
            {!stationaryOk ? (
              <DriverCard testID="pod-stationary">
                <Text style={styles.cardTitle}>Safety check</Text>
                <Text style={styles.bodyText}>
                  Confirm the vehicle is stopped before capturing proof of delivery.
                </Text>
              </DriverCard>
            ) : (
              <>
                <SectionHeader label="Receiver Information" />
                <DriverCard>
                  <Text style={styles.inputLabel}>Receiver Name *</Text>
                  <TextInput
                    testID="pod-receiver-name"
                    style={styles.textInput}
                    placeholder="e.g. Michael Schneider"
                    placeholderTextColor={DriverTheme.colors.textMuted}
                    value={receiverName}
                    onChangeText={(val) => {
                      setReceiverName(val);
                      if (error) setError(null);
                    }}
                  />

                  <Text style={styles.inputLabel}>Receiver Role (Optional)</Text>
                  <TextInput
                    testID="pod-receiver-role"
                    style={styles.textInput}
                    placeholder="e.g. Warehouse Manager"
                    placeholderTextColor={DriverTheme.colors.textMuted}
                    value={receiverRole}
                    onChangeText={setReceiverRole}
                  />
                </DriverCard>

                <SectionHeader label="Delivery Condition" />
                <DriverCard>
                  <Text style={styles.meta}>Select cargo condition at delivery:</Text>
                  <View style={styles.conditionGrid}>
                    {CONDITION_OPTIONS.map((opt) => {
                      const selected = conditionStatus === opt.key;
                      return (
                        <Pressable
                          key={opt.key}
                          testID={`pod-condition-${opt.key}`}
                          style={[
                            styles.conditionCard,
                            selected && styles.conditionCardSelected,
                          ]}
                          onPress={() => setConditionStatus(opt.key)}>
                          <View style={styles.rowBetween}>
                            <Text
                              style={[
                                styles.conditionTitle,
                                selected && styles.conditionTitleSelected,
                              ]}>
                              {opt.label}
                            </Text>
                            {selected ? (
                              <StatusBadge label="Selected" tone="available" />
                            ) : null}
                          </View>
                          <Text style={styles.conditionDesc}>{opt.desc}</Text>
                        </Pressable>
                      );
                    })}
                  </View>
                </DriverCard>

                <SectionHeader label="Delivery Notes" />
                <DriverCard>
                  <TextInput
                    testID="pod-delivery-notes"
                    style={[styles.textInput, styles.textArea]}
                    placeholder="Optional delivery notes or exception remarks..."
                    placeholderTextColor={DriverTheme.colors.textMuted}
                    multiline
                    numberOfLines={3}
                    maxLength={1000}
                    value={deliveryNotes}
                    onChangeText={setDeliveryNotes}
                  />
                </DriverCard>

                <SectionHeader label="GPS Evidence Snapshot" />
                <DriverCard>
                  <View style={styles.rowBetween}>
                    <View style={styles.gpsRow}>
                      <MapPin size={18} color={DriverTheme.colors.primary} />
                      <Text style={styles.bodyText}>Location confirmation</Text>
                    </View>
                    <StatusBadge
                      label={gpsLocation ? 'Captured' : 'Unavailable'}
                      tone={gpsLocation ? 'available' : 'neutral'}
                    />
                  </View>
                  {gpsLocation ? (
                    <Text style={styles.meta}>
                      Coordinates: {gpsLocation.latitude.toFixed(6)}, {gpsLocation.longitude.toFixed(6)}
                    </Text>
                  ) : gpsWarning ? (
                    <View style={styles.warningBox}>
                      <AlertTriangle size={16} color="#F59E0B" />
                      <Text style={styles.warningText}>{gpsWarning}</Text>
                    </View>
                  ) : null}
                </DriverCard>

                <SectionHeader label="Photo Evidence *" />
                <DriverCard>
                  <View style={styles.rowBetween}>
                    <Text style={styles.sectionLabel}>Delivery photo</Text>
                    <StatusBadge
                      label={photoUri ? 'Captured' : 'Required'}
                      tone={photoUri ? 'available' : 'neutral'}
                    />
                  </View>
                  {photoUri ? (
                    <Image
                      source={{ uri: photoUri }}
                      style={styles.preview}
                      testID="pod-photo-preview"
                    />
                  ) : (
                    <View style={styles.photoEmpty}>
                      <Camera size={32} color={DriverTheme.colors.textMuted} />
                      <Text style={styles.meta}>No photo captured yet</Text>
                    </View>
                  )}
                  <PrimaryButton
                    testID="pod-capture-photo"
                    label={photoUri ? 'Retake photo' : 'Capture delivery photo'}
                    accessibilityLabel={photoUri ? 'Retake photo' : 'Capture delivery photo'}
                    onPress={() => void pickPhoto()}
                  />
                </DriverCard>

                <SectionHeader label="Recipient Signature *" />
                <DriverCard>
                  <View style={styles.rowBetween}>
                    <Text style={styles.sectionLabel}>Receiver Signature</Text>
                    <StatusBadge
                      label={strokes.length > 0 ? 'Captured' : 'Required'}
                      tone={strokes.length > 0 ? 'available' : 'neutral'}
                    />
                  </View>
                  <View style={styles.signatureHint}>
                    <PenLine size={16} color={DriverTheme.colors.textSecondary} />
                    <Text style={styles.meta}>Sign in the pad below with your finger.</Text>
                  </View>
                  <SignaturePad onChange={setStrokes} />
                </DriverCard>

                {error ? (
                  <Text testID="pod-error" style={styles.error}>
                    {error}
                  </Text>
                ) : null}
              </>
            )}
          </View>
        ) : null}

        {step === 'review' ? (
          <View style={styles.section} testID="pod-review">
            <SectionHeader label="Delivery Evidence Review" />
            <DriverCard>
              <Text style={styles.bodyText}>
                Review collected evidence before final submission:
              </Text>
              <View style={styles.reviewList}>
                <InfoRow label="Receiver" value={receiverName} />
                <InfoRow label="Role" value={receiverRole || 'Not specified'} />
                <InfoRow label="Condition" value={conditionStatus.toUpperCase()} />
                <InfoRow label="Notes" value={deliveryNotes || 'None'} />
                <InfoRow
                  label="GPS Location"
                  value={
                    gpsLocation
                      ? `${gpsLocation.latitude.toFixed(4)}, ${gpsLocation.longitude.toFixed(4)}`
                      : 'Unavailable'
                  }
                />
              </View>

              <View style={styles.summaryRow}>
                <StatusBadge label="Photo captured" tone="available" />
                <StatusBadge label="Signature captured" tone="available" />
              </View>

              {photoUri ? (
                <Image source={{ uri: photoUri }} style={styles.preview} />
              ) : null}
            </DriverCard>
          </View>
        ) : null}

        {step === 'submitting' ? (
          <View style={styles.section} testID="pod-submitting">
            <DriverCard>
              <ActivityIndicator size="large" color={DriverTheme.colors.primary} />
              <Text style={styles.bodyText}>Submitting delivery evidence…</Text>
            </DriverCard>
          </View>
        ) : null}

        {step === 'success' ? (
          <View style={styles.section} testID="pod-success">
            <DriverCard>
              <StatusBadge label="Submitted" tone="available" />
              <Text style={styles.cardTitle}>Delivery Evidence Submitted</Text>
              <Text style={styles.bodyText}>
                Proof of delivery is safely stored. Return to active trip to complete.
              </Text>
              {confirmedAt ? (
                <Text style={styles.meta}>Confirmed: {confirmedAt}</Text>
              ) : null}
            </DriverCard>
          </View>
        ) : null}
      </View>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  stack: {
    gap: DriverTheme.spacing.md,
  },
  section: {
    gap: DriverTheme.spacing.md,
  },
  headerInfoCard: {
    padding: DriverTheme.spacing.md,
    backgroundColor: DriverTheme.colors.surface,
    borderRadius: DriverTheme.radius.medium,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
  },
  lead: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
    marginBottom: 4,
  },
  shipmentMeta: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.primary,
    marginBottom: 2,
  },
  bodyText: {
    ...DriverTheme.typography.body,
    color: DriverTheme.colors.textPrimary,
  },
  meta: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  cardTitle: {
    ...DriverTheme.typography.cardTitle,
    color: DriverTheme.colors.textPrimary,
  },
  sectionLabel: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  inputLabel: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textPrimary,
    fontWeight: '700',
    marginTop: 6,
    marginBottom: 4,
  },
  textInput: {
    backgroundColor: '#111A2E',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    color: '#FFFFFF',
    fontSize: 15,
    marginBottom: 12,
  },
  textArea: {
    minHeight: 90,
    textAlignVertical: 'top',
  },
  conditionGrid: {
    gap: 10,
    marginTop: 10,
  },
  conditionCard: {
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    backgroundColor: '#111A2E',
  },
  conditionCardSelected: {
    borderColor: '#1E75FF',
    backgroundColor: 'rgba(30, 117, 255, 0.15)',
  },
  conditionTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#F8FAFC',
  },
  conditionTitleSelected: {
    color: '#60A5FA',
  },
  conditionDesc: {
    fontSize: 12,
    color: '#94A3B8',
    marginTop: 2,
  },
  gpsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  warningBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 6,
  },
  warningText: {
    fontSize: 12,
    color: '#D97706',
    flex: 1,
  },
  rowBetween: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: DriverTheme.spacing.md,
  },
  reviewList: {
    marginVertical: DriverTheme.spacing.md,
    gap: DriverTheme.spacing.xs,
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: DriverTheme.spacing.sm,
    marginBottom: DriverTheme.spacing.md,
  },
  signatureHint: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: DriverTheme.spacing.sm,
    marginBottom: 6,
  },
  photoEmpty: {
    minHeight: 180,
    borderRadius: DriverTheme.radius.medium,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: DriverTheme.colors.border,
    backgroundColor: DriverTheme.colors.background,
    alignItems: 'center',
    justifyContent: 'center',
    gap: DriverTheme.spacing.sm,
    marginVertical: 10,
  },
  preview: {
    width: '100%',
    height: 220,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.background,
    marginVertical: 10,
  },
  error: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.danger,
    marginTop: 6,
  },
  progress: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginVertical: DriverTheme.spacing.sm,
  },
  progressStep: {
    flex: 1,
    alignItems: 'center',
    gap: DriverTheme.spacing.xs,
  },
  progressTrack: {
    flexDirection: 'row',
    alignItems: 'center',
    width: '100%',
    height: 16,
  },
  progressLine: {
    flex: 1,
    height: 2,
  },
  progressDot: {
    width: 14,
    height: 14,
    borderRadius: 7,
  },
  progressDotActive: {
    width: 16,
    height: 16,
    borderRadius: 8,
    borderWidth: 3,
    borderColor: DriverTheme.colors.infoSoft,
  },
  progressLabel: {
    ...DriverTheme.typography.caption,
    fontWeight: '700',
    textAlign: 'center',
  },
});
