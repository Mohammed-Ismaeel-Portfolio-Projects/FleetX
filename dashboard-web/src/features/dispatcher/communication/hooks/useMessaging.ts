import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { Driver } from '../../drivers/types/driver-operations.types';
import type {
  Conversation,
  Message,
  MessagingLoadState,
} from '../types/messaging.types';
import {
  getConversation,
  loadNewestMessages,
  loadOlderMessages,
  listConversations,
  markConversationRead,
  MessagingApiError,
  openConversation,
  sendMessage,
} from '../services/messaging.service';
import { resolveDriverMessagingGate } from '../utils/messagingGates';
import {
  getMessagingRealtimeStatus,
  isMessagingRealtimeEnabled,
  mapRealtimeMessage,
  sortMessagesChronologically,
  subscribeConversationMessages,
  type MessagingRealtimeStatus,
  type MessageCreatedPayload,
} from '../utils/conversationRealtime';

const CONVERSATIONS_POLL_MS = 15_000;
const MESSAGES_PER_PAGE = 50;

/**
 * Deterministic list order (API fields only):
 * 1. status === 'open' before others
 * 2. updated_at descending (missing → oldest)
 * No invented priority scores.
 */
export function sortConversationsOperationally(items: Conversation[]): Conversation[] {
  return [...items].sort((a, b) => {
    const aOpen = a.status === 'open' ? 0 : 1;
    const bOpen = b.status === 'open' ? 0 : 1;
    if (aOpen !== bOpen) return aOpen - bOpen;
    const aTs = a.updatedAt ? Date.parse(a.updatedAt) : 0;
    const bTs = b.updatedAt ? Date.parse(b.updatedAt) : 0;
    return (Number.isFinite(bTs) ? bTs : 0) - (Number.isFinite(aTs) ? aTs : 0);
  });
}

function classifyError(err: unknown): {
  state: Extract<MessagingLoadState, 'unauthorized' | 'unavailable' | 'error'>;
  message: string;
} {
  if (err instanceof MessagingApiError) {
    if (err.statusCode === 403 || err.statusCode === 401) {
      return {
        state: 'unauthorized',
        message: "You don't have access to this conversation.",
      };
    }
    if (err.statusCode === 404) {
      return {
        state: 'unavailable',
        message: 'This conversation is no longer available.',
      };
    }
    if (err.statusCode === 422) {
      return {
        state: 'unavailable',
        message: err.message || 'This conversation cannot be used right now.',
      };
    }
    return {
      state: 'error',
      message: err.message || 'Messaging request failed. Try again.',
    };
  }
  return {
    state: 'error',
    message: err instanceof Error ? err.message : 'Messaging request failed. Try again.',
  };
}

function findExistingDriverConversation(
  conversations: Conversation[],
  shipmentId: string
): Conversation | undefined {
  return conversations.find(
    (c) => c.type === 'dispatcher_driver' && c.shipmentId === shipmentId
  );
}

type LoadThreadOptions = {
  /** Mark read only on genuine open/view — not silent freshness polls. */
  markRead?: boolean;
  /** Soft refresh: keep messages visible; do not flash full loading empty. */
  silent?: boolean;
};

export function useMessaging(options: { selectedDriver: Driver | null }) {
  const { selectedDriver } = options;

  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [conversationsState, setConversationsState] = useState<MessagingLoadState>('idle');
  const [conversationsError, setConversationsError] = useState<string | null>(null);

  const [selectedConversationId, setSelectedConversationId] = useState<string | null>(null);
  const [activeConversation, setActiveConversation] = useState<Conversation | null>(null);
  const [threadState, setThreadState] = useState<MessagingLoadState>('idle');
  const [threadError, setThreadError] = useState<string | null>(null);
  const [gateMessage, setGateMessage] = useState<string | null>(null);

  const [messages, setMessages] = useState<Message[]>([]);
  const [hasOlderMessages, setHasOlderMessages] = useState(false);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [oldestLoadedPage, setOldestLoadedPage] = useState(1);

  const [composerText, setComposerText] = useState('');
  const [sendError, setSendError] = useState<string | null>(null);
  const [isSending, setIsSending] = useState(false);
  const [lastReadAt, setLastReadAt] = useState<string | null>(null);
  const [realtimeStatus, setRealtimeStatus] = useState<MessagingRealtimeStatus>(() =>
    getMessagingRealtimeStatus()
  );

  /** Scroll hints for ActiveConversation (not realtime — local UX only). */
  const [scrollSignal, setScrollSignal] = useState<{
    kind: 'initial' | 'sent' | 'older' | 'none';
    token: number;
  }>({ kind: 'none', token: 0 });

  const conversationsRequestRef = useRef(0);
  const conversationsInFlightRef = useRef(false);
  const conversationsPendingRef = useRef(false);
  const threadRequestRef = useRef(0);
  const selectedIdRef = useRef<string | null>(null);
  const messagesRef = useRef<Message[]>([]);
  const activeConversationRef = useRef<Conversation | null>(null);
  const conversationsRef = useRef<Conversation[]>([]);
  /** User chose a conversation from the list — do not replace with driver match. */
  const listPinnedRef = useRef(false);
  const lastDriverKeyRef = useRef<string | null>(null);
  const lastKnownUpdatedAtRef = useRef<string | null>(null);
  const softRefreshInFlightRef = useRef(false);
  const realtimeUnsubRef = useRef<(() => void) | null>(null);

  selectedIdRef.current = selectedConversationId;
  messagesRef.current = messages;
  activeConversationRef.current = activeConversation;
  conversationsRef.current = conversations;

  const sortedConversations = useMemo(
    () => sortConversationsOperationally(conversations),
    [conversations]
  );

  const emitScroll = useCallback((kind: 'initial' | 'sent' | 'older' | 'none') => {
    setScrollSignal((prev) => ({ kind, token: prev.token + 1 }));
  }, []);

  const loadThread = useCallback(
    async (conversation: Conversation, opts?: LoadThreadOptions) => {
      const markRead = opts?.markRead !== false;
      const silent = Boolean(opts?.silent);
      const requestId = ++threadRequestRef.current;
      const switchingAway =
        messagesRef.current.length > 0 &&
        messagesRef.current.some((m) => m.conversationId !== conversation.id);

      setSelectedConversationId(conversation.id);
      setActiveConversation(conversation);
      setThreadError(null);
      setSendError(null);
      setGateMessage(null);

      if (!silent) {
        setThreadState('loading');
      }

      if (switchingAway) {
        setMessages([]);
        setHasOlderMessages(false);
        setLastReadAt(null);
        setComposerText('');
      }

      try {
        const newest = await loadNewestMessages(conversation.id, MESSAGES_PER_PAGE);
        if (requestId !== threadRequestRef.current) return;
        if (selectedIdRef.current !== conversation.id) return;

        const prevIds = new Set(messagesRef.current.map((m) => m.id));
        const added =
          newest.messages.some((m) => !prevIds.has(m.id)) &&
          messagesRef.current.every((m) => m.conversationId === conversation.id);

        setMessages(newest.messages);
        setOldestLoadedPage(newest.oldestLoadedPage);
        setHasOlderMessages(newest.hasOlder);
        setThreadState(newest.messages.length === 0 ? 'empty' : 'ready');
        lastKnownUpdatedAtRef.current = conversation.updatedAt;

        if (!silent || switchingAway) {
          emitScroll('initial');
        } else if (added) {
          emitScroll('sent');
        }

        if (markRead && !silent) {
          try {
            const read = await markConversationRead(conversation.id);
            if (requestId !== threadRequestRef.current) return;
            if (selectedIdRef.current !== conversation.id) return;
            setLastReadAt(read.lastReadAt);
            setActiveConversation((prev) =>
              prev && prev.id === conversation.id
                ? { ...prev, lastReadAt: read.lastReadAt }
                : prev
            );
          } catch {
            // Read mark failure is non-fatal for viewing history.
          }
        }
      } catch (err) {
        if (requestId !== threadRequestRef.current) return;
        if (selectedIdRef.current !== conversation.id) return;
        const classified = classifyError(err);
        setThreadError(classified.message);
        setThreadState(classified.state);
        // Preserve last-known-good messages for this conversation.
      }
    },
    [emitScroll]
  );

  const applyRealtimeMessage = useCallback(
    (payload: MessageCreatedPayload) => {
      const conversationId = payload.conversation_id;
      if (!conversationId) return;

      // List: bump updated_at only — never invent last message / unread.
      if (payload.conversation_updated_at || payload.created_at) {
        const nextUpdated = payload.conversation_updated_at || payload.created_at || null;
        setConversations((prev) =>
          sortConversationsOperationally(
            prev.map((c) =>
              c.id === conversationId && nextUpdated
                ? { ...c, updatedAt: nextUpdated }
                : c
            )
          )
        );
      }

      // Active thread only when selected.
      if (selectedIdRef.current !== conversationId) return;

      const mapped = mapRealtimeMessage(payload);
      if (!mapped) {
        const active = activeConversationRef.current;
        if (active && active.id === conversationId) {
          void loadThread(active, { markRead: false, silent: true });
        }
        return;
      }

      setMessages((prev) => {
        if (prev.some((m) => m.id === mapped.id)) return prev;
        return sortMessagesChronologically([...prev, mapped]);
      });
      setThreadState((prev) => (prev === 'empty' || prev === 'ready' ? 'ready' : prev));
      setActiveConversation((prev) =>
        prev && prev.id === conversationId
          ? {
              ...prev,
              updatedAt: payload.conversation_updated_at || mapped.createdAt || prev.updatedAt,
            }
          : prev
      );
      lastKnownUpdatedAtRef.current =
        payload.conversation_updated_at || mapped.createdAt || lastKnownUpdatedAtRef.current;
      emitScroll('sent');
    },
    [emitScroll, loadThread]
  );

  const refreshConversations = useCallback(
    async (opts?: { silent?: boolean }) => {
      if (conversationsInFlightRef.current) {
        conversationsPendingRef.current = true;
        return;
      }
      conversationsInFlightRef.current = true;
      const requestId = ++conversationsRequestRef.current;
      const silent = Boolean(opts?.silent);

      if (!silent) {
        setConversationsState((prev) =>
          prev === 'ready' || prev === 'empty' ? prev : 'loading'
        );
      }

      try {
        const result = await listConversations({ per_page: 50, page: 1 });
        if (requestId !== conversationsRequestRef.current) return;

        setConversations(result.conversations);
        setConversationsError(null);
        setConversationsState(result.conversations.length === 0 ? 'empty' : 'ready');

        const selectedId = selectedIdRef.current;
        if (selectedId) {
          const stillThere = result.conversations.find((c) => c.id === selectedId);
          if (stillThere) {
            setActiveConversation((current) => {
              if (!current || current.id !== stillThere.id) return stillThere;
              return stillThere;
            });

            // Soft message refresh only when list reports a newer updated_at.
            const prevUpdated = lastKnownUpdatedAtRef.current;
            const nextUpdated = stillThere.updatedAt;
            if (
              silent &&
              nextUpdated &&
              nextUpdated !== prevUpdated &&
              !softRefreshInFlightRef.current
            ) {
              softRefreshInFlightRef.current = true;
              lastKnownUpdatedAtRef.current = nextUpdated;
              void loadThread(stillThere, { markRead: false, silent: true }).finally(() => {
                softRefreshInFlightRef.current = false;
              });
            } else if (nextUpdated) {
              lastKnownUpdatedAtRef.current = nextUpdated;
            }
          } else {
            // Selection missing from page-1 list — verify via show; never auto-pick another.
            try {
              const fresh = await getConversation(selectedId);
              if (requestId !== conversationsRequestRef.current) return;
              if (selectedIdRef.current !== selectedId) return;
              setActiveConversation(fresh);
            } catch (err) {
              if (requestId !== conversationsRequestRef.current) return;
              if (selectedIdRef.current !== selectedId) return;
              const classified = classifyError(err);
              setThreadError(classified.message);
              setThreadState(classified.state);
              // Keep selection id + last-known messages; do not switch to another conversation.
            }
          }
        }
      } catch (err) {
        if (requestId !== conversationsRequestRef.current) return;
        const classified = classifyError(err);
        setConversationsError(classified.message);
        setConversationsState((prev) =>
          prev === 'ready' || prev === 'empty' ? prev : classified.state
        );
      } finally {
        if (requestId === conversationsRequestRef.current) {
          conversationsInFlightRef.current = false;
          if (conversationsPendingRef.current) {
            conversationsPendingRef.current = false;
            void refreshConversations({ silent: true });
          }
        } else {
          conversationsInFlightRef.current = false;
        }
      }
    },
    [loadThread]
  );

  const selectConversation = useCallback(
    (conversation: Conversation) => {
      listPinnedRef.current = true;
      lastKnownUpdatedAtRef.current = conversation.updatedAt;
      void loadThread(conversation, { markRead: true, silent: false });
    },
    [loadThread]
  );

  const clearThreadForGate = useCallback((message: string) => {
    ++threadRequestRef.current;
    setSelectedConversationId(null);
    setActiveConversation(null);
    setMessages([]);
    setHasOlderMessages(false);
    setLastReadAt(null);
    setThreadState('unavailable');
    setGateMessage(message);
    setThreadError(null);
    setSendError(null);
    lastKnownUpdatedAtRef.current = null;
  }, []);

  /**
   * Explicit find-or-create for a linked driver + shipment.
   * Never called automatically on contact selection (M4).
   */
  const openForDriver = useCallback(
    async (driver: Driver) => {
      const gate = resolveDriverMessagingGate(driver);

      if (gate.kind === 'unlinked') {
        clearThreadForGate('This driver account is not linked to messaging.');
        return;
      }
      if (gate.kind === 'no_shipment') {
        clearThreadForGate(
          'Messaging cannot open — this driver has no active assignment/shipment.'
        );
        return;
      }
      if (gate.kind !== 'ready') return;

      const requestId = ++threadRequestRef.current;
      listPinnedRef.current = false;
      setThreadState('loading');
      setThreadError(null);
      setGateMessage(null);
      setSendError(null);

      try {
        const conversation = await openConversation({
          type: 'dispatcher_driver',
          shipment_id: gate.shipmentId,
          driver_user_id: gate.driverUserId,
        });
        if (requestId !== threadRequestRef.current) return;

        lastKnownUpdatedAtRef.current = conversation.updatedAt;
        await loadThread(conversation, { markRead: true, silent: false });
        void refreshConversations({ silent: true });
      } catch (err) {
        if (requestId !== threadRequestRef.current) return;
        const classified = classifyError(err);
        // Do not wipe an already-valid pinned thread on failed create.
        if (listPinnedRef.current && activeConversationRef.current) {
          setThreadError(classified.message);
          setThreadState(classified.state);
          return;
        }
        setSelectedConversationId(null);
        setActiveConversation(null);
        setMessages([]);
        setThreadState(classified.state);
        setThreadError(classified.message);
        setGateMessage(null);
      }
    },
    [clearThreadForGate, loadThread, refreshConversations]
  );

  // Initial + poll conversation list (does not reload messages every tick).
  useEffect(() => {
    void refreshConversations({ silent: false });

    let intervalId: number | null = null;
    const clear = () => {
      if (intervalId != null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };
    const tick = () => {
      if (!document.hidden) void refreshConversations({ silent: true });
    };
    const start = () => {
      if (intervalId != null || document.hidden) return;
      intervalId = window.setInterval(tick, CONVERSATIONS_POLL_MS);
    };
    const onVis = () => {
      if (document.hidden) {
        clear();
        return;
      }
      tick();
      start();
    };
    start();
    document.addEventListener('visibilitychange', onVis);
    return () => {
      clear();
      document.removeEventListener('visibilitychange', onVis);
      conversationsRequestRef.current += 1;
      threadRequestRef.current += 1;
    };
  }, [refreshConversations]);

  /**
   * Driver contact selection (M4):
   * - Updates gate messaging readiness only
   * - May resume an EXISTING list conversation for that shipment (no create)
   * - Does NOT POST find-or-create (explicit Open Conversation required)
   */
  useEffect(() => {
    const driver = selectedDriver;
    const driverKey = driver
      ? `${driver.id}:${driver.assignment?.shipmentId ?? ''}:${driver.userId ?? ''}`
      : null;

    if (lastDriverKeyRef.current === driverKey) return;
    lastDriverKeyRef.current = driverKey;

    if (listPinnedRef.current) {
      // Driver changed while a list conversation is pinned — keep thread; update gate copy only.
      if (!driver) {
        setGateMessage(null);
        return;
      }
      const gate = resolveDriverMessagingGate(driver);
      if (gate.kind === 'unlinked') {
        setGateMessage('Selected contact is not linked to messaging. Call / Email still available.');
      } else if (gate.kind === 'no_shipment') {
        setGateMessage('Selected contact has no active shipment. Thread above is from Conversations.');
      } else {
        setGateMessage(null);
      }
      return;
    }

    if (!driver) {
      setGateMessage(null);
      return;
    }

    const gate = resolveDriverMessagingGate(driver);
    if (gate.kind === 'unlinked') {
      clearThreadForGate('This driver account is not linked to messaging.');
      return;
    }
    if (gate.kind === 'no_shipment') {
      clearThreadForGate(
        'Messaging cannot open — this driver has no active assignment/shipment.'
      );
      return;
    }

    const existing = findExistingDriverConversation(conversationsRef.current, gate.shipmentId);
    if (existing) {
      setGateMessage(null);
      void loadThread(existing, { markRead: true, silent: false });
      return;
    }

    // Ready to open, but wait for explicit CTA — no automatic create.
    ++threadRequestRef.current;
    setSelectedConversationId(null);
    setActiveConversation(null);
    setMessages([]);
    setThreadState('idle');
    setThreadError(null);
    setGateMessage(
      'Ready to message this driver. Use Open Conversation to find or create the thread.'
    );
    lastKnownUpdatedAtRef.current = null;
  }, [selectedDriver, clearThreadForGate, loadThread]);

  // If conversations arrive after driver selection, resume existing thread (still no create).
  useEffect(() => {
    if (listPinnedRef.current) return;
    if (!selectedDriver) return;
    if (selectedIdRef.current) return;
    const gate = resolveDriverMessagingGate(selectedDriver);
    if (gate.kind !== 'ready') return;
    const existing = findExistingDriverConversation(conversations, gate.shipmentId);
    if (!existing) return;
    setGateMessage(null);
    void loadThread(existing, { markRead: true, silent: false });
  }, [conversations, selectedDriver, loadThread]);

  // M5: subscribe to selected conversation channel (shared Echo singleton).
  useEffect(() => {
    const conversationId = selectedConversationId;
    realtimeUnsubRef.current?.();
    realtimeUnsubRef.current = null;

    if (!conversationId || !isMessagingRealtimeEnabled()) {
      setRealtimeStatus(getMessagingRealtimeStatus());
      return;
    }

    let cancelled = false;
    setRealtimeStatus('connecting');

    void subscribeConversationMessages(
      conversationId,
      (payload) => {
        if (cancelled) return;
        applyRealtimeMessage(payload);
      },
      (error) => {
        if (cancelled) return;
        const msg = error.message.toLowerCase();
        if (msg.includes('403') || msg.includes('401') || msg.includes('auth')) {
          setRealtimeStatus('permission_denied');
        } else {
          setRealtimeStatus('error');
        }
      }
    ).then((unsub) => {
      if (cancelled) {
        unsub();
        return;
      }
      realtimeUnsubRef.current = unsub;
      setRealtimeStatus(getMessagingRealtimeStatus());
    });

    const statusTimer = window.setInterval(() => {
      if (!cancelled) setRealtimeStatus(getMessagingRealtimeStatus());
    }, 3000);

    return () => {
      cancelled = true;
      window.clearInterval(statusTimer);
      realtimeUnsubRef.current?.();
      realtimeUnsubRef.current = null;
    };
  }, [selectedConversationId, applyRealtimeMessage]);

  const loadOlder = useCallback(async () => {
    const id = selectedIdRef.current;
    if (!id || !hasOlderMessages || loadingOlder) return;
    setLoadingOlder(true);
    const requestId = threadRequestRef.current;
    try {
      const older = await loadOlderMessages(id, oldestLoadedPage, MESSAGES_PER_PAGE);
      if (!older || requestId !== threadRequestRef.current || selectedIdRef.current !== id) {
        return;
      }
      setMessages((prev) => {
        const seen = new Set(prev.map((m) => m.id));
        return [...older.messages.filter((m) => !seen.has(m.id)), ...prev];
      });
      setOldestLoadedPage(older.page);
      setHasOlderMessages(older.page > 1);
      emitScroll('older');
    } catch (err) {
      if (requestId !== threadRequestRef.current) return;
      const classified = classifyError(err);
      setThreadError(classified.message);
    } finally {
      setLoadingOlder(false);
    }
  }, [emitScroll, hasOlderMessages, loadingOlder, oldestLoadedPage]);

  const retryThread = useCallback(() => {
    if (activeConversation) {
      void loadThread(activeConversation, { markRead: true, silent: false });
    }
  }, [activeConversation, loadThread]);

  const refreshActiveThread = useCallback(() => {
    if (activeConversation) {
      void loadThread(activeConversation, { markRead: false, silent: true });
    }
  }, [activeConversation, loadThread]);

  const submitMessage = useCallback(async () => {
    const id = selectedIdRef.current;
    const conversation = activeConversationRef.current;
    const body = composerText.trim();
    if (!id || !body || isSending) return;
    if (conversation?.status === 'closed') {
      setSendError('This conversation is closed. Messages cannot be sent.');
      setThreadState('send_failed');
      return;
    }

    setIsSending(true);
    setSendError(null);
    setThreadState((prev) => (prev === 'empty' || prev === 'ready' ? 'sending' : prev));

    try {
      const message = await sendMessage(id, body);
      if (selectedIdRef.current !== id) return;
      setMessages((prev) => {
        if (prev.some((m) => m.id === message.id)) return prev;
        return sortMessagesChronologically([...prev, message]);
      });
      setComposerText('');
      setThreadState('ready');
      setActiveConversation((prev) =>
        prev && prev.id === id
          ? { ...prev, updatedAt: message.createdAt ?? prev.updatedAt }
          : prev
      );
      lastKnownUpdatedAtRef.current = message.createdAt ?? lastKnownUpdatedAtRef.current;
      emitScroll('sent');
      void refreshConversations({ silent: true });
    } catch (err) {
      if (selectedIdRef.current !== id) return;
      const classified = classifyError(err);
      setSendError(
        `Message not sent. Your text is preserved. ${classified.message}`
      );
      setThreadState('send_failed');
    } finally {
      setIsSending(false);
    }
  }, [composerText, emitScroll, isSending, refreshConversations]);

  const releaseListPin = useCallback(() => {
    listPinnedRef.current = false;
  }, []);

  const messagingConnected =
    conversationsState === 'ready' ||
    conversationsState === 'empty' ||
    threadState === 'ready' ||
    threadState === 'empty' ||
    threadState === 'sending' ||
    threadState === 'send_failed' ||
    threadState === 'idle';

  const driverGate = resolveDriverMessagingGate(selectedDriver);
  const canOpenDriverConversation = driverGate.kind === 'ready';

  return {
    conversations: sortedConversations,
    conversationsState,
    conversationsError,
    refreshConversations: () => void refreshConversations({ silent: false }),
    selectedConversationId,
    activeConversation,
    selectConversation,
    threadState,
    threadError,
    gateMessage,
    messages,
    hasOlderMessages,
    loadingOlder,
    loadOlder,
    retryThread,
    refreshActiveThread,
    composerText,
    setComposerText,
    sendError,
    isSending,
    submitMessage,
    lastReadAt,
    messagingConnected,
    openForDriver,
    canOpenDriverConversation,
    scrollSignal,
    releaseListPin,
    realtimeStatus,
  };
}
