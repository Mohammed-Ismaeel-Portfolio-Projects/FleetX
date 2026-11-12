import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import {
  failDelivery,
  reportIssue,
  type DriverIssueTypeCode,
} from '@/features/driverApi/work';
import { DriverWorkApiError } from '@/features/driverApi/workTypes';

const REASONS: { code: DriverIssueTypeCode; label: string }[] = [
  { code: 'traffic', label: 'Traffic' },
  { code: 'traffic_light', label: 'Traffic light' },
  { code: 'vehicle_problem', label: 'Vehicle problem' },
  { code: 'accident', label: 'Accident' },
  { code: 'loading_problem', label: 'Loading problem' },
  { code: 'delivery_problem', label: 'Delivery problem' },
  { code: 'other', label: 'Other' },
];

type Props = {
  visible: boolean;
  assignmentId: string;
  mode: 'issue' | 'fail';
  onClose: () => void;
  onReported: () => void;
};

/**
 * Driver issue / fail-delivery sheet (D7). Requires stationary confirmation.
 */
export function ReportIssueSheet({
  visible,
  assignmentId,
  mode,
  onClose,
  onReported,
}: Props) {
  const [type, setType] = useState<DriverIssueTypeCode | null>(null);
  const [description, setDescription] = useState('');
  const [stationary, setStationary] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const submitLock = useRef(false);

  const reset = useCallback(() => {
    setType(null);
    setDescription('');
    setStationary(false);
    setSubmitting(false);
    setError(null);
    setDone(false);
  }, []);

  const close = () => {
    reset();
    onClose();
  };

  const submit = async () => {
    if (!type || !stationary || submitting || submitLock.current) return;
    if (type === 'other' && !description.trim()) {
      setError('Please describe the issue.');
      return;
    }
    submitLock.current = true;
    setSubmitting(true);
    setError(null);
    try {
      if (mode === 'fail') {
        await failDelivery(assignmentId, type, description.trim() || undefined);
      } else {
        await reportIssue(assignmentId, type, description.trim() || undefined);
      }
      setSubmitting(false);
      setDone(true);
    } catch (e) {
      setError(e instanceof DriverWorkApiError ? e.message : 'Could not submit.');
      setSubmitting(false);
    } finally {
      submitLock.current = false;
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={close}>
      <View style={styles.backdrop} testID="report-issue-sheet">
        <View style={styles.sheet}>
          {done ? (
            <View testID="issue-success" style={styles.success}>
              <ThemedText type="subtitle">
                {mode === 'fail' ? 'Failed delivery reported' : 'Exception reported'}
              </ThemedText>
              <ThemedText style={styles.muted}>
                {mode === 'fail'
                  ? 'The shipment remains undelivered. Your dispatcher has been notified.'
                  : 'Your dispatcher has been notified. Shipment status was not changed.'}
              </ThemedText>
              <Pressable
                testID="issue-success-done"
                onPress={() => {
                  reset();
                  onReported();
                }}
                style={styles.cta}
                accessibilityRole="button"
                accessibilityLabel="Done">
                <ThemedText style={styles.ctaLabel}>Done</ThemedText>
              </Pressable>
            </View>
          ) : (
            <>
          <ThemedText type="subtitle">
            {mode === 'fail' ? 'Report failed delivery' : 'Report an issue'}
          </ThemedText>
          <ThemedText style={styles.muted}>
            {mode === 'fail'
              ? 'This does not mark the shipment delivered. Dispatcher will decide next steps.'
              : 'Ops will review. This does not change shipment status by itself.'}
          </ThemedText>

          <ScrollView style={styles.list}>
            {REASONS.map((r) => (
              <Pressable
                key={r.code}
                testID={`issue-reason-${r.code}`}
                onPress={() => setType(r.code)}
                style={[styles.reason, type === r.code && styles.reasonActive]}
                accessibilityRole="button"
                accessibilityLabel={r.label}
                accessibilityState={{ selected: type === r.code }}>
                <ThemedText>{r.label}</ThemedText>
              </Pressable>
            ))}
          </ScrollView>

          {(type === 'other' || description.length > 0 || type) && (
            <TextInput
              testID="issue-description"
              value={description}
              onChangeText={setDescription}
              placeholder={type === 'other' ? 'Describe the issue (required)' : 'Optional details'}
              placeholderTextColor="#888"
              multiline
              style={styles.input}
            />
          )}

          <Pressable
            testID="issue-stationary-confirm"
            onPress={() => setStationary((v) => !v)}
            style={[styles.reason, stationary && styles.reasonActive]}
            accessibilityRole="button"
            accessibilityLabel="I confirm I am stationary"
            accessibilityState={{ selected: stationary }}>
            <ThemedText>I confirm I am stationary</ThemedText>
          </Pressable>

          {error ? (
            <ThemedText testID="issue-error" style={styles.error}>
              {error}
            </ThemedText>
          ) : null}

          <Pressable
            testID="issue-submit"
            disabled={!type || !stationary || submitting}
            onPress={() => void submit()}
            style={[styles.cta, (!type || !stationary || submitting) && styles.disabled]}>
            {submitting ? (
              <ActivityIndicator color="#fff" testID="issue-submit-loading" />
            ) : (
              <ThemedText style={styles.ctaLabel}>Submit</ThemedText>
            )}
          </Pressable>

          <Pressable testID="issue-cancel" onPress={close} style={styles.link}
            accessibilityRole="button"
            accessibilityLabel="Cancel">
            <ThemedText>Cancel</ThemedText>
          </Pressable>
            </>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  sheet: {
    maxHeight: '88%',
    backgroundColor: '#1a1a1a',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: Spacing.four,
    gap: Spacing.two,
  },
  muted: { opacity: 0.7 },
  list: { maxHeight: 220 },
  reason: {
    minHeight: 48,
    borderRadius: 10,
    paddingHorizontal: Spacing.three,
    justifyContent: 'center',
    marginBottom: 6,
    backgroundColor: 'rgba(127,127,127,0.18)',
  },
  reasonActive: { backgroundColor: 'rgba(27,79,114,0.55)' },
  input: {
    minHeight: 72,
    borderRadius: 10,
    padding: Spacing.three,
    color: '#fff',
    backgroundColor: 'rgba(127,127,127,0.18)',
    textAlignVertical: 'top',
  },
  cta: {
    minHeight: 56,
    borderRadius: 12,
    backgroundColor: '#1B4F72',
    alignItems: 'center',
    justifyContent: 'center',
  },
  disabled: { opacity: 0.5 },
  ctaLabel: { color: '#fff', fontWeight: '700', fontSize: 17 },
  link: { minHeight: 44, justifyContent: 'center', alignItems: 'center' },
  error: { color: '#B42318' },
  success: { gap: Spacing.three },
});
