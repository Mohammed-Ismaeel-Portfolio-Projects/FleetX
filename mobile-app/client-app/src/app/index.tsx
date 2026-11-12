import { Redirect } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity } from 'react-native';

import { ThemedView } from '@/components/themed-view';
import { useAuth } from '@/lib/auth/AuthContext';
import { useTheme } from '@/hooks/use-theme';
import { useState } from 'react';

/** Entry redirect — authenticated users land on tabs after session validation. */
export default function Index() {
  const theme = useTheme();
  const { ready, isAuthenticated, bootError, retrySessionRestore, dismissBootError } = useAuth();
  const [retrying, setRetrying] = useState(false);

  if (!ready) {
    return (
      <ThemedView style={styles.center} testID="session-gate-loading">
        <ActivityIndicator />
      </ThemedView>
    );
  }

  if (bootError) {
    return (
      <ThemedView style={styles.centerPad} testID="session-gate-boot-error">
        <Text style={styles.title}>Session restore failed</Text>
        <Text style={styles.body}>{bootError}</Text>
        <TouchableOpacity
          testID="session-gate-retry"
          style={[styles.retryBtn, { backgroundColor: theme.primary }]}
          disabled={retrying}
          accessibilityRole="button"
          accessibilityLabel="Retry session restore"
          accessibilityHint="Attempts to restore your signed-in session"
          accessibilityState={{ disabled: retrying, busy: retrying }}
          onPress={() => {
            if (retrying) return;
            setRetrying(true);
            void retrySessionRestore().finally(() => setRetrying(false));
          }}>
          <Text style={styles.retryText}>{retrying ? 'Retrying…' : 'Retry'}</Text>
        </TouchableOpacity>
        <TouchableOpacity
          testID="session-gate-dismiss"
          onPress={() => void dismissBootError()}
          style={styles.link}
          accessibilityRole="button"
          accessibilityLabel="Continue to sign in">
          <Text style={{ color: theme.textSecondary }}>Continue to sign in</Text>
        </TouchableOpacity>
      </ThemedView>
    );
  }

  if (isAuthenticated) {
    return <Redirect href="/(tabs)" />;
  }

  return <Redirect href="/login" />;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  centerPad: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  title: {
    color: '#F8FAFC',
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
    textAlign: 'center',
  },
  body: {
    color: '#94A3B8',
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 16,
  },
  retryBtn: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 12,
    marginBottom: 12,
  },
  retryText: { color: '#FFFFFF', fontWeight: '700' },
  link: { padding: 8 },
});
