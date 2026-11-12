/** Shared FleetX mobile messaging types — mirror dashboard M2 contract. */

export type ConversationType = 'dispatcher_driver' | 'dispatcher_customer';

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

export interface Conversation {
  id: string;
  type: ConversationType | string;
  status: string;
  shipmentId: string;
  assignmentId: string | number | null;
  shipment: ConversationShipmentRef | null;
  participants: ConversationParticipant[];
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
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: { id: number; name: string } | null;
  driver?: {
    id: string;
    employee_number?: string;
  } | null;
  company?: {
    id: string;
    company_code?: string;
    company_name?: string;
  } | null;
}

export interface AuthIdentity {
  user_id: number;
  role: string | null;
  linked: boolean;
  unlink_reason: string | null;
  driver: {
    id: string;
    employee_number?: string;
  } | null;
  company: {
    id: string;
    company_name?: string;
    company_code?: string;
  } | null;
}

export type RealtimeStatus =
  | 'disabled'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'disconnected'
  | 'permission_denied'
  | 'error'
  | 'rest_fallback';
