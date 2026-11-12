/**
 * C2.9-C3.10 — Shipment → Support communication handoff.
 * Opens an existing authorized conversation only — never auto-creates.
 */
import { useLocalSearchParams, useRouter, Redirect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, MessageSquare } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { findAuthorizedConversationForShipment } from '@/features/messaging/shipmentHandoff';
import type { Conversation } from '@/features/messaging/types';

export default function ShipmentCommunicationScreen() {
  const theme = useTheme();
  const { id } = useLocalSearchParams<{ id: string }>();
  const shipmentId = String(id || '');
  const router = useRouter();
  const { roleOk, identityLinked, ready, isAuthenticated } = useAuth();
  const [loading, setLoading] = useState(true);
  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [error, setError] = useState<string | null>(null);

  const conversationType = 'dispatcher_customer' as const;

  const load = useCallback(async () => {
    if (!roleOk || !identityLinked) {
      setError('Messaging is unavailable for this account.');
      setLoading(false);
      return;
    }
    if (!shipmentId) {
      setError('Shipment is unavailable.');
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
          'Messaging is not currently available for this shipment. FleetX Support must open the conversation first.'
        );
      }
    } catch (e) {
      setConversation(null);
      setError(
        e instanceof Error ? e.message : 'Could not open messaging for this shipment.'
      );
    } finally {
      setLoading(false);
    }
  }, [shipmentId, roleOk, identityLinked, conversationType]);

  useEffect(() => {
    void load();
  }, [load]);

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  const shipmentLabel =
    conversation?.shipment?.shipmentNumber?.trim() ||
    (shipmentId ? 'Your shipment' : null);

  return (
    <GradientBackground>
      <View style={styles.screen} testID="shipment-handoff-screen">
        <Pressable
          testID="shipment-handoff-back"
          onPress={() => {
            if (router.canGoBack()) router.back();
            else router.replace('/(tabs)/messages');
          }}
          accessibilityRole="button"
          accessibilityLabel="Go back"
          style={[styles.backBtn, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
          <ChevronLeft size={20} color={theme.text} />
        </Pressable>

        <Text style={[styles.title, { color: theme.text }]}>Shipment communication</Text>
        <Text style={[styles.subtitle, { color: theme.textSecondary }]}>
          Contact FleetX Support using an authorized conversation for this shipment.
        </Text>

        {loading ? (
          <View style={styles.center} testID="shipment-handoff-loading">
            <ActivityIndicator color={theme.primary} />
            <Text style={[styles.muted, { color: theme.textSecondary }]}>Checking messages…</Text>
          </View>
        ) : conversation ? (
          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
            <View style={styles.cardIconRow}>
              <MessageSquare size={18} color={theme.primary} />
              <Text style={[styles.cardTitle, { color: theme.text }]}>FleetX Support</Text>
            </View>
            {shipmentLabel ? (
              <Text style={[styles.cardMeta, { color: theme.textSecondary }]}>{shipmentLabel}</Text>
            ) : null}
            <Text style={[styles.cardMeta, { color: theme.textSecondary }]}>
              Status: {conversation.status === 'open' ? 'Open' : conversation.status || 'Conversation'}
            </Text>
            <Pressable
              style={[styles.primaryBtn, { backgroundColor: theme.primary }]}
              testID="shipment-open-conversation"
              onPress={() => router.navigate(`/conversation/${conversation.id}`)}
              accessibilityRole="button"
              accessibilityLabel="Contact FleetX Support"
              accessibilityHint="Opens the authorized support conversation">
              <Text style={styles.primaryBtnText}>Contact FleetX Support</Text>
            </Pressable>
          </View>
        ) : (
          <View
            style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
            testID="shipment-unavailable">
            <Text style={[styles.cardTitle, { color: theme.text }]}>Messaging unavailable</Text>
            <Text style={[styles.cardBody, { color: theme.textSecondary }]}>
              {error || 'No authorized conversation is available for this shipment.'}
            </Text>
            <Pressable
              style={[styles.secondaryBtn, { borderColor: theme.border }]}
              onPress={() => void load()}
              accessibilityRole="button"
              accessibilityLabel="Retry loading messaging">
              <Text style={[styles.secondaryBtnText, { color: theme.primary }]}>Retry</Text>
            </Pressable>
          </View>
        )}
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  backBtn: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  title: { fontSize: 22, fontWeight: '800' },
  subtitle: { fontSize: 13, lineHeight: 18, marginTop: 6, marginBottom: 20 },
  center: { alignItems: 'center', justifyContent: 'center', gap: 12, paddingVertical: 40 },
  muted: { fontSize: 13 },
  card: {
    borderRadius: 16,
    borderWidth: 1.5,
    padding: 16,
    gap: 10,
  },
  cardIconRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  cardTitle: { fontSize: 16, fontWeight: '700' },
  cardMeta: { fontSize: 13 },
  cardBody: { fontSize: 14, lineHeight: 20 },
  primaryBtn: {
    marginTop: 8,
    minHeight: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 15 },
  secondaryBtn: {
    marginTop: 8,
    minHeight: 44,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondaryBtnText: { fontWeight: '700', fontSize: 14 },
});
