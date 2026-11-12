/**
 * Conversation realtime helpers (M5).
 *
 * REST remains authoritative. Echo is an optional delivery path.
 * Do not enable unless a real WS transport is configured.
 */

import type { ConnectionStatus } from '../../../../core/realtime/interfaces/realtime-connection.interface';
import { realtimeConnectionService } from '../../../../core/realtime/services/realtime-connection.service';
import { mapApiMessage } from '../services/messaging.service';
import type { Message } from '../types/messaging.types';

export type MessagingRealtimeStatus =
  | 'disabled'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'disconnected'
  | 'permission_denied'
  | 'error';

/** Explicit opt-in — never claim live merely because Echo packages exist. */
export function isMessagingRealtimeEnabled(): boolean {
  return import.meta.env.VITE_REALTIME_ENABLED === 'true';
}

export function conversationChannelName(conversationId: string): string {
  return `conversation.${conversationId}`;
}

export const MESSAGE_CREATED_EVENT = '.message.created';

export interface MessageCreatedPayload {
  id?: string;
  conversation_id?: string;
  sender_user_id?: number;
  body?: string;
  sender?: { id: number; name: string; email: string } | null;
  created_at?: string | null;
  updated_at?: string | null;
  conversation_updated_at?: string | null;
}

export function isCompleteMessagePayload(
  payload: MessageCreatedPayload | null | undefined
): payload is MessageCreatedPayload & {
  id: string;
  conversation_id: string;
  sender_user_id: number;
  body: string;
} {
  return Boolean(
    payload &&
      typeof payload.id === 'string' &&
      payload.id.length > 0 &&
      typeof payload.conversation_id === 'string' &&
      payload.conversation_id.length > 0 &&
      payload.sender_user_id != null &&
      typeof payload.body === 'string'
  );
}

export function mapRealtimeMessage(payload: MessageCreatedPayload): Message | null {
  if (!isCompleteMessagePayload(payload)) return null;
  return mapApiMessage({
    id: payload.id,
    conversation_id: payload.conversation_id,
    sender_user_id: payload.sender_user_id,
    body: payload.body,
    sender: payload.sender ?? null,
    created_at: payload.created_at ?? null,
    updated_at: payload.updated_at ?? null,
  });
}

export function mapConnectionStatus(
  status: ConnectionStatus,
  enabled: boolean
): MessagingRealtimeStatus {
  if (!enabled) return 'disabled';
  if (status === 'connected') return 'connected';
  if (status === 'connecting') return 'connecting';
  if (status === 'reconnecting') return 'reconnecting';
  if (status === 'error') return 'error';
  return 'disconnected';
}

/**
 * Ensure the shared Echo singleton is connected (same instance as Tracking).
 * No-op when realtime is not explicitly enabled.
 */
export async function ensureMessagingRealtimeConnection(): Promise<MessagingRealtimeStatus> {
  if (!isMessagingRealtimeEnabled()) {
    return 'disabled';
  }

  try {
    await realtimeConnectionService.connect();
    return mapConnectionStatus(realtimeConnectionService.state.status, true);
  } catch {
    return 'error';
  }
}

export function getMessagingRealtimeStatus(): MessagingRealtimeStatus {
  if (!isMessagingRealtimeEnabled()) return 'disabled';
  return mapConnectionStatus(realtimeConnectionService.state.status, true);
}

/**
 * Subscribe to a conversation private channel for message.created.
 * Returns unsubscribe that removes the listener and leaves the channel.
 */
export async function subscribeConversationMessages(
  conversationId: string,
  onMessage: (payload: MessageCreatedPayload) => void,
  onError?: (error: Error) => void
): Promise<() => void> {
  if (!isMessagingRealtimeEnabled()) {
    return () => {};
  }

  const channel = conversationChannelName(conversationId);

  try {
    await ensureMessagingRealtimeConnection();
    await realtimeConnectionService.subscribe(channel);
    const off = realtimeConnectionService.on<MessageCreatedPayload>(
      channel,
      MESSAGE_CREATED_EVENT,
      onMessage
    );

    return () => {
      off();
      void realtimeConnectionService.unsubscribe(channel);
    };
  } catch (error) {
    onError?.(error instanceof Error ? error : new Error('Conversation realtime subscribe failed'));
    return () => {};
  }
}

/**
 * Chronological sort by created_at then id (deterministic).
 */
export function sortMessagesChronologically(messages: Message[]): Message[] {
  return [...messages].sort((a, b) => {
    const aTs = a.createdAt ? Date.parse(a.createdAt) : 0;
    const bTs = b.createdAt ? Date.parse(b.createdAt) : 0;
    const aSafe = Number.isFinite(aTs) ? aTs : 0;
    const bSafe = Number.isFinite(bTs) ? bTs : 0;
    if (aSafe !== bSafe) return aSafe - bSafe;
    return String(a.id).localeCompare(String(b.id));
  });
}
