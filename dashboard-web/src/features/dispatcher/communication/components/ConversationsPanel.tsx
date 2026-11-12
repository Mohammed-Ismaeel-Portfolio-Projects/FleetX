import { MessageSquare, Package } from 'lucide-react';
import type { Conversation, MessagingLoadState } from '../types/messaging.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import { StatusBadge } from '../../../../shared/components/dispatcher/StatusBadge';
import { conversationTypeLabel, counterpartLabel } from '../utils/messagingGates';

interface ConversationsPanelProps {
  conversations: Conversation[];
  state: MessagingLoadState;
  error: string | null;
  selectedConversationId: string | null;
  onSelect: (conversation: Conversation) => void;
  onRetry: () => void;
}

function formatWhen(iso: string | null): string {
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

function statusTone(status: string): 'success' | 'neutral' | 'warning' {
  if (status === 'open') return 'success';
  if (status === 'closed') return 'neutral';
  return 'warning';
}

/**
 * M2 conversation list.
 * Ordering applied upstream: open first, then updated_at desc (API fields only).
 * No last-message preview / unread invent — list API does not expose them.
 */
export function ConversationsPanel({
  conversations,
  state,
  error,
  selectedConversationId,
  onSelect,
  onRetry,
}: ConversationsPanelProps) {
  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <MessageSquare className="h-4 w-4 text-cyan-300" aria-hidden />
            <h2 className="text-lg font-semibold text-white">Conversations</h2>
          </div>
          <p className="mt-1 text-sm text-slate-500">
            REST threads (Driver / Customer). Separate from Needs Attention.
          </p>
        </div>
        <span className="rounded-full border border-emerald-400/25 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-100">
          Open first · recent
        </span>
      </div>

      {error ? (
        <div className="mt-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
          <p>Refresh failed: {error}</p>
          {conversations.length > 0 ? (
            <p className="mt-1 text-xs text-amber-200/80">Showing last known conversations.</p>
          ) : (
            <button
              type="button"
              onClick={onRetry}
              className={cn(dispatcherDesignTokens.buttonSecondary, 'mt-2 px-3 py-1.5 text-xs')}
            >
              Retry conversations
            </button>
          )}
        </div>
      ) : null}

      <div className="mt-4 max-h-[320px] space-y-2 overflow-y-auto overscroll-contain pr-1">
        {state === 'loading' && conversations.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
            Loading conversations…
          </p>
        ) : null}

        {(state === 'empty' || (state === 'ready' && conversations.length === 0)) && !error ? (
          <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
            <p>No conversations yet.</p>
            <p className="mt-2 text-slate-600">
              Select a linked driver with an active shipment, then Open Conversation.
            </p>
          </div>
        ) : null}

        {(state === 'unauthorized' || state === 'error' || state === 'unavailable') &&
        conversations.length === 0 ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-6 text-center text-sm text-rose-100">
            <p>Conversations unavailable.</p>
            <button
              type="button"
              onClick={onRetry}
              className={cn(dispatcherDesignTokens.buttonSecondary, 'mt-3 px-3 py-1.5 text-xs')}
            >
              Retry
            </button>
          </div>
        ) : null}

        {conversations.map((conversation) => {
          const selected = conversation.id === selectedConversationId;
          const counterpart = counterpartLabel(conversation.type, conversation.participants);
          const shipmentLabel =
            conversation.shipment?.shipmentNumber ||
            (conversation.shipmentId
              ? `Shipment ${conversation.shipmentId.slice(0, 8)}…`
              : null);
          const typeShort =
            conversation.type === 'dispatcher_driver'
              ? 'Driver'
              : conversation.type === 'dispatcher_customer'
                ? 'Customer'
                : conversationTypeLabel(conversation.type);

          return (
            <button
              key={conversation.id}
              type="button"
              onClick={() => onSelect(conversation)}
              className={cn(
                'w-full rounded-2xl border px-3 py-3 text-left transition',
                selected
                  ? 'border-cyan-400/40 bg-cyan-500/15 ring-1 ring-cyan-400/30'
                  : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-slate-100">{counterpart}</p>
                  <p className="mt-0.5 text-[11px] uppercase tracking-[0.12em] text-slate-500">
                    {typeShort}
                  </p>
                </div>
                <StatusBadge
                  label={conversation.status || 'Unavailable'}
                  tone={statusTone(conversation.status)}
                />
              </div>
              <div className="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                {shipmentLabel ? (
                  <span className="inline-flex items-center gap-1">
                    <Package className="h-3 w-3" aria-hidden />
                    {shipmentLabel}
                  </span>
                ) : (
                  <span>Shipment: Unavailable</span>
                )}
                <span className="tabular-nums">{formatWhen(conversation.updatedAt)}</span>
              </div>
              {conversation.lastReadAt ? (
                <p className="mt-1 text-[10px] text-slate-600">
                  Your last read: {formatWhen(conversation.lastReadAt)}
                </p>
              ) : null}
            </button>
          );
        })}
      </div>
    </div>
  );
}
