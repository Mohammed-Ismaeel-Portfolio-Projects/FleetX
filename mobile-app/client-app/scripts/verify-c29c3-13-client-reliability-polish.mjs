/**
 * C2.9-C3.13 Static Verification — Client reliability & accessibility polish
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');
const CLIENT = resolve(ROOT, 'mobile-app/client-app');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

function readClient(rel) {
  const p = resolve(CLIENT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

const home = readClient('src/app/(tabs)/index.tsx') || '';
const messages = readClient('src/app/(tabs)/messages.tsx') || '';
const tabs = readClient('src/app/(tabs)/_layout.tsx') || '';
const login = readClient('src/app/login.tsx') || '';
const details = readClient('src/app/shipment-details/[id].tsx') || '';
const dest = readClient('src/features/notifications/notificationDestination.ts') || '';
const messagingApi = readClient('src/features/messaging/messagingApi.ts') || '';
const profile = readClient('src/app/(tabs)/profile.tsx') || '';
const workspace = readClient('src/features/shipments/shipmentWorkspace.ts') || '';
const shipments = readClient('src/app/(tabs)/shipments.tsx') || '';
const sessionGate = readClient('src/app/index.tsx') || '';
const pkg = readClient('package.json') || '';

test('C3.13 documentation exists', () => {
  const doc = read('docs/C2.9C3_13_CLIENT_RELIABILITY_POLISH.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.13/i);
  assert.match(doc, /HARD STOP|graduation freeze/i);
  for (const s of [
    'Objective',
    'Target',
    'Security',
    'Regression',
    'Deferred',
  ]) {
    assert.match(doc, new RegExp(s, 'i'), s);
  }
});

test('Home notification error exposes Retry recovery', () => {
  assert.match(home, /notesStatus === 'error'|notesStatus === \"error\"/);
  assert.match(home, /home-notifications-error/);
  assert.match(home, /home-notifications-retry/);
  assert.match(home, /loadRecentNotes/);
  assert.match(home, /listClientNotifications/);
  assert.match(home, /accessibilityLabel="Retry loading recent notifications"/);
  assert.equal(home.includes('openConversation'), false);
});

test('Home has request sequencing / stale-response protection', () => {
  assert.match(home, /requestSeqRef/);
  assert.match(home, /requestSeq !== requestSeqRef\.current/);
  assert.match(home, /\+\+requestSeqRef\.current/);
  assert.match(home, /Promise\.allSettled/);
});

test('Messages has request sequencing / stale-response protection', () => {
  assert.match(messages, /requestSeqRef/);
  assert.match(messages, /inFlightRef/);
  assert.match(messages, /requestSeq !== requestSeqRef\.current/);
  assert.match(messages, /listConversations/);
  assert.match(messages, /dispatcher_customer/);
  assert.equal(messages.includes('openConversation'), false);
});

test('role-mismatch Sign out has accessibility metadata', () => {
  assert.match(tabs, /role-mismatch-logout/);
  assert.match(tabs, /accessibilityRole="button"/);
  assert.match(tabs, /accessibilityLabel="Sign out"/);
});

test('Login boot Retry has hint and state parity with session gate', () => {
  assert.match(login, /login-boot-retry/);
  assert.match(login, /accessibilityLabel="Retry session restore"/);
  assert.match(login, /accessibilityHint="Attempts to restore your signed-in session"/);
  assert.match(login, /accessibilityState=\{\{ disabled: retrying, busy: retrying \}\}/);
  assert.match(sessionGate, /accessibilityHint="Attempts to restore your signed-in session"/);
});

test('openDispatcherThread removed; Support CTA intact', () => {
  assert.equal(details.includes('openDispatcherThread'), false);
  assert.match(details, /openSupportThread/);
  assert.match(details, /Contact FleetX Support/);
  assert.match(details, /shipment-details-contact-support/);
  assert.match(details, /dispatcher_customer/);
});

test('openConversation remains absent', () => {
  assert.equal(messagingApi.includes('openConversation'), false);
  assert.equal(messages.includes('openConversation'), false);
  assert.equal(details.includes('openConversation'), false);
  assert.equal(home.includes('openConversation'), false);
});

test('notification resolver safety intact', () => {
  assert.match(dest, /resolveClientNotificationDestination/);
  assert.match(dest, /ALLOWED_KINDS/);
  assert.match(dest, /shipment_details/);
  assert.match(dest, /shipment_progress/);
  assert.match(dest, /request_details/);
  assert.match(dest, /conversation/);
  assert.match(dest, /UUID_RE|isUuid/);
  assert.match(dest, /void notification\?\.title/);
  assert.match(dest, /void notification\?\.message/);
});

test('Client API boundary intact', () => {
  const blob = [home, messages, tabs, login, details].join('\n');
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.equal(blob.includes('ShipmentLifecycleEngine'), false);
});

test('C3.9–C3.12 invariants preserved', () => {
  assert.match(profile, /AppConfig\.appVersion/);
  assert.equal(profile.includes('AI Insights'), false);
  assert.equal(profile.includes('Biometric'), false);
  assert.match(workspace, /customerFacingShipmentStatus/);
  assert.match(shipments, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.equal(login.includes('Client@123'), false);
  assert.equal(login.includes('Remember Me'), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/product-source')), false);
  assert.match(tabs, /name="index"/);
  assert.match(tabs, /name="requests"/);
  assert.match(tabs, /name="shipments"/);
  assert.match(tabs, /name="messages"/);
  assert.match(tabs, /name="profile"/);
});

test('package script registered', () => {
  assert.match(pkg, /test:c29c3-13-client-reliability-polish/);
});
