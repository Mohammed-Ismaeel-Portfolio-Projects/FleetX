/**
 * Driver messaging — re-exports existing messagingApi (dispatcher_driver only at call sites).
 * Does not add conversation creation.
 */
export {
  listConversations,
  getConversation,
  loadNewestMessages,
  loadOlderMessages,
  sendMessage,
  markConversationRead,
} from '@/features/messaging/messagingApi';
