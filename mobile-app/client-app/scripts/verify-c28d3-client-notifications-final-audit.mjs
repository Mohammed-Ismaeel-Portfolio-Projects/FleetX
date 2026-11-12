/**
 * C2.8-D3 — Client Notifications final MVP audit (static verification only).
 * C2.9-C3.8 later decision: `action` may be { kind, id } from the Client-safe
 * projection. Still forbids invented deep links, Issues, and leaked user_id.
 * Run: node ./scripts/verify-c28d3-client-notifications-final-audit.mjs
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

const auditDoc = path.join(repoRoot, 'docs/C2.8D3_CLIENT_NOTIFICATIONS_FINAL_AUDIT.md');
const screen = readClient('src/app/notifications.tsx');
const api = readClient('src/features/notifications/notificationsApi.ts');
const types = readClient('src/features/notifications/types.ts');
const home = readClient('src/app/(tabs)/index.tsx');
const layout = readClient('src/app/_layout.tsx');
const routes = readRepo('backend/routes/api.php');
const resource = readRepo('backend/app/Http/Resources/Client/ClientNotificationResource.php');
const queryAction = readRepo('backend/app/Actions/Notification/QueryClientNotificationsAction.php');
const markAction = readRepo('backend/app/Actions/Notification/MarkClientNotificationReadAction.php');
const markAllAction = readRepo(
  'backend/app/Actions/Notification/MarkAllClientNotificationsReadAction.php'
);
const appProvider = readRepo('backend/app/Providers/AppServiceProvider.php');
const featureTest = readRepo('backend/tests/Feature/Notification/ClientNotificationTest.php');

check('final audit document exists with required sections', () => {
  assert.ok(fs.existsSync(auditDoc));
  const body = fs.readFileSync(auditDoc, 'utf8');
  for (const section of [
    'Executive result',
    'API boundary',
    'Security',
    'UI states',
    'Read / unread',
    'Pagination',
    'Deep links',
    'Cross-phase consistency',
    'Findings',
    'Deferred',
    'Final recommendation',
  ]) {
    assert.match(body, new RegExp(section, 'i'), section);
  }
  assert.match(body, /PASS WITH FINDINGS|PASS\b/);
  assert.match(body, /COMPLETE/);
});

check('Client App uses only approved client notification endpoints', () => {
  assert.match(api, /\/client\/notifications'/);
  assert.match(api, /\/client\/notifications\/unread-count/);
  assert.match(api, /\/client\/notifications\/\$\{encodeURIComponent\(id\)\}\/read/);
  assert.match(api, /\/client\/notifications\/read-all/);
  assert.match(api, /apiClient\.patch/);
  assert.match(screen, /listClientNotifications/);
  assert.match(screen, /markClientNotificationRead/);
  assert.match(screen, /markAllClientNotificationsRead/);
  assert.match(screen, /getClientNotificationsUnreadCount/);
  assert.equal(api.includes('/issues'), false);
  assert.equal(screen.includes('/issues'), false);
  assert.equal(api.includes('live-fleet'), false);
  assert.equal(api.includes('dashboard'), false);
  assert.equal(api.includes('/conversations'), false);
});

check('ownership params rejected; server ownedBy scoping', () => {
  assert.match(api, /user_id/);
  assert.match(api, /company_id/);
  assert.match(api, /customer_company_id/);
  assert.match(api, /delete \(query as \{ user_id/);
  assert.match(queryAction, /ownedBy/);
  assert.match(markAction, /ownedBy/);
  assert.match(markAllAction, /ownedBy/);
  assert.match(appProvider, /client\.notification\.viewAny/);
  assert.match(appProvider, /client\.notification\.update/);
  assert.match(featureTest, /assertUnauthorized|assertForbidden|assertNotFound|assertStatus\(422\)/);
  assert.match(routes, /v1\.client\.notifications/);
});

check('resource allowlist + structured action; no invented deep links (C3.8)', () => {
  assert.match(resource, /'id'/);
  assert.match(resource, /'type'/);
  assert.match(resource, /'title'/);
  assert.match(resource, /'message'/);
  assert.match(resource, /'is_read'/);
  assert.match(resource, /'created_at'/);
  assert.match(resource, /ClientNotificationAction::toClientPayload/);
  assert.equal(resource.includes("'user_id'"), false);
  assert.equal(resource.includes("'updated_at'"), false);
  assert.equal(screen.includes('Track Shipment'), false);
  assert.match(screen, /resolveClientNotificationDestination/);
  assert.match(api, /mapClientNotificationAction/);
  assert.match(types, /action/);
});

check('UI states + request safety + read hardening present', () => {
  assert.match(screen, /notifications-loading/);
  assert.match(screen, /notifications-list/);
  assert.match(screen, /notifications-empty/);
  assert.match(screen, /No notifications yet/);
  assert.match(screen, /empty inbox, not an unavailable/);
  assert.match(screen, /notifications-error/);
  assert.match(screen, /notifications-forbidden/);
  assert.match(screen, /statusCode === 401/);
  assert.match(screen, /errorStatus !== 403/);
  assert.match(screen, /inFlightRef/);
  assert.match(screen, /requestSeqRef/);
  assert.match(screen, /unreadSeqRef/);
  assert.match(screen, /markInFlightRef/);
  assert.match(screen, /markAllInFlightRef/);
  assert.match(screen, /PAGE_SIZE = 15/);
  assert.match(queryAction, /orderByDesc\('created_at'\)->orderByDesc\('id'\)/);
});

check('single production notifications route; Home header entry (C3.2/C3.3)', () => {
  assert.match(layout, /name="notifications"/);
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/app/notifications.tsx')));
  assert.equal(home.includes('unreadBadge'), false);
  assert.match(home, /home-notifications-header/);
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.equal(home.includes('home-notifications-unavailable'), false);
});

check('phase docs A–D2 exist; audit is audit-only', () => {
  for (const rel of [
    'docs/C2.8A_CLIENT_NOTIFICATIONS_CONTRACT_SECURITY_AUDIT.md',
    'docs/C2.8B_CLIENT_NOTIFICATIONS_CONTRACT.md',
    'docs/C2.8C_CLIENT_NOTIFICATIONS_BACKEND_IMPLEMENTATION.md',
    'docs/C2.8D1_CLIENT_NOTIFICATIONS_INBOX_UI.md',
    'docs/C2.8D2_CLIENT_NOTIFICATIONS_UX_HARDENING.md',
    'docs/C2.8D3_CLIENT_NOTIFICATIONS_FINAL_AUDIT.md',
  ]) {
    assert.ok(fs.existsSync(path.join(repoRoot, rel)), rel);
  }
  assert.match(fs.readFileSync(auditDoc, 'utf8'), /AUDIT ONLY/i);
});

if (process.exitCode) {
  console.error(`\nC2.8-D3 verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.8-D3 verification OK — ${passed} checks passed.`);
}
