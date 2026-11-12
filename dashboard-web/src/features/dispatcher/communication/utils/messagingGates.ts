import type { Driver } from '../../drivers/types/driver-operations.types';
import type { DriverMessagingGate } from '../types/messaging.types';

/**
 * Gate for opening a dispatcher_driver conversation from a selected Driver contact.
 * Uses only Drivers SSOT fields (userId + active assignment shipment).
 */
export function resolveDriverMessagingGate(driver: Driver | null): DriverMessagingGate {
  if (!driver) return { kind: 'no_driver' };
  if (driver.userId == null) return { kind: 'unlinked' };
  const shipmentId = driver.assignment?.shipmentId ?? null;
  if (!shipmentId) return { kind: 'no_shipment' };
  return { kind: 'ready', shipmentId, driverUserId: driver.userId };
}

export function conversationTypeLabel(type: string): string {
  if (type === 'dispatcher_driver') return 'Dispatcher ↔ Driver';
  if (type === 'dispatcher_customer') return 'Dispatcher ↔ Customer';
  return type || 'Conversation';
}

export function counterpartLabel(
  type: string,
  participants: Array<{ participantRole: string; user: { name: string } | null }>
): string {
  const role =
    type === 'dispatcher_customer'
      ? 'client'
      : type === 'dispatcher_driver'
        ? 'driver'
        : null;
  if (role) {
    const match = participants.find((p) => p.participantRole === role);
    if (match?.user?.name) return match.user.name;
  }
  const other = participants.find((p) => p.participantRole !== 'dispatcher');
  return other?.user?.name ?? 'Unavailable';
}
