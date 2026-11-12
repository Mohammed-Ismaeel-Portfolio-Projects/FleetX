/**
 * Lightweight unit checks for Message.id merge (no Jest required).
 * Usage: node scripts/verify-message-merge.mjs
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
  return sortMessagesChronologically(Array.from(map.values()));
}

function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL:', msg);
    process.exit(1);
  }
}

const t1 = '2026-08-08T10:00:00.000Z';
const t2 = '2026-08-08T10:01:00.000Z';

const rest = {
  id: 'msg-1',
  conversationId: 'c1',
  senderUserId: 1,
  body: 'hello',
  sender: null,
  createdAt: t1,
  updatedAt: t1,
};

const wsDup = { ...rest, body: 'hello (ws echo)' };
const merged = mergeMessagesById([rest], wsDup);
assert(merged.length === 1, 'dedupe by Message.id');
assert(merged[0].body === 'hello (ws echo)', 'later merge wins for same id');

const other = {
  id: 'msg-2',
  conversationId: 'c1',
  senderUserId: 2,
  body: 'world',
  sender: null,
  createdAt: t2,
  updatedAt: t2,
};
const ordered = mergeMessagesById([other], rest);
assert(ordered.map((m) => m.id).join(',') === 'msg-1,msg-2', 'chronological order');

const cross = mergeMessagesById([], {
  ...other,
  conversationId: 'c2',
});
assert(cross[0].conversationId === 'c2', 'preserves conversation id for isolation checks');

console.log('OK: message merge / Message.id dedupe');
