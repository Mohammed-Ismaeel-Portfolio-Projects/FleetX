import { Redirect } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { ThemedView } from '@/components/themed-view';
import { useAuth } from '@/lib/auth/AuthContext';

/** Entry redirect — authenticated users land on tabs (Work/Messages/Account). */
export default function Index() {
  const { ready, isAuthenticated, bootError } = useAuth();

  if (!ready) {
    return (
      <ThemedView style={styles.center}>
        <ActivityIndicator />
      </ThemedView>
    );
  }

  // Boot restore errors are handled on Login (retry / dismiss).
  if (bootError) {
    return <Redirect href="/login" />;
  }

  if (isAuthenticated) {
    return <Redirect href="/(tabs)" />;
  }

  return <Redirect href="/login" />;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
