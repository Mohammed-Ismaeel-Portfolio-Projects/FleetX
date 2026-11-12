/**
 * C2.9-C3.2 Static Verification — Client Information Architecture
 * Validates navigation consolidation. Does not claim C3.3+ features exist.
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

test('C3.2 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_2_CLIENT_INFORMATION_ARCHITECTURE.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.2/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'Navigation decision',
    'Requests route',
    'Notifications integration',
    'Tracking navigation',
    'Shipment-centric',
    'Client / Dispatcher / Engine boundary',
    'Deferred work',
  ]) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), s);
  }
});

test('primary tabs are Home, Requests, Shipments, Messages, Profile', () => {
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.match(tabs, /tabBarLabel: 'Home'/);
  assert.match(tabs, /tabBarLabel: 'Requests'/);
  assert.match(tabs, /tabBarLabel: 'Shipments'/);
  assert.match(tabs, /tabBarLabel: 'Messages'/);
  assert.match(tabs, /tabBarLabel: 'Profile'/);
  assert.equal(/tabBarLabel: 'Tracking'/.test(tabs), false);
  assert.equal(/tabBarLabel: 'Notifications'/.test(tabs), false);
});

test('Requests is a first-class tab using the client request API (C3.4)', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/app/(tabs)/requests.tsx')));
  const requests = readClient('src/app/(tabs)/requests.tsx');
  assert.match(requests, /listClientShipmentRequests/);
  assert.match(requests, /requests-screen|requests-list/);
  assert.equal(/engine-enroll|live-fleet|\/assignments\//.test(requests), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/features/shipment-requests')), false);
});

test('Tracking is hidden from primary nav and redirects to Shipments', () => {
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.match(tabs, /name="tracking"/);
  assert.match(tabs, /href:\s*null/);
  const tracking = readClient('src/app/(tabs)/tracking.tsx');
  assert.match(tracking, /Redirect/);
  assert.match(tracking, /\/\(tabs\)\/shipments/);
  assert.equal(/getClientShipmentTracking|live-fleet|SafeMap/.test(tracking), false);
});

test('shipment-centric tracking path is preserved', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-tracking/[id].tsx')));
  const details = readClient('src/app/shipment-details/[id].tsx');
  assert.match(details, /\/shipment-tracking\//);
  const api = readClient('src/features/shipments/shipmentsApi.ts');
  assert.match(api, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
  assert.equal(/tracking-logs|live-fleet/.test(api), false);
});

test('Home header opens existing Notifications screen', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  assert.match(home, /home-notifications-header/);
  assert.match(home, /router\.push\('\/notifications'\)/);
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.ok(existsSync(resolve(CLIENT, 'src/app/notifications.tsx')));
  const notifications = readClient('src/app/notifications.tsx');
  assert.match(notifications, /listClientNotifications/);
});

test('existing primary destinations remain', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/app/(tabs)/shipments.tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/(tabs)/messages.tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/(tabs)/profile.tsx')));
  const shipments = readClient('src/app/(tabs)/shipments.tsx');
  assert.match(shipments, /listClientShipments/);
  const messages = readClient('src/app/(tabs)/messages.tsx');
  assert.match(messages, /listConversations/);
  const profile = readClient('src/app/(tabs)/profile.tsx');
  assert.match(profile, /logout|Logout/i);
});

test('auth and client API boundary unchanged', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/lib/auth/AuthContext.tsx')));
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.match(tabs, /isAuthenticated/);
  assert.match(tabs, /roleOk/);
  const scanned = [
    'src/app/(tabs)/_layout.tsx',
    'src/app/(tabs)/index.tsx',
    'src/app/(tabs)/requests.tsx',
    'src/app/(tabs)/tracking.tsx',
    'src/app/(tabs)/shipments.tsx',
    'src/app/notifications.tsx',
  ]
    .map((f) => readClient(f) || '')
    .join('\n');
  assert.equal(/engine-enroll|SimulationRuntime|ShipmentLifecycleEngine|live-fleet/.test(scanned), false);
  assert.equal(/\/api\/v1\/shipments['"`]|\/assignments\//.test(scanned), false);
});

test('C3.2 navigation remains; request workflow is C3.4', () => {
  const requests = readClient('src/app/(tabs)/requests.tsx');
  assert.match(requests, /listClientShipmentRequests/);
  const home = readClient('src/app/(tabs)/index.tsx');
  assert.match(home, /home-notifications-header/);
  assert.match(home, /getClientNotificationsUnreadCount/);
  const doc = read('docs/C2.9C3_2_CLIENT_INFORMATION_ARCHITECTURE.md');
  assert.match(doc, /C3\.3/);
  assert.match(doc, /C3\.4/);
});
