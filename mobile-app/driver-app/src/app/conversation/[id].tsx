import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { MessageCircle } from 'lucide-react-native';

import {
  DriverCard,
  DriverHeader,
  DriverScreen,
  EmptyState,
  InfoRow,
  MessageBubble,
  MessageComposer,
  StatusBadge,
  type DriverStatusTone,
} from '@/components/driver';
import { DriverTheme } from '@/components/driver/theme';
import { useAuth } from '@/lib/auth/AuthContext';
import {
  getConversation,
  loadNewestMessages,
  loadOlderMessages,
  markConversationRead,
  sendMessage,
} from '@/features/driverApi/messaging';
import { formatMessageTime, mergeMessagesById } from '@/features/messaging/messageMerge';
import {
  isMessagingRealtimeEnabled,
  mapRealtimeMessage,
  mobileRealtime,
  type MessageCreatedPayload,
} from '@/features/messaging/realtime';
import type { Conversation, Message, RealtimeStatus } from '@/features/messaging/types';
import { ScreenError, ScreenLoading } from '@/lib/ux/ScreenState';

const uxTheme = {
  text: DriverTheme.colors.textPrimary,
  textSecondary: DriverTheme.colors.textSecondary,
  primary: DriverTheme.colors.primary,
};

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

function realtimeTone(status: RealtimeStatus): DriverStatusTone {
  switch (status) {
    case 'connected':
      return 'available';
    case 'connecting':
    case 'reconnecting':
      return 'in_operation';
    case 'error':
    case 'permission_denied':
      return 'danger';
    default:
      return 'neutral';
  }
}

function conversationStatusTone(status: string): DriverStatusTone {
  const key = status.trim().toLowerCase();
  if (key === 'open') return 'assigned';
  if (key === 'closed') return 'danger';
  return 'neutral';
}

export default function ConversationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const conversationId = String(id);
  const { user, roleOk, identityLinked } = useAuth();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [oldestPage, setOldestPage] = useState(1);
  const [hasOlder, setHasOlder] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [errorKind, setErrorKind] = useState<'unauthorized' | 'unavailable' | 'error' | null>(
    null
  );
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [sendError, setSendError] = useState<string | null>(null);
  const [rtStatus, setRtStatus] = useState<RealtimeStatus>(
    isMessagingRealtimeEnabled() ? 'connecting' : 'disabled'
  );

  const activeIdRef = useRef(conversationId);
  useEffect(() => {
    activeIdRef.current = conversationId;
  }, [conversationId]);
  const listRef = useRef<FlatList<Message>>(null);
  const sendLock = useRef(false);
  const unsubscribeRef = useRef<(() => void) | undefined>(undefined);

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
          const err = e as { statusCode?: number; message?: string };
          const status = err.statusCode;
          if (status === 403 || status === 401) {
            setErrorKind('unauthorized');
            setError(err.message || 'Unauthorized conversation');
          } else if (status === 404) {
            setErrorKind('unavailable');
            setError(err.message || 'Conversation unavailable');
          } else {
            setErrorKind('error');
            setError(err.message || 'Failed to open conversation');
          }
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [conversationId, roleOk, identityLinked]);

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
      setSendError(e instanceof Error ? e.message : 'Send failed');
    } finally {
      setSending(false);
      sendLock.current = false;
    }
  };

  const headerTitle = conversation?.shipment?.shipmentNumber
    ? `Ship ${conversation.shipment.shipmentNumber}`
    : 'Conversation';

  if (loading) {
    return (
      <DriverScreen
        header={<DriverHeader title="Conversation" onBack={() => router.back()} />}>
        <ScreenLoading label="Loading conversation…" theme={uxTheme} />
      </DriverScreen>
    );
  }

  if (error || !conversation) {
    return (
      <DriverScreen
        header={<DriverHeader title="Conversation" onBack={() => router.back()} />}>
        <View style={styles.errorPad}>
          <ScreenError
            title={
              errorKind === 'unauthorized'
                ? 'Unauthorized'
                : errorKind === 'unavailable'
                  ? 'Unavailable'
                  : 'Error'
            }
            body={error || 'Conversation unavailable'}
            theme={uxTheme}
          />
        </View>
      </DriverScreen>
    );
  }

  const closed = conversation.status === 'closed';

  return (
    <DriverScreen
      header={
        <DriverHeader
          title={headerTitle}
          subtitle={conversation.type}
          onBack={() => router.back()}
        />
      }
      testID="conversation-screen">
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : Platform.OS === 'android' ? 'height' : undefined}
        keyboardVerticalOffset={0}>
        <View style={styles.meta} testID="conversation-meta">
          <Text style={styles.metaType} numberOfLines={1}>
            {conversation.type}
          </Text>
          <StatusBadge
            label={conversation.status}
            tone={conversationStatusTone(conversation.status)}
          />
          <View testID="realtime-status">
            <StatusBadge label={statusLabel(rtStatus)} tone={realtimeTone(rtStatus)} />
          </View>
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
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Reconnect">
              <Text style={styles.reconnect}>Reconnect</Text>
            </Pressable>
          ) : null}
        </View>

        <DriverCard style={styles.banner} testID="shipment-banner">
          <InfoRow
            label="Shipment"
            value={
              conversation.shipment?.shipmentNumber ?? conversation.shipmentId ?? 'Unavailable'
            }
          />
          <InfoRow
            label="Assignment"
            value={
              conversation.assignmentId != null
                ? String(conversation.assignmentId)
                : 'Unavailable'
            }
          />
          {conversation.shipmentId ? (
            <Pressable
              testID="shipment-comms-link"
              onPress={() => router.push(`/shipment/${conversation.shipmentId}`)}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Shipment communication"
              style={styles.handoff}>
              <Text style={styles.handoffText}>Shipment communication</Text>
            </Pressable>
          ) : null}
        </DriverCard>

        {hasOlder ? (
          <Pressable
            onPress={() => void onLoadOlder()}
            style={styles.loadOlder}
            accessibilityRole="button"
            accessibilityLabel="Load older">
            {loadingOlder ? (
              <ActivityIndicator color={DriverTheme.colors.primary} />
            ) : (
              <Text style={styles.loadOlderText}>Load older</Text>
            )}
          </Pressable>
        ) : null}

        <FlatList
          ref={listRef}
          data={messages}
          keyExtractor={(item) => item.id}
          style={styles.flex}
          contentContainerStyle={messages.length === 0 ? styles.emptyList : styles.list}
          onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
          keyboardShouldPersistTaps="handled"
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void onRefresh()}
              tintColor={DriverTheme.colors.primary}
              colors={[DriverTheme.colors.primary]}
            />
          }
          ListEmptyComponent={
            <EmptyState
              icon={
                <MessageCircle
                  size={36}
                  color={DriverTheme.colors.primary}
                  accessibilityLabel="No messages"
                />
              }
              title="No messages yet."
              body="Send a text message to start this thread."
            />
          }
          renderItem={({ item }) => {
            const mine = user?.id != null && item.senderUserId === user.id;
            return (
              <MessageBubble
                mine={mine}
                body={item.body}
                senderLabel={item.sender?.name ?? `User ${item.senderUserId}`}
                timeLabel={formatMessageTime(item.createdAt)}
              />
            );
          }}
        />

        <MessageComposer
          value={draft}
          onChangeText={setDraft}
          onSend={() => void onSend()}
          sending={sending}
          closed={closed}
          sendError={sendError}
          bottomInset={insets.bottom}
        />
      </KeyboardAvoidingView>
    </DriverScreen>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  errorPad: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: DriverTheme.spacing.lg,
  },
  meta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: DriverTheme.spacing.sm,
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingTop: DriverTheme.spacing.md,
    paddingBottom: DriverTheme.spacing.sm,
  },
  metaType: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  reconnect: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.primary,
    fontWeight: '700',
    minHeight: 44,
    textAlignVertical: 'center',
    paddingHorizontal: DriverTheme.spacing.xs,
  },
  banner: {
    marginHorizontal: DriverTheme.spacing.lg,
    marginBottom: DriverTheme.spacing.sm,
    paddingVertical: DriverTheme.spacing.md,
  },
  handoff: {
    minHeight: 44,
    justifyContent: 'center',
  },
  handoffText: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.primary,
    fontWeight: '700',
  },
  loadOlder: {
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 44,
    paddingVertical: DriverTheme.spacing.sm,
  },
  loadOlderText: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.primary,
  },
  list: {
    paddingHorizontal: DriverTheme.spacing.lg,
    paddingTop: DriverTheme.spacing.sm,
    paddingBottom: DriverTheme.spacing.lg,
  },
  emptyList: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: DriverTheme.spacing.lg,
  },
});
