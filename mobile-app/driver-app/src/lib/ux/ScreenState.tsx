/**
 * D1 — Presentational primitives for loading / empty / error blocks.
 */
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

type ThemeBits = {
  text: string;
  textSecondary: string;
  primary: string;
};

export function ScreenLoading({
  label,
  theme,
  testID,
}: {
  label: string;
  theme: ThemeBits;
  testID?: string;
}) {
  return (
    <View style={styles.center} testID={testID} accessibilityRole="progressbar" accessibilityLabel={label}>
      <ActivityIndicator color={theme.primary} />
      <Text style={[styles.muted, { color: theme.textSecondary }]}>{label}</Text>
    </View>
  );
}

export function ScreenEmpty({
  title,
  body,
  theme,
  testID,
}: {
  title: string;
  body?: string;
  theme: ThemeBits;
  testID?: string;
}) {
  return (
    <View style={styles.empty} testID={testID} accessibilityRole="summary">
      <Text style={[styles.emptyTitle, { color: theme.text }]}>{title}</Text>
      {body ? <Text style={[styles.muted, { color: theme.textSecondary }]}>{body}</Text> : null}
    </View>
  );
}

export function ScreenError({
  title,
  body,
  onRetry,
  theme,
  testID,
  retryTestID,
}: {
  title: string;
  body: string;
  onRetry?: (() => void) | null;
  theme: ThemeBits;
  testID?: string;
  retryTestID?: string;
}) {
  return (
    <View style={styles.errorBlock} testID={testID} accessibilityRole="summary">
      <Text style={[styles.errorTitle, { color: theme.text }]}>{title}</Text>
      <Text style={[styles.errorBody, { color: theme.textSecondary }]}>{body}</Text>
      {onRetry ? (
        <Pressable
          testID={retryTestID}
          onPress={onRetry}
          style={[styles.retryBtn, { backgroundColor: theme.primary }]}
          accessibilityRole="button"
          accessibilityLabel="Retry"
          accessibilityHint="Attempts to load this information again">
          <Text style={styles.retryText}>Retry</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export function ScreenBlocked({
  title,
  body,
  theme,
  testID,
  actionLabel,
  onAction,
  actionTestID,
}: {
  title: string;
  body: string;
  theme: ThemeBits;
  testID?: string;
  actionLabel?: string;
  onAction?: () => void;
  actionTestID?: string;
}) {
  return (
    <View style={styles.empty} testID={testID} accessibilityRole="summary">
      <Text style={[styles.emptyTitle, { color: theme.text }]}>{title}</Text>
      <Text style={[styles.muted, { color: theme.textSecondary }]}>{body}</Text>
      {onAction && actionLabel ? (
        <Pressable
          testID={actionTestID}
          onPress={onAction}
          style={[styles.retryBtn, { backgroundColor: theme.primary, alignSelf: 'center', marginTop: 12 }]}
          accessibilityRole="button"
          accessibilityLabel={actionLabel}>
          <Text style={styles.retryText}>{actionLabel}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    paddingHorizontal: 24,
  },
  muted: { fontSize: 13, textAlign: 'center', lineHeight: 18 },
  empty: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingVertical: 32,
    gap: 8,
  },
  emptyTitle: { fontSize: 16, fontWeight: '700', textAlign: 'center' },
  errorBlock: {
    marginBottom: 12,
    gap: 8,
    paddingHorizontal: 4,
  },
  errorTitle: { fontSize: 16, fontWeight: '700' },
  errorBody: { fontSize: 13, lineHeight: 18 },
  retryBtn: {
    alignSelf: 'flex-start',
    minHeight: 44,
    paddingHorizontal: 16,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  retryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 13 },
});
