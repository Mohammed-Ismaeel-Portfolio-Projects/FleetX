/**
 * C2.9-C3.10 — Customer-facing Messages inbox.
 * Lists authorized conversations only — never creates threads.
 */
import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { MessageSquare } from 'lucide-react-native';

import { GradientBackground } from '@/components/product';
import { UnavailableDomainScreen } from '@/components/unavailable-domain';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import { listConversations, MessagingApiError } from '@/features/messaging/messagingApi';
import type { Conversation } from '@/features/messaging/types';
import {
  isRetryableLoadFailure,
  screenErrorTitle,
} from '@/lib/ux/customerErrors';
import { ScreenEmpty, ScreenError } from '@/lib/ux/ScreenState';

function conversationTitle(item: Conversation): string {
  const shipmentNumber = item.shipment?.shipmentNumber?.trim();
  if (shipmentNumber) return `Shipment ${shipmentNumber}`;
  return 'FleetX Support';
}

function conversationStatusLabel(status: string | null | undefined): string {
  const raw = (status ?? '').trim().toLowerCase();
  if (raw === 'open') return 'Open';
  if (raw === 'closed') return 'Closed';
  if (!raw) return 'Conversation';
  return raw.charAt(0).toUpperCase() + raw.slice(1);
}

function ConversationRow({
  item,
  onPress,
  onShipmentPress,
}: {
  item: Conversation;
  onPress: () => void;
  onShipmentPress?: () => void;
}) {
  const theme = useTheme();
  const title = conversationTitle(item);
  const statusLabel = conversationStatusLabel(item.status);
  const updated =
    item.updatedAt != null ? new Date(item.updatedAt).toLocaleString() : null;

  return (
    <Pressable
      testID={`conversation-row-${item.id}`}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${title}. ${statusLabel} conversation with FleetX Support.${
        updated ? ` Updated ${updated}.` : ''
      }`}
      accessibilityHint="Opens this support conversation"
      style={({ pressed }) => [
        styles.row,
        {
          backgroundColor: theme.backgroundElement,
          borderColor: theme.border,
          opacity: pressed ? 0.9 : 1,
        },
      ]}>
      <Text style={[styles.rowTitle, { color: theme.text }]} numberOfLines={1}>
        {title}
      </Text>
      <Text style={[styles.rowMeta, { color: theme.textSecondary }]}>FleetX Support</Text>
      <Text style={[styles.rowMeta, { color: theme.textSecondary }]}>Status: {statusLabel}</Text>
      {updated ? (
        <Text style={[styles.rowMeta, { color: theme.textSecondary }]}>Updated: {updated}</Text>
      ) : null}
      {onShipmentPress && item.shipmentId ? (
        <Pressable
          testID={`shipment-handoff-${item.shipmentId}`}
          onPress={(e) => {
            e.stopPropagation?.();
            onShipmentPress();
          }}
          accessibilityRole="button"
          accessibilityLabel="Open shipment communication"
          style={styles.handoff}>
          <Text style={[styles.handoffText, { color: theme.primary }]}>Shipment communication</Text>
        </Pressable>
      ) : null}
    </Pressable>
  );
}

export default function MessagesScreen() {
  const theme = useTheme();
  const { roleOk, identityLinked } = useAuth();
  const router = useRouter();
  const [items, setItems] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | undefined>(undefined);
  const [errorIsNetwork, setErrorIsNetwork] = useState(false);

  /** C3.13 — supersede stale Messages loads (focus / Retry / pull-to-refresh). */
  const inFlightRef = useRef(false);
  const requestSeqRef = useRef(0);

  const load = useCallback(
    async (isRefresh = false) => {
      if (!roleOk || !identityLinked) {
        setItems([]);
        setLoading(false);
        setRefreshing(false);
        inFlightRef.current = false;
        return;
      }

      // Allow Retry / refresh to supersede an in-flight focus load via sequence bump.
      const requestSeq = ++requestSeqRef.current;
      inFlightRef.current = true;

      if (isRefresh) setRefreshing(true);
      else setLoading(true);
      setError(null);
      setErrorStatus(undefined);
      setErrorIsNetwork(false);
      try {
        const { conversations } = await listConversations({
          type: 'dispatcher_customer',
          per_page: 50,
        });
        if (requestSeq !== requestSeqRef.current) return;
        const sorted = [...conversations].sort((a, b) => {
          if (a.status === 'open' && b.status !== 'open') return -1;
          if (b.status === 'open' && a.status !== 'open') return 1;
          const at = a.updatedAt ? Date.parse(a.updatedAt) : 0;
          const bt = b.updatedAt ? Date.parse(b.updatedAt) : 0;
          return bt - at;
        });
        setItems(sorted);
      } catch (e) {
        if (requestSeq !== requestSeqRef.current) return;
        const err = e as MessagingApiError;
        if (err.statusCode === 401) {
          // Shared interceptor + AuthContext own session expiry.
          setItems([]);
          return;
        }
        setItems([]);
        setError(err.message || 'Could not load messages');
        setErrorStatus(err.statusCode);
        setErrorIsNetwork(Boolean(err.isNetwork));
      } finally {
        if (requestSeq === requestSeqRef.current) {
          inFlightRef.current = false;
          setLoading(false);
          setRefreshing(false);
        }
      }
    },
    [roleOk, identityLinked]
  );

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  if (!roleOk) {
    return (
      <UnavailableDomainScreen
        title="Messages"
        testID="messages-forbidden-role"
        icon={MessageSquare}
        explanation="Messages are only available for FleetX customer accounts."
      />
    );
  }

  if (!identityLinked) {
    return (
      <UnavailableDomainScreen
        title="Messages"
        testID="messages-unlinked"
        icon={MessageSquare}
        explanation="Your account is not linked to a company. Conversations cannot be loaded until company linking is available."
      />
    );
  }

  if (loading) {
    return (
      <GradientBackground>
        <View style={styles.center} testID="messages-loading">
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.muted, { color: theme.textSecondary }]}>Loading messages…</Text>
        </View>
      </GradientBackground>
    );
  }

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.container} testID="messages-screen">
        <Text style={[styles.screenTitle, { color: theme.text }]}>Messages</Text>
        <Text style={[styles.screenSubtitle, { color: theme.textSecondary }]}>
          Conversations with FleetX Support for your shipments
        </Text>
        {error ? (
          <ScreenError
            testID="messages-error"
            retryTestID="messages-retry"
            theme={theme}
            title={screenErrorTitle({
              statusCode: errorStatus,
              isNetwork: errorIsNetwork,
              loadFallbackTitle: 'Could not load messages',
            })}
            body={error}
            onRetry={
              isRetryableLoadFailure(errorStatus, errorIsNetwork)
                ? () => void load(false)
                : null
            }
          />
        ) : null}
        <FlatList
          data={error ? [] : items}
          keyExtractor={(item) => item.id}
          contentContainerStyle={!error && items.length === 0 ? styles.emptyList : styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => void load(true)} />
          }
          ListEmptyComponent={
            error ? null : (
              <ScreenEmpty
                testID="messages-empty"
                theme={theme}
                title="No conversations yet"
                body="Conversations appear when FleetX Support opens a thread for one of your company shipments."
              />
            )
          }
          renderItem={({ item }) => (
            <ConversationRow
              item={item}
              onPress={() => router.push(`/conversation/${item.id}`)}
              onShipmentPress={
                item.shipmentId
                  ? () => router.push(`/shipment/${item.shipmentId}`)
                  : undefined
              }
            />
          )}
          ListFooterComponent={
            items.length > 0 ? (
              <Text style={[styles.footerHint, { color: theme.textSecondary }]}>
                Opening a conversation uses an existing authorized thread only — never creates a new
                one.
              </Text>
            ) : null
          }
        />
      </View>
    </GradientBackground>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    minHeight: 240,
  },
  screenTitle: {
    fontSize: 22,
    fontWeight: '800',
    marginTop: 8,
  },
  screenSubtitle: {
    fontSize: 13,
    lineHeight: 18,
    marginTop: 6,
    marginBottom: 16,
  },
  list: { paddingBottom: 24, gap: 10 },
  emptyList: { flexGrow: 1 },
  empty: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
    gap: 8,
    minHeight: 200,
  },
  emptyTitle: { fontSize: 16, fontWeight: '700', textAlign: 'center' },
  row: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1.5,
    marginBottom: 10,
    gap: 4,
    minHeight: 52,
  },
  rowTitle: { fontSize: 15, fontWeight: '700' },
  rowMeta: { fontSize: 13 },
  muted: { fontSize: 13, textAlign: 'center', lineHeight: 18 },
  errorBlock: { marginBottom: 12, gap: 10 },
  error: { color: '#B42318', fontSize: 13 },
  retryBtn: {
    alignSelf: 'flex-start',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
  },
  retryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 13 },
  handoff: { marginTop: 8, paddingVertical: 8, minHeight: 44, justifyContent: 'center' },
  handoffText: { fontWeight: '700', fontSize: 13 },
  footerHint: { fontSize: 12, lineHeight: 17, paddingVertical: 12, textAlign: 'center' },
});
