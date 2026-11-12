import type { Message } from './types';

/** Chronological sort by created_at then Message.id (deterministic). */
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

/**
 * Merge messages deduplicating strictly by Message.id.
 * Never dedupe by body or timestamp.
 */
export function mergeMessagesById(
  existing: Message[],
  incoming: Message | Message[]
): Message[] {
  const map = new Map<string, Message>();
  for (const m of existing) {
    map.set(String(m.id), m);
  }
  const list = Array.isArray(incoming) ? incoming : [incoming];
  for (const m of list) {
    map.set(String(m.id), m);
  }
  return sortMessagesChronologically(Array.from(map.values()));
}

export function formatMessageTime(iso: string | null): string {
  if (!iso) return 'Unavailable';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return 'Unavailable';
  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
