/**
 * C2.8-B — Client Notifications contract design (static verification).
 * DESIGN ONLY — proves docs + that no notification implementation was added.
 * Run: node ./scripts/verify-c28b-client-notifications-contract.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
}

let passed = 0;
function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    console.error(`FAIL  ${name}`);
    console.error(e);
    process.exitCode = 1;
  }
}

const docPath = path.join(repoRoot, 'docs/C2.8B_CLIENT_NOTIFICATIONS_CONTRACT.md');
const doc = fs.readFileSync(docPath, 'utf8');
const apiPhp = readRepo('backend/routes/api.php');
const liveNotifications = readClient('src/app/notifications.tsx');
const home = readClient('src/app/(tabs)/index.tsx');
const profile = readClient('src/app/(tabs)/profile.tsx');

check('contract document exists with required sections', () => {
  assert.ok(fs.existsSync(docPath));
  for (const section of [
    'Scope',
    'Existing-system evidence',
    'Ownership model',
    'Resource allowlist',
    'Notification type vocabulary',
    'Deep-link contract',
    'API surface',
    'Read / unread semantics',
    'Pagination / order rules',
    'Error semantics',
    'Security matrix',
    'Notification writers',
    'Realtime boundary',
    'Product decisions',
    'Implementation prerequisites',
    'Explicit exclusions',
  ]) {
    assert.match(doc, new RegExp(section.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), section);
  }
});

check('API surface: list, unread count, mark-read, mark-all-read under client namespace', () => {
  assert.match(doc, /GET \/api\/v1\/client\/notifications/);
  assert.match(doc, /GET \/api\/v1\/client\/notifications\/unread-count/);
  assert.match(doc, /POST \/api\/v1\/client\/notifications\/\{notification\}\/read/);
  assert.match(doc, /POST \/api\/v1\/client\/notifications\/read-all/);
});

check('ownership is auth-user scoped; ownership params rejected', () => {
  assert.match(doc, /notifications\.user_id === auth\(\)->id\(\)|user_id = authenticated_user\.id|user_id = auth\(\)->id\(\)/);
  assert.match(doc, /MUST NOT.*user_id|MUST NOT\*\* submit `user_id`/i);
  assert.match(doc, /company_id/);
  assert.match(doc, /\*\*422\*\*/);
  assert.match(doc, /Client-side filtering is not security/i);
});

check('security matrix + 404 foreign + deep-link AuthZ invariant', () => {
  assert.match(doc, /Security matrix/i);
  assert.match(doc, /Unauthenticated/);
  assert.match(doc, /Unlinked Client/);
  assert.match(doc, /Foreign user/);
  assert.match(doc, /A notification deep link MUST NOT grant access/i);
  assert.match(doc, /independently.*enforc/i);
  assert.match(doc, /Foreign user’s notification|Foreign user's notification|other user’s notification|other user's notification/i);
  assert.match(doc, /\*\*404\*\*/);
});

check('MVP vocabulary excludes GPS/driver/fleet; writers/blockers documented', () => {
  assert.match(doc, /shipment\.status/);
  assert.match(doc, /message\.new/);
  assert.match(doc, /system\.notice/);
  assert.match(doc, /GPS/);
  assert.match(doc, /BLOCKED — NO VERIFIED SOURCE EVENT/);
  assert.match(doc, /MessageCreated/);
  assert.match(doc, /Issues/);
});

check('realtime/push excluded from MVP; messaging read separate', () => {
  assert.match(doc, /Realtime boundary/i);
  assert.match(doc, /must not.*require realtime|No WebSockets for inbox|Push\/realtime out of MVP/i);
  assert.match(doc, /Conversation \*\*read receipts\*\*|conversation `markRead`|Messaging read/i);
  assert.match(doc, /separate/i);
});

check('C2.8-D1 inbox uses client API; no Issues substitute; Home has no fake badge', () => {
  assert.match(liveNotifications, /listClientNotifications/);
  assert.equal(liveNotifications.includes('apiClient'), false); // screen uses feature module, not raw client
  assert.equal(liveNotifications.includes('/issues'), false);
  assert.equal(home.includes('unreadBadge'), false);
  assert.equal(home.includes('unread_count'), false);
  assert.equal(home.includes('home-notifications-unavailable'), false);
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/features/notifications')));
  assert.match(apiPhp, /ClientNotificationController/);
  assert.equal(home.includes('/issues'), false);
});

check('no fake notification cards/badges in production notifications UI', () => {
  // Real mark-all action is allowed (C2.8-D1); archive-style unreadBadge / AI mock must not appear.
  assert.equal(liveNotifications.includes('unreadBadge'), false);
  assert.equal(liveNotifications.includes('AI DAILY SUMMARY'), false);
  assert.match(liveNotifications, /listClientNotifications|No notifications yet/);
  assert.equal(profile.includes('unread_count'), false);
  assert.equal(profile.includes('unreadBadge'), false);
});

check('phase is design-only (doc + verify script; DESIGN ONLY language)', () => {
  assert.match(doc, /DESIGN ONLY/i);
  assert.match(doc, /Implementation started\?/i);
  assert.match(doc, /\|\s*\*\*No\*\*\s*\|/);
  assert.ok(
    fs.existsSync(path.join(clientRoot, 'scripts/verify-c28b-client-notifications-contract.mjs'))
  );
});

if (process.exitCode) {
  console.error(`\nC2.8-B verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.8-B verification OK — ${passed} checks passed.`);
}
