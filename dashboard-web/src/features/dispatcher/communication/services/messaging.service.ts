import { axiosClientWithInterceptors } from '../../../../core/api';
import type {
  Conversation,
  ConversationParticipant,
  ConversationType,
  Message,
  MessagingPaginationMeta,
  OpenConversationPayload,
} from '../types/messaging.types';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message: string;
}

interface PaginatedPayload<T> {
  data: T[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
  };
  links?: {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
  };
}

interface ConversationApi {
  id: string;
  type: string;
  status: string;
  shipment_id: string;
  assignment_id?: string | number | null;
  shipment?: { id: string; shipment_number?: string | null } | null;
  participants?: Array<{
    user_id: number;
    participant_role: string;
    last_read_at?: string | null;
    user?: { id: number; name: string; email: string } | null;
  }>;
  last_read_at?: string | null;
  created_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface MessageApi {
  id: string;
  conversation_id: string;
  sender_user_id: number;
  body: string;
  sender?: { id: number; name: string; email: string } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export class MessagingApiError extends Error {
  statusCode?: number;

  constructor(message: string, statusCode?: number) {
    super(message);
    this.name = 'MessagingApiError';
    this.statusCode = statusCode;
  }
}

function toMessagingError(error: unknown, fallback: string): MessagingApiError {
  if (error instanceof MessagingApiError) return error;
  const err = error as {
    response?: { status?: number; data?: { message?: string } };
    message?: string;
  };
  const message = err?.response?.data?.message || err?.message || fallback;
  const statusCode = err?.response?.status;
  return new MessagingApiError(message, statusCode);
}

function mapParticipant(
  p: NonNullable<ConversationApi['participants']>[number]
): ConversationParticipant {
  return {
    userId: Number(p.user_id),
    participantRole: p.participant_role,
    lastReadAt: p.last_read_at ?? null,
    user: p.user
      ? {
          id: Number(p.user.id),
          name: p.user.name,
          email: p.user.email,
        }
      : null,
  };
}

export function mapApiConversation(api: ConversationApi): Conversation {
  return {
    id: api.id,
    type: api.type,
    status: api.status,
    shipmentId: api.shipment_id,
    assignmentId: api.assignment_id ?? null,
    shipment: api.shipment
      ? {
          id: api.shipment.id,
          shipmentNumber: api.shipment.shipment_number ?? null,
        }
      : null,
    participants: (api.participants ?? []).map(mapParticipant),
    lastReadAt: api.last_read_at ?? null,
    createdBy: api.created_by ?? null,
    createdAt: api.created_at ?? null,
    updatedAt: api.updated_at ?? null,
  };
}

export function mapApiMessage(api: MessageApi): Message {
  return {
    id: api.id,
    conversationId: api.conversation_id,
    senderUserId: Number(api.sender_user_id),
    body: api.body,
    sender: api.sender
      ? {
          id: Number(api.sender.id),
          name: api.sender.name,
          email: api.sender.email,
        }
      : null,
    createdAt: api.created_at ?? null,
    updatedAt: api.updated_at ?? null,
  };
}

function mapMeta(meta: PaginatedPayload<unknown>['meta']): MessagingPaginationMeta {
  return {
    currentPage: Number(meta.current_page) || 1,
    perPage: Number(meta.per_page) || 30,
    total: Number(meta.total) || 0,
    lastPage: Number(meta.last_page) || 1,
    from: meta.from ?? null,
    to: meta.to ?? null,
  };
}

export type ListConversationsParams = {
  type?: ConversationType | string;
  shipment_id?: string;
  status?: string;
  page?: number;
  per_page?: number;
};

export async function listConversations(
  params?: ListConversationsParams
): Promise<{ conversations: Conversation[]; meta: MessagingPaginationMeta }> {
  try {
    const response = await axiosClientWithInterceptors.get<
      ApiEnvelope<PaginatedPayload<ConversationApi>>
    >('/conversations', { params });
    const payload = response.data.data;
    return {
      conversations: (payload.data || []).map(mapApiConversation),
      meta: mapMeta(payload.meta),
    };
  } catch (error) {
    throw toMessagingError(error, 'Failed to load conversations');
  }
}

export async function getConversation(id: string): Promise<Conversation> {
  try {
    const response = await axiosClientWithInterceptors.get<ApiEnvelope<ConversationApi>>(
      `/conversations/${id}`
    );
    return mapApiConversation(response.data.data);
  } catch (error) {
    throw toMessagingError(error, 'Failed to load conversation');
  }
}

export async function openConversation(
  payload: OpenConversationPayload
): Promise<Conversation> {
  try {
    const response = await axiosClientWithInterceptors.post<ApiEnvelope<ConversationApi>>(
      '/conversations',
      payload
    );
    return mapApiConversation(response.data.data);
  } catch (error) {
    throw toMessagingError(error, 'Failed to open conversation');
  }
}

export async function listMessages(
  conversationId: string,
  params?: { page?: number; per_page?: number }
): Promise<{ messages: Message[]; meta: MessagingPaginationMeta }> {
  try {
    const response = await axiosClientWithInterceptors.get<
      ApiEnvelope<PaginatedPayload<MessageApi>>
    >(`/conversations/${conversationId}/messages`, { params });
    const payload = response.data.data;
    return {
      messages: (payload.data || []).map(mapApiMessage),
      meta: mapMeta(payload.meta),
    };
  } catch (error) {
    throw toMessagingError(error, 'Failed to load messages');
  }
}

export async function sendMessage(conversationId: string, body: string): Promise<Message> {
  try {
    const response = await axiosClientWithInterceptors.post<ApiEnvelope<MessageApi>>(
      `/conversations/${conversationId}/messages`,
      { body }
    );
    return mapApiMessage(response.data.data);
  } catch (error) {
    throw toMessagingError(error, 'Failed to send message');
  }
}

export async function markConversationRead(
  conversationId: string
): Promise<{ conversationId: string; userId: number; lastReadAt: string | null }> {
  try {
    const response = await axiosClientWithInterceptors.post<
      ApiEnvelope<{ conversation_id: string; user_id: number; last_read_at?: string | null }>
    >(`/conversations/${conversationId}/read`);
    const data = response.data.data;
    return {
      conversationId: data.conversation_id,
      userId: Number(data.user_id),
      lastReadAt: data.last_read_at ?? null,
    };
  } catch (error) {
    throw toMessagingError(error, 'Failed to mark conversation read');
  }
}

/**
 * Load newest message page for a conversation using M2 ascending pagination.
 * Page 1 = oldest; last_page = newest chunk.
 */
export async function loadNewestMessages(
  conversationId: string,
  perPage = 50
): Promise<{
  messages: Message[];
  meta: MessagingPaginationMeta;
  oldestLoadedPage: number;
  hasOlder: boolean;
}> {
  const probe = await listMessages(conversationId, { page: 1, per_page: perPage });
  if (probe.meta.lastPage <= 1) {
    return {
      messages: probe.messages,
      meta: probe.meta,
      oldestLoadedPage: 1,
      hasOlder: false,
    };
  }

  const newest = await listMessages(conversationId, {
    page: probe.meta.lastPage,
    per_page: perPage,
  });
  return {
    messages: newest.messages,
    meta: newest.meta,
    oldestLoadedPage: newest.meta.currentPage,
    hasOlder: newest.meta.currentPage > 1,
  };
}

export async function loadOlderMessages(
  conversationId: string,
  olderThanPage: number,
  perPage = 50
): Promise<{ messages: Message[]; meta: MessagingPaginationMeta; page: number } | null> {
  if (olderThanPage <= 1) return null;
  const page = olderThanPage - 1;
  const result = await listMessages(conversationId, { page, per_page: perPage });
  return { messages: result.messages, meta: result.meta, page };
}
