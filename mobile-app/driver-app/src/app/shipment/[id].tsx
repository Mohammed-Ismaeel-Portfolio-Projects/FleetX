import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { findAuthorizedConversationForShipment } from '@/features/messaging/shipmentHandoff';
import type { Conversation } from '@/features/messaging/types';

/**
 * Shipment → Communication handoff.
 * Opens an existing authorized conversation only — never auto-creates.
 */
export default function ShipmentCommunicationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const shipmentId = String(id || '');
  const router = useRouter();
  const { roleOk, identityLinked } = useAuth();
  const [loading, setLoading] = useState(true);
  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [error, setError] = useState<string | null>(null);

  const conversationType = 'dispatcher_driver' as const;

  const load = useCallback(async () => {
    if (!roleOk || !identityLinked) {
      setError('Messaging unavailable for this account.');
      setLoading(false);
      return;
    }
    if (!shipmentId) {
      setError('Shipment id unavailable.');
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const found = await findAuthorizedConversationForShipment(shipmentId, conversationType);
      setConversation(found);
      if (!found) {
        setError(
          'No authorized conversation exists for this shipment yet. A dispatcher must open the thread first.'
        );
      }
    } catch (e) {
      setConversation(null);
      setError(e instanceof Error ? e.message : 'Failed to resolve conversation');
    } finally {
      setLoading(false);
    }
  }, [shipmentId, roleOk, identityLinked, conversationType]);

  useEffect(() => {
    const initial = setTimeout(() => void load(), 0);
    return () => clearTimeout(initial);
  }, [load]);

  return (
    <ThemedView style={styles.container} testID="shipment-handoff-screen">
      <SafeAreaView style={styles.safe}>
        <ThemedText type="subtitle">Communication</ThemedText>
        <ThemedText style={styles.muted}>Shipment: {shipmentId || 'Unavailable'}</ThemedText>

        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator />
          </View>
        ) : conversation ? (
          <View style={styles.card}>
            <ThemedText type="smallBold">{conversation.type}</ThemedText>
            <ThemedText>Status: {conversation.status}</ThemedText>
            <ThemedText style={styles.muted}>
              {conversation.shipment?.shipmentNumber ?? conversation.shipmentId}
            </ThemedText>
          <Pressable
            style={styles.button}
            testID="shipment-open-conversation"
            onPress={() => router.navigate(`/conversation/${conversation.id}`)}>
              <ThemedText style={styles.buttonText}>Open conversation</ThemedText>
            </Pressable>
          </View>
        ) : (
          <View style={[styles.card, styles.warn]} testID="shipment-unavailable">
            <ThemedText type="smallBold">Unavailable</ThemedText>
            <ThemedText>{error || 'No authorized conversation.'}</ThemedText>
            <Pressable style={styles.secondary} onPress={() => void load()}>
              <ThemedText>Retry</ThemedText>
            </Pressable>
          </View>
        )}
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  safe: { flex: 1, padding: Spacing.four, gap: Spacing.three },
  muted: { opacity: 0.65 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: {
    gap: Spacing.two,
    padding: Spacing.three,
    borderRadius: 12,
    backgroundColor: 'rgba(127,127,127,0.12)',
  },
  warn: { backgroundColor: 'rgba(180,35,24,0.12)' },
  button: {
    marginTop: Spacing.two,
    backgroundColor: '#1B4F72',
    borderRadius: 10,
    paddingVertical: Spacing.three,
    alignItems: 'center',
  },
  buttonText: { color: '#fff', fontWeight: '600' },
  secondary: { marginTop: Spacing.two, alignItems: 'center', padding: Spacing.two },
});
