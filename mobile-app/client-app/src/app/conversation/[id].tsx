import { useLocalSearchParams, useNavigation, useRouter, Redirect } from 'expo-router';
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { GradientBackground } from '@/components/product';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  getConversation,
  loadNewestMessages,
  loadOlderMessages,
  markConversationRead,
  MessagingApiError,
  sendMessage,
} from '@/features/messaging/messagingApi';
import { formatMessageTime, mergeMessagesById } from '@/features/messaging/messageMerge';
import {
  isMessagingRealtimeEnabled,
  mapRealtimeMessage,
  mobileRealtime,
  type MessageCreatedPayload,
} from '@/features/messaging/realtime';
import type { Conversation, Message, RealtimeStatus } from '@/features/messaging/types';
import {
  ClientUxCopy,
  screenErrorTitle,
} from '@/lib/ux/customerErrors';

function statusLabel(status: RealtimeStatus): string {
  switch (status) {
    case 'connected':
      return 'Connected';
    case 'connecting':
      return 'Connecting';
    case 'reconnecting':
      return 'Reconnecting';
    case 'rest_fallback':
      return 'REST fallback';
    case 'disabled':
      return 'Realtime off';
    case 'permission_denied':
      return 'Channel denied';
    case 'error':
      return 'Realtime error';
    default:
      return 'Disconnected';
  }
}

export default function ConversationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const conversationId = String(id);
  const { user, roleOk, identityLinked, ready, isAuthenticated } = useAuth();
  const navigation = useNavigation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const theme = useTheme();

  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [oldestPage, setOldestPage] = useState(1);
  const [hasOlder, setHasOlder] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [errorKind, setErrorKind] = useState<
    'unauthorized' | 'unavailable' | 'network' | 'error' | null
  >(null);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [sendError, setSendError] = useState<string | null>(null);
  const [rtStatus, setRtStatus] = useState<RealtimeStatus>(
    isMessagingRealtimeEnabled() ? 'connecting' : 'disabled'
  );
  const [loadNonce, setLoadNonce] = useState(0);

  const activeIdRef = useRef(conversationId);
  activeIdRef.current = conversationId;
  const listRef = useRef<FlatList<Message>>(null);
  const sendLock = useRef(false);
  const unsubscribeRef = useRef<(() => void) | undefined>(undefined);

  useLayoutEffect(() => {
    navigation.setOptions({
      title: conversation?.shipment?.shipmentNumber
        ? `Shipment ${conversation.shipment.shipmentNumber}`
        : 'FleetX Support',
    });
  }, [navigation, conversation]);

  const reconcile = useCallback(async () => {
    if (!conversationId || activeIdRef.current !== conversationId) return;
    try {
      const newest = await loadNewestMessages(conversationId, 50);
      if (activeIdRef.current !== conversationId) return;
      setMessages((prev) => mergeMessagesById(prev, newest.messages));
      setOldestPage(newest.oldestLoadedPage);
      setHasOlder(newest.hasOlder);
    } catch {
      // keep existing messages; honest refresh failure via pull-to-refresh
    }
  }, [conversationId]);

  const handleRealtimePayload = useCallback((payload: MessageCreatedPayload) => {
    if (payload.conversation_id && payload.conversation_id !== activeIdRef.current) {
      return;
    }
    const mapped = mapRealtimeMessage(payload);
    if (!mapped) return;
    if (mapped.conversationId !== activeIdRef.current) return;
    setMessages((prev) => mergeMessagesById(prev, mapped));
  }, []);

  const subscribeActive = useCallback(async () => {
    if (!isMessagingRealtimeEnabled()) return;
    unsubscribeRef.current?.();
    unsubscribeRef.current = await mobileRealtime.subscribeConversation(
      conversationId,
      handleRealtimePayload,
      () => {
        // REST remains available — status comes from onStatus
      }
    );
  }, [conversationId, handleRealtimePayload]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (!roleOk || !identityLinked) {
        setError('Messaging unavailable for this account.');
        setErrorKind('unavailable');
        setLoading(false);
        return;
      }
      setLoading(true);
      setError(null);
      setErrorKind(null);
      try {
        const conv = await getConversation(conversationId);
        if (cancelled) return;
        setConversation(conv);
        const newest = await loadNewestMessages(conversationId, 50);
        if (cancelled) return;
        setMessages(newest.messages);
        setOldestPage(newest.oldestLoadedPage);
        setHasOlder(newest.hasOlder);
        void markConversationRead(conversationId).catch(() => undefined);
      } catch (e) {
        if (!cancelled) {
          const err = e as MessagingApiError;
          const status = err.statusCode;
          if (status === 403 || status === 401) {
            setErrorKind('unauthorized');
            setError(err.message || 'You do not have access to this conversation.');
          } else if (status === 404) {
            setErrorKind('unavailable');
            setError(err.message || 'This conversation is unavailable.');
          } else {
            setErrorKind(err.isNetwork ? 'network' : 'error');
            setError(err.message || 'Could not open this conversation.');
          }
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [conversationId, roleOk, identityLinked, loadNonce]);

  useEffect(() => {
    const offStatus = mobileRealtime.onStatus(setRtStatus);
    let cancelled = false;

    (async () => {
      await subscribeActive();
      if (cancelled) unsubscribeRef.current?.();
    })();

    // Single AppState owner is realtime service — screens only react via onForeground.
    const offForeground = mobileRealtime.onForeground(() => {
      if (activeIdRef.current !== conversationId) return;
      void reconcile();
      void subscribeActive();
    });

    return () => {
      cancelled = true;
      offStatus();
      offForeground();
      unsubscribeRef.current?.();
      unsubscribeRef.current = undefined;
    };
  }, [conversationId, reconcile, subscribeActive]);

  // When Reverb is down, light REST reconcile — not a storm (15s).
  useEffect(() => {
    if (rtStatus !== 'rest_fallback' && rtStatus !== 'disconnected') return;
    if (!isMessagingRealtimeEnabled() && rtStatus === 'disconnected') return;
    const timer = setInterval(() => {
      void reconcile();
    }, 15000);
    return () => clearInterval(timer);
  }, [rtStatus, reconcile]);

  const onRefresh = async () => {
    setRefreshing(true);
    try {
      await reconcile();
      if (rtStatus === 'rest_fallback' || rtStatus === 'disconnected') {
        await subscribeActive();
      }
    } finally {
      setRefreshing(false);
    }
  };

  const onLoadOlder = async () => {
    if (!hasOlder || loadingOlder) return;
    setLoadingOlder(true);
    try {
      const older = await loadOlderMessages(conversationId, oldestPage, 50);
      if (!older) {
        setHasOlder(false);
        return;
      }
      setMessages((prev) => mergeMessagesById(prev, older.messages));
      setOldestPage(older.page);
      setHasOlder(older.page > 1);
    } catch {
      // keep honest
    } finally {
      setLoadingOlder(false);
    }
  };

  const onSend = async () => {
    const body = draft.trim();
    if (!body || sending || sendLock.current) return;
    if (conversation?.status === 'closed') {
      setSendError('Conversation is closed.');
      return;
    }
    sendLock.current = true;
    setSending(true);
    setSendError(null);
    try {
      const message = await sendMessage(conversationId, body);
      setMessages((prev) => mergeMessagesById(prev, message));
      setDraft('');
      requestAnimationFrame(() => listRef.current?.scrollToEnd({ animated: true }));
    } catch (e) {
      setSendError(e instanceof Error ? e.message : 'Could not send message. Please try again.');
    } finally {
      setSending(false);
      sendLock.current = false;
    }
  };

  const retryLoad = () => {
    setConversation(null);
    setMessages([]);
    setLoadNonce((n) => n + 1);
  };

  if (ready && !isAuthenticated) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <GradientBackground>
        <View style={styles.center} testID="conversation-loading">
          <ActivityIndicator color={theme.primary} />
          <Text style={[styles.muted, { color: theme.textSecondary }]}>
            Loading FleetX Support…
          </Text>
        </View>
      </GradientBackground>
    );
  }

  if (error || !conversation) {
    const canRetry = errorKind === 'error' || errorKind === 'network' || errorKind === null;
    const title =
      errorKind === 'unauthorized'
        ? ClientUxCopy.accessTitle
        : errorKind === 'unavailable'
          ? ClientUxCopy.notFoundTitle
          : errorKind === 'network'
            ? ClientUxCopy.networkTitle
            : screenErrorTitle({
                isNetwork: false,
                loadFallbackTitle: 'Could not open conversation',
              });
    return (
      <GradientBackground>
        <View style={styles.center} testID="conversation-error">
          <Text style={[styles.errorTitle, { color: theme.text }]}>{title}</Text>
          <Text style={[styles.centerText, { color: theme.textSecondary }]}>
            {error || 'This conversation is unavailable.'}
          </Text>
          {canRetry ? (
            <Pressable
              testID="conversation-retry"
              onPress={retryLoad}
              style={[styles.retryBtn, { backgroundColor: theme.primary }]}
              accessibilityRole="button"
              accessibilityLabel="Retry loading FleetX Support conversation"
              accessibilityHint="Attempts to reload this conversation">
              <Text style={styles.retryText}>{ClientUxCopy.retry}</Text>
            </Pressable>
          ) : null}
        </View>
      </GradientBackground>
    );
  }

  const closed = conversation.status === 'closed';
  const keyboardOffset = Platform.OS === 'ios' ? 88 : 0;
  const shipmentTitle = conversation.shipment?.shipmentNumber
    ? `Shipment ${conversation.shipment.shipmentNumber}`
    : conversation.shipmentId
      ? 'Your shipment'
      : 'FleetX Support conversation';

  return (
    <GradientBackground scrollable={false}>
      <View style={styles.container} testID="conversation-screen">
        <View style={styles.meta} testID="conversation-meta">
          <Text style={[styles.metaText, { color: theme.textSecondary }]}>FleetX Support</Text>
          <Text style={[styles.metaText, { color: theme.textSecondary }]}>
            · {conversation.status === 'open' ? 'Open' : conversation.status || 'Conversation'}
          </Text>
          <Text
            testID="realtime-status"
            style={[styles.metaText, { color: theme.textSecondary }]}>
            · {statusLabel(rtStatus)}
          </Text>
          {rtStatus === 'rest_fallback' || rtStatus === 'disconnected' ? (
            <Pressable
              testID="realtime-reconnect"
              onPress={() => {
                void (async () => {
                  await mobileRealtime.reconnect();
                  await subscribeActive();
                  await reconcile();
                })();
              }}
              accessibilityRole="button"
              accessibilityLabel="Reconnect realtime messaging">
              <Text style={[styles.reconnect, { color: theme.primary }]}>Reconnect</Text>
            </Pressable>
          ) : null}
        </View>

        <View
          style={[styles.shipmentBanner, { backgroundColor: theme.backgroundElement }]}
          testID="shipment-banner">
          <Text style={[styles.bannerTitle, { color: theme.text }]}>{shipmentTitle}</Text>
          {conversation.shipmentId ? (
            <Pressable
              testID="shipment-comms-link"
              onPress={() => router.push(`/shipment/${conversation.shipmentId}`)}
              accessibilityRole="button"
              accessibilityLabel="Open shipment communication">
              <Text style={[styles.linkText, { color: theme.primary }]}>Shipment communication</Text>
            </Pressable>
          ) : null}
        </View>

        {hasOlder ? (
          <Pressable
            onPress={() => void onLoadOlder()}
            style={styles.loadOlder}
            accessibilityRole="button"
            accessibilityLabel="Load older messages">
            {loadingOlder ? (
              <ActivityIndicator color={theme.primary} />
            ) : (
              <Text style={[styles.linkText, { color: theme.primary }]}>Load older</Text>
            )}
          </Pressable>
        ) : null}

        <FlatList
          ref={listRef}
          data={messages}
          keyExtractor={(item) => item.id}
          contentContainerStyle={messages.length === 0 ? styles.emptyList : styles.list}
          onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => void onRefresh()} />
          }
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={{ color: theme.text }}>No messages yet.</Text>
              <Text style={[styles.muted, { color: theme.textSecondary }]}>
                Send a text message to continue with FleetX Support.
              </Text>
            </View>
          }
          renderItem={({ item }) => {
            const mine = user?.id != null && item.senderUserId === user.id;
            return (
              <View
                style={[styles.bubble, mine ? styles.mine : styles.theirs]}
                accessibilityRole="text"
                accessibilityLabel={`Message from ${
                  mine ? 'you' : item.sender?.name ?? 'FleetX Support'
                }. ${item.body}`}>
                <Text style={[styles.body, mine ? styles.mineText : { color: theme.text }]}>
                  {item.body}
                </Text>
                <Text style={[styles.time, mine ? styles.mineText : { color: theme.textSecondary }]}>
                  {item.sender?.name ?? (mine ? 'You' : 'FleetX Support')} ·{' '}
                  {formatMessageTime(item.createdAt)}
                </Text>
              </View>
            );
          }}
        />

        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : Platform.OS === 'android' ? 'height' : undefined}
          keyboardVerticalOffset={keyboardOffset}>
          {sendError ? (
            <Text testID="send-error" style={styles.sendError}>
              {sendError}
            </Text>
          ) : null}
          {closed ? (
            <Text style={[styles.closed, { color: theme.textSecondary }]}>
              Conversation closed — composer disabled.
            </Text>
          ) : (
            <View
              testID="composer"
              style={[
                styles.composer,
                {
                  paddingBottom: Math.max(insets.bottom, 12),
                  borderTopColor: theme.border,
                },
              ]}>
              <TextInput
                testID="composer-input"
                accessibilityLabel="Message to FleetX Support"
                value={draft}
                onChangeText={setDraft}
                placeholder="Message FleetX Support"
                placeholderTextColor={theme.textSecondary}
                editable={!sending}
                multiline
                style={[
                  styles.input,
                  {
                    color: theme.text,
                    borderColor: theme.border,
                    backgroundColor: theme.backgroundElement,
                  },
                ]}
              />
              <Pressable
                testID="composer-send"
                accessibilityRole="button"
                accessibilityLabel="Send message to FleetX Support"
                onPress={() => void onSend()}
                disabled={sending || !draft.trim()}
                style={[
                  styles.sendBtn,
                  { backgroundColor: theme.primary },
                  (sending || !draft.trim()) && styles.sendDisabled,
                ]}>
                {sending ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.sendText}>Send</Text>
                )}
              </Pressable>
            </View>
          )}
        </KeyboardAvoidingView>
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
    padding: 24,
    gap: 8,
  },
  centerText: { textAlign: 'center', fontSize: 14, lineHeight: 20 },
  errorTitle: { fontSize: 18, fontWeight: '700', marginBottom: 4 },
  muted: { fontSize: 13 },
  meta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  metaText: { fontSize: 12 },
  reconnect: { fontSize: 12, fontWeight: '700', marginLeft: 8 },
  shipmentBanner: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    gap: 4,
  },
  bannerTitle: { fontSize: 14, fontWeight: '700' },
  loadOlder: { alignItems: 'center', paddingVertical: 8 },
  linkText: { fontWeight: '600', fontSize: 13 },
  list: { padding: 16, paddingBottom: 20 },
  emptyList: { flexGrow: 1 },
  bubble: {
    maxWidth: '82%',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 14,
    marginBottom: 8,
  },
  body: { flexShrink: 1, fontSize: 15 },
  mine: { alignSelf: 'flex-end', backgroundColor: '#0F5C4C' },
  theirs: { alignSelf: 'flex-start', backgroundColor: 'rgba(127,127,127,0.18)' },
  mineText: { color: '#fff' },
  time: { fontSize: 11, opacity: 0.75, marginTop: 4 },
  composer: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 16,
    paddingTop: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
    alignItems: 'flex-end',
  },
  input: {
    flex: 1,
    minHeight: 40,
    maxHeight: 120,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  sendBtn: {
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    minWidth: 72,
    alignItems: 'center',
  },
  sendDisabled: { opacity: 0.5 },
  sendText: { color: '#fff', fontWeight: '600' },
  sendError: { color: '#B42318', paddingHorizontal: 16, marginBottom: 4 },
  closed: { padding: 16 },
  retryBtn: {
    marginTop: 12,
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 12,
  },
  retryText: { color: '#FFFFFF', fontWeight: '700' },
});
