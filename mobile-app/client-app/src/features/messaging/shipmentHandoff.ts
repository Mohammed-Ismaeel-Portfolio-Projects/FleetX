import { listConversations } from './messagingApi';
import type { Conversation, ConversationType } from './types';

/**
 * Find an already-authorized conversation for a shipment.
 * Does NOT create conversations (M4 opt-in preserved).
 * Never returns a conversation for a different shipment.
 */
export async function findAuthorizedConversationForShipment(
  shipmentId: string,
  type?: ConversationType | string
): Promise<Conversation | null> {
  if (!shipmentId) return null;
  const { conversations } = await listConversations({
    shipment_id: shipmentId,
    ...(type ? { type } : {}),
    per_page: 20,
  });
  return conversations.find((c) => String(c.shipmentId) === String(shipmentId)) ?? null;
}
