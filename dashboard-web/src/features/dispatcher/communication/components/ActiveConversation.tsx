import { forwardRef, useEffect, useLayoutEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertCircle,
  Loader2,
  Lock,
  MessageSquare,
  Navigation,
  Package,
  Send,
  Users,
} from 'lucide-react';
import type { Driver } from '../../drivers/types/driver-operations.types';
import type { Shipment } from '../../shipments/types/shipment.types';
import type { Conversation, Message, MessagingLoadState } from '../types/messaging.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { StatusBadge } from '../../../../shared/components/dispatcher/StatusBadge';
import { displayOrUnavailable, telHref } from '../utils/contactActions';
import { ContactActionButtons } from './ContactActionButtons';
import {
  conversationTypeLabel,
  counterpartLabel,
  resolveDriverMessagingGate,
} from '../utils/messagingGates';

interface ActiveConversationProps {
  selectedDriver: Driver | null;
  shipment: Shipment | null;
  shipmentLoading?: boolean;
  focusPulse?: boolean;
  currentUserId: number | null;
  activeConversation: Conversation | null;
  messages: Message[];
  threadState: MessagingLoadState;
  threadError: string | null;
  gateMessage: string | null;
  hasOlderMessages: boolean;
  loadingOlder: boolean;
  onLoadOlder: () => void;
  onRetryThread: () => void;
  composerText: string;
  onComposerChange: (value: string) => void;
  onSend: () => void;
  isSending: boolean;
  sendError: string | null;
  lastReadAt: string | null;
  onOpenDriverConversation?: () => void;
  canOpenDriverConversation?: boolean;
  scrollSignal?: { kind: 'initial' | 'sent' | 'older' | 'none'; token: number };
}

function formatMessageTime(iso: string | null): string {
  if (!iso) return 'Unavailable';
  const ms = new Date(iso).getTime();
  if (!Number.isFinite(ms)) return 'Unavailable';
  return new Date(ms).toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function participantRoleLabel(role: string | undefined): string {
  if (role === 'driver') return 'Driver';
  if (role === 'client') return 'Customer';
  if (role === 'dispatcher') return 'Dispatcher';
  return 'Participant';
}

/**
 * Messaging workspace: thread identity + history + composer.
 * Full Driver→Assignment→Shipment detail lives in ConversationDetails.
 */
export const ActiveConversation = forwardRef<HTMLDivElement, ActiveConversationProps>(
  function ActiveConversation(
    {
      selectedDriver,
      shipment,
      shipmentLoading = false,
      focusPulse = false,
      currentUserId,
      activeConversation,
      messages,
      threadState,
      threadError,
      gateMessage,
      hasOlderMessages,
      loadingOlder,
      onLoadOlder,
      onRetryThread,
      composerText,
      onComposerChange,
      onSend,
      isSending,
      sendError,
      lastReadAt,
      onOpenDriverConversation,
      canOpenDriverConversation = false,
      scrollSignal,
    },
    ref
  ) {
    const scrollRef = useRef<HTMLDivElement | null>(null);
    const stickToBottomRef = useRef(true);
    const prevScrollHeightRef = useRef(0);
    const lastScrollTokenRef = useRef(0);

    const isCustomerThread = activeConversation?.type === 'dispatcher_customer';
    const isDriverThread = activeConversation?.type === 'dispatcher_driver';
    const isClosed = activeConversation?.status === 'closed';

    const counterpart = activeConversation
      ? counterpartLabel(activeConversation.type, activeConversation.participants)
      : null;

    const counterpartParticipant = activeConversation?.participants.find((p) =>
      isCustomerThread
        ? p.participantRole === 'client'
        : isDriverThread
          ? p.participantRole === 'driver'
          : p.participantRole !== 'dispatcher'
    );

    const gate = resolveDriverMessagingGate(selectedDriver);
    const shipmentHref = activeConversation?.shipmentId
      ? `/dispatcher/shipments/${activeConversation.shipmentId}`
      : selectedDriver?.assignment?.shipmentId
        ? `/dispatcher/shipments/${selectedDriver.assignment.shipmentId}`
        : null;

    const canCompose =
      Boolean(activeConversation) &&
      !isClosed &&
      (threadState === 'ready' ||
        threadState === 'empty' ||
        threadState === 'sending' ||
        threadState === 'send_failed');

    const onScroll = () => {
      const el = scrollRef.current;
      if (!el) return;
      const distance = el.scrollHeight - el.scrollTop - el.clientHeight;
      stickToBottomRef.current = distance < 72;
    };

    useLayoutEffect(() => {
      if (!scrollSignal || scrollSignal.token === lastScrollTokenRef.current) return;
      lastScrollTokenRef.current = scrollSignal.token;
      const el = scrollRef.current;
      if (!el) return;

      if (scrollSignal.kind === 'older') {
        const delta = el.scrollHeight - prevScrollHeightRef.current;
        el.scrollTop = el.scrollTop + Math.max(0, delta);
        return;
      }

      if (scrollSignal.kind === 'initial' || scrollSignal.kind === 'sent') {
        el.scrollTop = el.scrollHeight;
        stickToBottomRef.current = true;
      }
    }, [scrollSignal, messages]);

    useEffect(() => {
      if (loadingOlder) {
        prevScrollHeightRef.current = scrollRef.current?.scrollHeight ?? 0;
      }
    }, [loadingOlder]);

    // If user is near bottom and a new message arrives without an explicit signal, stay put.
    useLayoutEffect(() => {
      if (!stickToBottomRef.current) return;
      if (scrollSignal?.kind === 'older') return;
      const el = scrollRef.current;
      if (!el) return;
      el.scrollTop = el.scrollHeight;
    }, [messages.length, scrollSignal?.kind]);

    return (
      <div
        ref={ref}
        tabIndex={-1}
        id="communication-workspace"
        className={cn(
          dispatcherDesignTokens.surface,
          'flex min-h-[520px] flex-col p-4 outline-none transition md:p-5',
          focusPulse && 'ring-2 ring-cyan-400/40'
        )}
      >
        <div className="flex items-start justify-between gap-3 border-b border-white/10 pb-3">
          <div className="min-w-0">
            <h2 className="text-lg font-semibold text-white">Active Conversation</h2>
            <p className="mt-1 text-sm text-slate-500">
              {activeConversation
                ? `${conversationTypeLabel(activeConversation.type)} · ${counterpart}`
                : selectedDriver
                  ? `Contact selected: ${selectedDriver.name} — open a conversation to message`
                  : 'Select a conversation or a driver contact'}
            </p>
          </div>
          <div className="flex shrink-0 flex-col items-end gap-1">
            <span className="rounded-full border border-emerald-400/25 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-100">
              Messaging (REST)
            </span>
            {activeConversation ? (
              <StatusBadge
                label={isClosed ? 'Closed' : activeConversation.status || 'Unavailable'}
                tone={isClosed ? 'neutral' : activeConversation.status === 'open' ? 'success' : 'warning'}
              />
            ) : null}
          </div>
        </div>

        {/* Compact thread identity — details panel holds full ops context */}
        {activeConversation ? (
          <div className="mt-3 grid gap-1.5 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-xs text-slate-300 sm:grid-cols-2">
            <p>
              <span className="text-slate-500">Talking to: </span>
              {displayOrUnavailable(counterpart)}
              {counterpartParticipant ? (
                <span className="text-slate-600">
                  {' '}
                  ({participantRoleLabel(counterpartParticipant.participantRole)})
                </span>
              ) : null}
            </p>
            <p>
              <span className="text-slate-500">Shipment: </span>
              {displayOrUnavailable(
                activeConversation.shipment?.shipmentNumber ||
                  shipment?.shipmentNumber ||
                  activeConversation.shipmentId
              )}
            </p>
            {lastReadAt ? (
              <p className="sm:col-span-2 text-[11px] text-slate-500">
                Marked read (you): {formatMessageTime(lastReadAt)}
              </p>
            ) : null}
          </div>
        ) : null}

        {/* Contact actions — driver threads / selected driver only */}
        {selectedDriver && !isCustomerThread ? (
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <ContactActionButtons
              phone={selectedDriver.phone}
              email={selectedDriver.email}
              callLabel="Call driver"
              emailLabel="Email driver"
            />
            {telHref(selectedDriver.emergencyContactPhone) ? (
              <a
                href={telHref(selectedDriver.emergencyContactPhone)!}
                className={cn(
                  dispatcherDesignTokens.buttonSecondary,
                  'border-rose-400/25 px-3 py-2 text-sm text-rose-100'
                )}
              >
                Call emergency
              </a>
            ) : null}
            {canOpenDriverConversation && onOpenDriverConversation ? (
              <button
                type="button"
                onClick={onOpenDriverConversation}
                className={cn(dispatcherDesignTokens.buttonPrimary, 'px-3 py-2 text-sm')}
              >
                <MessageSquare className="h-3.5 w-3.5" aria-hidden /> Open Conversation
              </button>
            ) : null}
            <Link
              to="/dispatcher/drivers"
              className={cn(dispatcherDesignTokens.buttonSecondary, 'px-3 py-2 text-xs')}
            >
              <Users className="h-3.5 w-3.5" aria-hidden /> Driver Ops
            </Link>
            {shipmentHref ? (
              <Link
                to={shipmentHref}
                className={cn(dispatcherDesignTokens.buttonSecondary, 'px-3 py-2 text-xs')}
              >
                <Package className="h-3.5 w-3.5" aria-hidden /> Shipment
              </Link>
            ) : null}
            <Link
              to="/dispatcher/tracking"
              className={cn(dispatcherDesignTokens.buttonSecondary, 'px-3 py-2 text-xs')}
            >
              <Navigation className="h-3.5 w-3.5" aria-hidden /> Tracking
            </Link>
          </div>
        ) : null}

        {isCustomerThread ? (
          <div className="mt-3 flex flex-wrap items-center gap-2">
            {shipmentHref ? (
              <Link
                to={shipmentHref}
                className={cn(dispatcherDesignTokens.buttonSecondary, 'px-3 py-2 text-xs')}
              >
                <Package className="h-3.5 w-3.5" aria-hidden /> Open Shipment
              </Link>
            ) : null}
            <p className="text-xs text-slate-500">
              Customer phone/company contacts: Unavailable from messaging API
            </p>
          </div>
        ) : null}

        {gateMessage && !activeConversation ? (
          <div className="mt-3 rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-50">
            <div className="flex items-start gap-2">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-200" aria-hidden />
              <div>
                <p>{gateMessage}</p>
                {canOpenDriverConversation && onOpenDriverConversation ? (
                  <button
                    type="button"
                    onClick={onOpenDriverConversation}
                    className={cn(dispatcherDesignTokens.buttonPrimary, 'mt-2 px-3 py-1.5 text-xs')}
                  >
                    Open Conversation
                  </button>
                ) : null}
                {gate.kind === 'unlinked' || gate.kind === 'no_shipment' ? (
                  <p className="mt-1.5 text-xs text-amber-100/70">
                    Call / Email remain available when phone or email exists on the driver record.
                  </p>
                ) : null}
              </div>
            </div>
          </div>
        ) : null}

        {/* Message history */}
        <div className="mt-3 flex min-h-0 flex-1 flex-col rounded-2xl border border-white/10 bg-black/20">
          <div className="flex items-center justify-between gap-2 border-b border-white/5 px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
              Messages
            </p>
            {hasOlderMessages ? (
              <button
                type="button"
                onClick={() => {
                  prevScrollHeightRef.current = scrollRef.current?.scrollHeight ?? 0;
                  onLoadOlder();
                }}
                disabled={loadingOlder}
                className="text-[11px] font-semibold uppercase tracking-[0.12em] text-cyan-300/90 hover:text-cyan-200 disabled:opacity-50"
              >
                {loadingOlder ? 'Loading…' : 'Load older'}
              </button>
            ) : null}
          </div>

          <div
            ref={scrollRef}
            onScroll={onScroll}
            className="flex max-h-[340px] min-h-[220px] flex-1 flex-col gap-2 overflow-y-auto overscroll-contain px-3 py-3"
          >
            {!selectedDriver && !activeConversation ? (
              <div className="m-auto max-w-md text-center text-sm text-slate-500">
                <p className="font-semibold text-white">No conversation selected</p>
                <p className="mt-2">
                  Choose a conversation from the list, or select a linked driver and use Open
                  Conversation.
                </p>
              </div>
            ) : null}

            {threadState === 'loading' && messages.length === 0 ? (
              <div className="m-auto flex items-center gap-2 text-sm text-slate-400">
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                Loading messages…
              </div>
            ) : null}

            {threadState === 'unauthorized' ? (
              <div className="m-auto max-w-md rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-4 text-center text-sm text-rose-50">
                <p className="font-medium">Unauthorized</p>
                <p className="mt-2 text-xs text-rose-100/80">
                  {threadError || "You don't have access to this conversation."}
                </p>
                <button
                  type="button"
                  onClick={onRetryThread}
                  className={cn(dispatcherDesignTokens.buttonSecondary, 'mt-3 px-3 py-1.5 text-xs')}
                >
                  Retry
                </button>
              </div>
            ) : null}

            {(threadState === 'unavailable' || threadState === 'error') &&
            activeConversation &&
            messages.length === 0 ? (
              <div className="m-auto max-w-md rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-center text-sm text-rose-50">
                <p className="font-medium">
                  {threadState === 'unavailable'
                    ? 'Conversation no longer available'
                    : 'Failed to load messages'}
                </p>
                <p className="mt-2 text-xs text-rose-100/80">{threadError}</p>
                <button
                  type="button"
                  onClick={onRetryThread}
                  className={cn(dispatcherDesignTokens.buttonSecondary, 'mt-3 px-3 py-1.5 text-xs')}
                >
                  Retry
                </button>
              </div>
            ) : null}

            {(threadState === 'error' || threadState === 'unavailable') &&
            messages.length > 0 ? (
              <div className="rounded-lg border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
                Refresh failed — showing last known messages.{' '}
                <button type="button" onClick={onRetryThread} className="underline">
                  Retry
                </button>
              </div>
            ) : null}

            {(threadState === 'empty' ||
              threadState === 'ready' ||
              threadState === 'sending' ||
              threadState === 'send_failed') &&
            messages.length === 0 &&
            activeConversation ? (
              <div className="m-auto text-center text-sm text-slate-500">
                <p className="font-medium text-slate-300">No messages yet</p>
                <p className="mt-1 text-xs text-slate-600">
                  {isClosed
                    ? 'This conversation is closed.'
                    : 'Send the first operational message below.'}
                </p>
              </div>
            ) : null}

            {messages.map((message) => {
              const mine =
                currentUserId != null && Number(message.senderUserId) === Number(currentUserId);
              const senderRole = activeConversation?.participants.find(
                (p) => Number(p.userId) === Number(message.senderUserId)
              )?.participantRole;
              return (
                <div
                  key={message.id}
                  className={cn('flex flex-col', mine ? 'items-end' : 'items-start')}
                >
                  <div
                    className={cn(
                      'max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed',
                      mine ? 'bg-cyan-500/20 text-cyan-50' : 'bg-white/10 text-slate-100'
                    )}
                  >
                    <p className="whitespace-pre-wrap break-words">{message.body}</p>
                  </div>
                  <p className="mt-1 text-[10px] text-slate-500">
                    {message.sender?.name ||
                      (mine ? 'You' : participantRoleLabel(senderRole))}
                    {!mine && senderRole ? ` · ${participantRoleLabel(senderRole)}` : null}
                    {' · '}
                    <span className="tabular-nums">{formatMessageTime(message.createdAt)}</span>
                  </p>
                </div>
              );
            })}
          </div>

          {/* Composer */}
          <div className="border-t border-white/5 p-3">
            {isClosed ? (
              <div className="flex items-center gap-2 text-xs text-slate-400">
                <Lock className="h-3.5 w-3.5" aria-hidden />
                Conversation closed — history is readable; sending is disabled.
              </div>
            ) : !canCompose ? (
              <p className="text-xs text-slate-500">
                {threadState === 'loading'
                  ? 'Composer disabled while messages load…'
                  : 'Composer inactive — open a conversation to send.'}
              </p>
            ) : (
              <>
                <div className="flex gap-2">
                  <textarea
                    value={composerText}
                    onChange={(e) => onComposerChange(e.target.value)}
                    onKeyDown={(e) => {
                      // Enter sends; Shift+Enter inserts newline (intentional).
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (composerText.trim() && !isSending) onSend();
                      }
                    }}
                    rows={2}
                    placeholder="Type an operational message… (Enter to send, Shift+Enter for new line)"
                    disabled={isSending || threadState === 'loading'}
                    className="min-h-[64px] flex-1 resize-y rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400/40 focus:outline-none disabled:opacity-60"
                  />
                  <button
                    type="button"
                    onClick={onSend}
                    disabled={isSending || !composerText.trim() || threadState === 'loading'}
                    className={cn(
                      dispatcherDesignTokens.buttonPrimary,
                      'h-auto shrink-0 self-end px-3 py-2 disabled:opacity-50'
                    )}
                    title={
                      !composerText.trim()
                        ? 'Message cannot be empty'
                        : isSending
                          ? 'Sending…'
                          : 'Send message'
                    }
                  >
                    {isSending ? (
                      <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                    ) : (
                      <Send className="h-4 w-4" aria-hidden />
                    )}
                  </button>
                </div>
                {sendError ? (
                  <p className="mt-2 text-xs text-rose-200">{sendError}</p>
                ) : null}
                {isSending ? (
                  <p className="mt-2 text-[11px] text-slate-500">Sending… waiting for server confirmation.</p>
                ) : null}
              </>
            )}
          </div>
        </div>

        {shipmentLoading ? (
          <p className="mt-2 text-[11px] text-slate-500">Loading shipment context…</p>
        ) : null}
      </div>
    );
  }
);
