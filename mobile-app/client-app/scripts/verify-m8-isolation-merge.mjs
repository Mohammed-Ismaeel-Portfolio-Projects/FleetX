/**
 * Active-conversation filter + Message.id merge (mirrors app UI logic).
 * Usage: node scripts/verify-m8-isolation-merge.mjs
 */

function sortMessagesChronologically(messages) {
  return [...messages].sort((a, b) => {
    const aTs = a.createdAt ? Date.parse(a.createdAt) : 0;
    const bTs = b.createdAt ? Date.parse(b.createdAt) : 0;
    const aSafe = Number.isFinite(aTs) ? aTs : 0;
    const bSafe = Number.isFinite(bTs) ? bTs : 0;
    if (aSafe !== bSafe) return aSafe - bSafe;
    return String(a.id).localeCompare(String(b.id));
  });
}

function mergeMessagesById(existing, incoming) {
  const map = new Map();
  for (const m of existing) map.set(String(m.id), m);
  const list = Array.isArray(incoming) ? incoming : [incoming];
  for (const m of list) map.set(String(m.id), m);
  return sortMessagesChronologically([...map.values()]);
}

/** Same gate as conversation/[id].tsx activeIdRef filter. */
function applyRealtimeToActive(activeId, messages, payload) {
  if (payload.conversation_id && payload.conversation_id !== activeId) {
    return messages;
  }
  if (!payload.id || !payload.conversation_id || payload.sender_user_id == null) {
    return messages;
  }
  if (payload.conversation_id !== activeId) return messages;
  return mergeMessagesById(messages, {
    id: payload.id,
    conversationId: payload.conversation_id,
    senderUserId: payload.sender_user_id,
    body: payload.body,
    sender: null,
    createdAt: payload.created_at ?? null,
    updatedAt: payload.updated_at ?? null,
  });
}

function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL:', msg);
    process.exit(1);
  }
}

const activeA = 'conv-a';
let threadA = [];

threadA = applyRealtimeToActive(activeA, threadA, {
  id: 'm1',
  conversation_id: 'conv-a',
  sender_user_id: 1,
  body: 'in A',
  created_at: '2026-08-09T10:00:00.000Z',
});
assert(threadA.length === 1, 'A message accepted');

threadA = applyRealtimeToActive(activeA, threadA, {
  id: 'm2',
  conversation_id: 'conv-b',
  sender_user_id: 1,
  body: 'in B while A open',
  created_at: '2026-08-09T10:01:00.000Z',
});
assert(threadA.length === 1, 'B message must not append to A');
assert(threadA[0].id === 'm1', 'A remains unchanged');

threadA = mergeMessagesById(threadA, {
  id: 'm3',
  conversationId: 'conv-a',
  senderUserId: 2,
  body: 'rest',
  sender: null,
  createdAt: '2026-08-09T10:02:00.000Z',
  updatedAt: '2026-08-09T10:02:00.000Z',
});
threadA = applyRealtimeToActive(activeA, threadA, {
  id: 'm3',
  conversation_id: 'conv-a',
  sender_user_id: 2,
  body: 'ws echo',
  created_at: '2026-08-09T10:02:00.000Z',
});
assert(threadA.filter((m) => m.id === 'm3').length === 1, 'Message.id dedupe');

console.log('OK: M8 isolation filter + Message.id merge');
