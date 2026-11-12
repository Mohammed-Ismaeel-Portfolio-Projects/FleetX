/** M2 conversation types — match backend Conversation model. */
export type ConversationType = 'dispatcher_driver' | 'dispatcher_customer';

export type ConversationStatus = 'open' | 'closed';

export type MessagingLoadState =
  | 'idle'
  | 'loading'
  | 'ready'
  | 'empty'
  | 'unavailable'
  | 'unauthorized'
  | 'error'
  | 'sending'
  | 'send_failed';

export interface ConversationParticipantUser {
  id: number;
  name: string;
  email: string;
}

export interface ConversationParticipant {
  userId: number;
  participantRole: string;
  lastReadAt: string | null;
  user: ConversationParticipantUser | null;
}

export interface ConversationShipmentRef {
  id: string;
  shipmentNumber: string | null;
}

/** Mapped M2 ConversationResource (fields actually returned). */
export interface Conversation {
  id: string;
  type: ConversationType | string;
  status: ConversationStatus | string;
  shipmentId: string;
  assignmentId: string | number | null;
  shipment: ConversationShipmentRef | null;
  participants: ConversationParticipant[];
  /** Current actor's last_read_at when provided by API. */
  lastReadAt: string | null;
  createdBy: number | null;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface MessageSender {
  id: number;
  name: string;
  email: string;
}

/** Mapped M2 MessageResource. */
export interface Message {
  id: string;
  conversationId: string;
  senderUserId: number;
  body: string;
  sender: MessageSender | null;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface MessagingPaginationMeta {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
  from: number | null;
  to: number | null;
}

export interface OpenConversationPayload {
  type: ConversationType;
  shipment_id: string;
  driver_user_id?: number;
  client_user_id?: number;
}

export type DriverMessagingGate =
  | { kind: 'no_driver' }
  | { kind: 'unlinked' }
  | { kind: 'no_shipment' }
  | { kind: 'ready'; shipmentId: string; driverUserId: number };
