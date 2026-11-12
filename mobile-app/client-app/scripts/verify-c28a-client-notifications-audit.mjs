/**
 * C2.8-A — Client Notifications contract & security audit (static verification).
 * AUDIT ONLY — does not implement notifications.
 * C2.9-C3.9: Profile no longer hosts the decorative Notifications→On preference.
 * Profile may open /notifications; must not become a second inbox.
 * Run: node ./scripts/verify-c28a-client-notifications-audit.mjs
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

const docPath = path.join(repoRoot, 'docs/C2.8A_CLIENT_NOTIFICATIONS_CONTRACT_SECURITY_AUDIT.md');
const apiPhp = readRepo('backend/routes/api.php');
const notificationModel = readRepo('backend/app/Models/Notification.php');
const migration = readRepo(
  'backend/database/migrations/0001_01_01_000220_create_notifications_table.php'
);
const appProvider = readRepo('backend/app/Providers/AppServiceProvider.php');
const liveNotifications = readClient('src/app/notifications.tsx');
const home = readClient('src/app/(tabs)/index.tsx');
const layout = readClient('src/app/_layout.tsx');
const profile = readClient('src/app/(tabs)/profile.tsx');

check('audit document exists with required sections', () => {
  assert.ok(fs.existsSync(docPath), 'missing C2.8A audit doc');
  const body = fs.readFileSync(docPath, 'utf8');
  for (const section of [
    'Executive Result',
    'Current Client App State',
    'Existing Backend Notification Endpoints',
    'Notification Data Model',
    'Authorization & Tenant Isolation',
    'Privacy / Data Exposure',
    'Notification Types',
    'Read / Unread Capabilities',
    'Realtime / Polling',
    'Deep-Link / Navigation Audit',
    'Security Findings',
    'Minimum Future Client Contract',
    'Client App Decision',
    'Required Next Phase',
  ]) {
    assert.match(body, new RegExp(section.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), section);
  }
  assert.match(body, /PASS WITH FINDINGS/);
  assert.match(body, /UNAVAILABLE/);
  assert.match(body, /C2\.8-B/);
});

check('production notifications route uses client inbox API (C2.8-D1; audit historical Unavailable superseded)', () => {
  assert.match(liveNotifications, /listClientNotifications|notificationsApi/);
  assert.match(liveNotifications, /notifications-empty|No notifications yet/);
  assert.equal(liveNotifications.includes('/issues'), false);
  assert.equal(liveNotifications.includes('AI DAILY SUMMARY'), false);
  assert.equal(liveNotifications.includes('unreadBadge'), false);
  assert.match(layout, /name="notifications"/);
});

check('Home notifications use unread-count (C3.2/C3.3); no fake global badge', () => {
  assert.equal(home.includes('unreadBadge'), false);
  assert.match(home, /getClientNotificationsUnreadCount|home-notifications-header/);
  assert.equal(home.includes('home-notifications-unavailable'), false);
  assert.match(home, /openMessages/);
});

check('client notifications feature module exists for inbox (C2.8-D1)', () => {
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/features/notifications')));
  const api = readClient('src/features/notifications/notificationsApi.ts');
  assert.match(api, /\/client\/notifications/);
  assert.equal(api.includes('/issues'), false);
});

check('backend: notifications table/model exist; client inbox routes are dedicated (C2.8-C)', () => {
  assert.match(migration, /Schema::create\('notifications'/);
  assert.match(migration, /user_id/);
  assert.match(migration, /is_read/);
  assert.match(notificationModel, /class Notification/);
  assert.match(notificationModel, /user_id/);
  // C2.8-A recorded MISSING routes; C2.8-C implemented client-scoped inbox only.
  assert.match(apiPhp, /ClientNotificationController/);
  assert.match(apiPhp, /\/notifications\/unread-count/);
  assert.match(apiPhp, /v1\.client\.notifications/);
  // Must not wire Client App to Issues as a substitute
  assert.equal(liveNotifications.includes('IssueController'), false);
});

check('Issues remain ops-gated (not a client notification substitute)', () => {
  assert.match(apiPhp, /IssueController/);
  assert.match(appProvider, /issue\.view/);
  assert.match(
    appProvider,
    /Gate::define\('issue\.view'[\s\S]*?admin',\s*'dispatcher'\]/
  );
  assert.equal(liveNotifications.includes('/issues'), false);
  assert.equal(home.includes('/issues'), false);
});

check('profile is not a notification inbox; decorative Notifications→On removed (C3.9); product-source removed (C3.12)', () => {
  const body = fs.readFileSync(docPath, 'utf8');
  // Historical C2.8-A finding: Profile once showed a fake Notifications preference "On".
  assert.match(body, /Notifications → On|Notifications.*On/i);
  assert.match(body, /product-source/);
  // C3.12: mock product-source tree deleted from Client App.
  assert.equal(fs.existsSync(path.join(clientRoot, 'src/product-source')), false);
  // C3.9: Profile may navigate to the existing inbox; must not host inbox/unread state or fake On.
  assert.match(profile, /profile-open-notifications|router\.push\('\/notifications'\)/);
  assert.equal(profile.includes('>On</'), false);
  assert.equal(profile.includes('unreadBadge'), false);
  assert.equal(profile.includes('unread_count'), false);
  assert.equal(profile.includes('listClientNotifications'), false);
  assert.equal(profile.includes('getClientNotificationsUnreadCount'), false);
});

check('phase is audit-only deliverables', () => {
  assert.ok(
    fs.existsSync(path.join(clientRoot, 'scripts/verify-c28a-client-notifications-audit.mjs'))
  );
  assert.match(fs.readFileSync(docPath, 'utf8'), /AUDIT \/ VERIFICATION ONLY|AUDIT ONLY/i);
  assert.match(fs.readFileSync(docPath, 'utf8'), /Do not implement/i);
});

if (process.exitCode) {
  console.error(`\nC2.8-A verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.8-A verification OK — ${passed} checks passed.`);
}
