/**
 * DRIVER-D8 — Operational awareness / notifications.
 * Run: npm run test:driver-d8
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e?.message || e);
  }
}

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

console.log('=== DRIVER-D8 Operational Awareness ===\n');

const notifApi = read('src/features/driverApi/notifications.ts');
const inbox = read('src/app/(tabs)/notifications.tsx');
const tabs = read('src/app/(tabs)/_layout.tsx');
const trip = read('src/app/active-trip/[id].tsx');
const awareness = read('src/features/notifications/TripAwarenessBanner.tsx');
const index = read('src/features/driverApi/index.ts');
const pkg = JSON.parse(read('package.json'));

check('Notifications API uses Driver-scoped contracts only', () => {
  assert.match(notifApi, /\/driver\/notifications/);
  assert.match(notifApi, /\/driver\/notifications\/unread-count/);
  assert.match(notifApi, /\/driver\/notifications\/\$\{id\}\/read/);
  assert.match(notifApi, /\/driver\/notifications\/read-all/);
  assert.doesNotMatch(notifApi, /\/client\/notifications/);
  assert.doesNotMatch(notifApi, /['"`]\/notifications/);
});

check('driverApi exports notifications module', () => {
  assert.match(index, /driverNotificationsApi/);
  assert.match(index, /notifications/);
});

check('Inbox screen: list, unread, mark read, empty/error/retry', () => {
  assert.match(inbox, /notifications-screen|notifications-empty/);
  assert.match(inbox, /listNotifications/);
  assert.match(inbox, /markNotificationRead|markAllNotificationsRead/);
  assert.match(inbox, /notifications-error|notifications-retry/);
  assert.match(inbox, /notificationHref/);
});

check('Alerts tab + unread badge wiring', () => {
  assert.match(tabs, /name="notifications"/);
  assert.match(tabs, /getUnreadNotificationCount/);
  assert.match(tabs, /tabBarBadge/);
});

check('Deep-link helper allowlists destinations', () => {
  assert.match(notifApi, /assignment/);
  assert.match(notifApi, /active_trip/);
  assert.match(notifApi, /conversation/);
  assert.match(notifApi, /notificationHref/);
});

check('Active Trip awareness + stale fail-closed', () => {
  assert.match(trip, /TripAwarenessBanner/);
  assert.match(trip, /active-trip-stale/);
  assert.match(trip, /stopTelemetry/);
  assert.match(awareness, /trip-awareness-critical/);
  assert.match(awareness, /assignment\.cancelled|assignment\.reassigned/);
  assert.match(awareness, /isVehicleMoving|MOTION_DEFER_HINT/);
});

check('No Client/Ops notification shortcuts', () => {
  const src = notifApi + inbox + awareness + trip;
  assert.doesNotMatch(src, /\/client\/notifications/);
  assert.doesNotMatch(src, /WriteClientNotification/);
  assert.doesNotMatch(src, /['"`]\/assignments(?:\/|['"`?])/);
});

check('Package scripts include D8 chain', () => {
  assert.ok(pkg.scripts['test:driver-d8']);
  assert.ok(pkg.scripts['test:d8-awareness']);
});

console.log('');
console.log(`DRIVER-D8: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D8 VERDICT: PASS');
