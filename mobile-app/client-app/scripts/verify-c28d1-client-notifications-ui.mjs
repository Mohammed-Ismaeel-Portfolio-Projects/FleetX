/**
 * C2.8-D1 — Client Notifications inbox UI (static verification).
 * C2.9-C3.8 later decision: structured action destinations are allowed via
 * resolveClientNotificationDestination. Safety assertions retained: no Issues
 * substitute, no invented IDs from title/message, Client API only.
 * Run: node ./scripts/verify-c28d1-client-notifications-ui.mjs
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

const screen = readClient('src/app/notifications.tsx');
const api = readClient('src/features/notifications/notificationsApi.ts');
const types = readClient('src/features/notifications/types.ts');
const home = readClient('src/app/(tabs)/index.tsx');
const layout = readClient('src/app/_layout.tsx');
const docPath = path.join(repoRoot, 'docs/C2.8D1_CLIENT_NOTIFICATIONS_INBOX_UI.md');

check('notifications feature module + types exist', () => {
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/features/notifications/notificationsApi.ts')));
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/features/notifications/types.ts')));
  assert.match(types, /ClientNotification/);
  assert.match(types, /isRead/);
  assert.match(types, /action/);
});

check('API uses only client notifications endpoints; no ownership params', () => {
  assert.match(api, /\/client\/notifications/);
  assert.match(api, /\/client\/notifications\/unread-count/);
  assert.match(api, /\/client\/notifications\/\$\{encodeURIComponent\(id\)\}\/read/);
  assert.match(api, /\/client\/notifications\/read-all/);
  assert.match(api, /apiClient\.get[\s\S]*\/client\/notifications/);
  assert.match(api, /apiClient\.patch/);
  assert.match(api, /apiClient\.post/);
  assert.equal(api.includes('/issues'), false);
  assert.equal(api.includes('live-fleet'), false);
  assert.equal(api.includes('tracking-logs'), false);
  assert.equal(api.includes('conversations'), false);
  assert.match(api, /delete \(query as \{ user_id/);
  assert.match(api, /company_id/);
  assert.match(api, /customer_company_id/);
  assert.match(api, /mapClientNotificationAction/);
  assert.equal(/title\.match|message\.match/.test(api), false);
});

check('screen consumes feature API; destinations only via resolver (C3.8)', () => {
  assert.match(screen, /listClientNotifications/);
  assert.match(screen, /getClientNotificationsUnreadCount/);
  assert.match(screen, /markClientNotificationRead/);
  assert.match(screen, /markAllClientNotificationsRead/);
  assert.match(screen, /resolveClientNotificationDestination/);
  assert.equal(screen.includes('/issues'), false);
  assert.equal(screen.includes('live-fleet'), false);
  assert.equal(screen.includes('FX-218'), false);
  assert.equal(screen.includes('AI DAILY SUMMARY'), false);
  assert.equal(/title\.match|message\.match/.test(screen), false);
  assert.match(layout, /name="notifications"/);
});

check('required UI states present', () => {
  assert.match(screen, /notifications-loading/);
  assert.match(screen, /notifications-list/);
  assert.match(screen, /notifications-empty/);
  assert.match(screen, /No notifications yet/);
  assert.match(screen, /notifications-error/);
  assert.match(screen, /notifications-retry/);
  assert.match(screen, /notifications-forbidden/);
  assert.match(screen, /statusCode === 401/);
  assert.match(screen, /errorStatus === 403|isForbidden/);
  assert.match(screen, /UnavailableDomainScreen/);
  assert.match(screen, /notifications-unlinked|notifications-forbidden-role/);
});

check('pagination + request safety', () => {
  assert.match(screen, /inFlightRef/);
  assert.match(screen, /requestSeqRef/);
  assert.match(screen, /RefreshControl/);
  assert.match(screen, /onEndReached/);
  assert.match(screen, /loadPage\(.*'append'/);
  assert.match(screen, /errorSource === 'refresh'/);
  assert.match(screen, /errorSource === 'append'/);
  assert.match(screen, /PAGE_SIZE = 15/);
});

check('read/unread + mark-read + mark-all-read', () => {
  assert.match(screen, /isRead/);
  assert.match(screen, /Unread/);
  assert.match(screen, /markClientNotificationRead/);
  assert.match(screen, /notifications-mark-all-read/);
  assert.match(screen, /markAllClientNotificationsRead/);
  assert.match(screen, /notification-read-label/);
  assert.match(screen, /notification-unread-dot-|notification-unread-/);
});

check('no forbidden PII fields in screen/API mapping', () => {
  assert.equal(/user_id|company_id|latitude|longitude|driver|vehicle_plate/i.test(screen), false);
  assert.equal(api.includes('latitude'), false);
  assert.equal(api.includes('driver'), false);
  assert.match(api, /mapNotification/);
  assert.match(types, /action: ClientNotificationAction/);
});

check('accessibility / testIDs', () => {
  assert.match(screen, /accessibilityLabel/);
  assert.match(screen, /accessibilityRole="button"/);
  assert.match(screen, /notifications-back/);
  assert.match(screen, /notification-card-|notification-item-/);
  assert.match(screen, /Marks this notification as read|Mark all notifications as read/);
});

check('Home header uses real unread-count (C3.2); compact preview may list recent items (C3.3)', () => {
  assert.equal(home.includes('unreadBadge'), false);
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.match(home, /home-notifications-header/);
  assert.equal(home.includes('home-notifications-unavailable'), false);
});

check('implementation doc exists', () => {
  assert.ok(fs.existsSync(docPath));
  const body = fs.readFileSync(docPath, 'utf8');
  assert.match(body, /C2\.8-D1/);
  assert.match(body, /Endpoint/);
  assert.match(body, /Deferred/i);
});

if (process.exitCode) {
  console.error(`\nC2.8-D1 verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.8-D1 verification OK — ${passed} checks passed.`);
}
