/**
 * C2.9-C3.3 Static Verification — Client Home Refresh
 * Validates the customer Home dashboard uses existing client APIs only.
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

const ENGINE_STAGES = [
  'EnRoutePickup',
  'ArrivedDelivery',
  'Unloading',
];

test('C3.3 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_3_CLIENT_HOME_REFRESH.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.3/i);
  assert.match(doc, /HARD STOP/i);
  assert.match(doc, /Backend production changes:\s*NONE/i);
  for (const s of [
    'Objective',
    'Audited existing architecture',
    'APIs reused',
    'Home information architecture',
    'Data ownership',
    'Shipment presentation',
    'Request summary',
    'Notification integration',
    'Loading',
    'Navigation',
    'Authorization boundary',
    'Files changed',
    'Tests',
    'Regression',
    'Known warnings',
    'Deferred work',
    'Blockers',
  ]) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), s);
  }
});

test('Home no longer contains stale Unavailable placeholders for implemented domains', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  assert.equal(home.includes('home-shipments-unavailable'), false);
  assert.equal(home.includes('home-attention-unavailable'), false);
  assert.equal(home.includes('home-notifications-unavailable'), false);
  assert.equal(home.includes('home-qa-tracking'), false);
  assert.match(home, /home-active-shipments|home-empty/);
  assert.match(home, /home-requests-summary|home-empty/);
});

test('active shipments are sourced from the real client shipment API', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  const api = readClient('src/features/shipments/shipmentsApi.ts');
  const overview = readClient('src/features/home/homeOverview.ts');
  assert.match(home, /listClientShipments/);
  assert.match(api, /\/client\/shipments/);
  assert.match(overview, /Waiting Assignment/);
  assert.match(overview, /Assigned/);
  assert.match(overview, /In Transit/);
  assert.match(home, /\/shipment-details\/\$\{id\}/);
  assert.equal(/updateClientShipment|PATCH.*status|updateStatus/.test(home), false);
});

test('Home request summary remains list-only; workflow mutations are not on Home', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  const api = readClient('src/features/requests/requestsApi.ts');
  const types = readClient('src/features/requests/types.ts');
  const overview = readClient('src/features/home/homeOverview.ts');
  assert.match(home, /listClientShipmentRequests/);
  assert.equal(/createClientShipmentRequest|submitClientShipmentRequest|resubmitClientShipmentRequest/.test(home), false);
  assert.match(api, /\/client\/shipment-requests/);
  assert.match(types, /requestNumber/);
  assert.match(overview, /draft/);
  assert.match(overview, /submitted/);
  assert.match(overview, /under_review/);
  assert.match(overview, /changes_requested/);
  assert.match(home, /home-requests-view-all/);
  assert.match(home, /\/\(tabs\)\/requests/);
});

test('notification unread indicator uses the existing unread-count endpoint', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  const api = readClient('src/features/notifications/notificationsApi.ts');
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.match(home, /home-notifications-header/);
  assert.match(home, /router\.push\('\/notifications'\)/);
  assert.match(api, /\/client\/notifications\/unread-count/);
  assert.match(home, /unreadCount != null && unreadCount > 0/);
  assert.equal(home.includes('unreadBadge'), false);
});

test('Home sections fail independently and have loading/empty/error states', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  assert.match(home, /Promise\.allSettled/);
  assert.match(home, /home-shipments-loading/);
  assert.match(home, /home-shipments-error/);
  assert.match(home, /home-shipments-retry/);
  assert.match(home, /home-requests-loading/);
  assert.match(home, /home-requests-error/);
  assert.match(home, /home-requests-retry/);
  assert.match(home, /home-empty/);
  assert.match(home, /No active shipments yet/);
});

test('no Dispatcher/Admin/Engine/Simulation APIs are imported on Home surfaces', () => {
  const scanned = [
    'src/app/(tabs)/index.tsx',
    'src/app/(tabs)/requests.tsx',
    'src/app/(tabs)/_layout.tsx',
    'src/features/home/homeOverview.ts',
    'src/features/requests/requestsApi.ts',
    'src/features/shipments/shipmentsApi.ts',
    'src/features/notifications/notificationsApi.ts',
  ]
    .map((f) => readClient(f) || '')
    .join('\n');
  assert.equal(/engine-enroll|SimulationRuntime|ShipmentLifecycleEngine|live-fleet/.test(scanned), false);
  assert.equal(/\/api\/v1\/shipments['"`]|\/assignments\//.test(scanned), false);
  assert.equal(/\/dispatcher\/|\/admin\//.test(scanned), false);
  for (const stage of ENGINE_STAGES) {
    assert.equal(scanned.includes(stage), false, stage);
  }
});

test('no fake shipment/request/notification records are introduced', () => {
  const home = readClient('src/app/(tabs)/index.tsx');
  const overview = readClient('src/features/home/homeOverview.ts');
  const api = readClient('src/features/requests/requestsApi.ts');
  const blob = home + overview + api;
  assert.equal(blob.includes('FX-4920'), false);
  assert.equal(blob.includes('FX-1620'), false);
  assert.equal(blob.includes('Robert Chen'), false);
  assert.equal(blob.includes('FleetX AI Recommendation'), false);
  assert.equal(blob.includes('LIVE ETA'), false);
  assert.equal(blob.includes('75%'), false);
});

test('Requests Center is the C3.4 workflow; Home summary still navigates to Requests', () => {
  const requests = readClient('src/app/(tabs)/requests.tsx');
  assert.match(requests, /listClientShipmentRequests/);
  assert.equal(/engine-enroll|live-fleet/.test(requests), false);
  const home = readClient('src/app/(tabs)/index.tsx');
  assert.match(home, /\/\(tabs\)\/requests/);
  assert.match(home, /request-details/);
});

test('C3.2 navigation remains intact; Tracking is not a primary tab', () => {
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.match(tabs, /tabBarLabel: 'Home'/);
  assert.match(tabs, /tabBarLabel: 'Requests'/);
  assert.match(tabs, /tabBarLabel: 'Shipments'/);
  assert.match(tabs, /tabBarLabel: 'Messages'/);
  assert.match(tabs, /tabBarLabel: 'Profile'/);
  assert.equal(/tabBarLabel: 'Tracking'/.test(tabs), false);
  assert.match(tabs, /href:\s*null/);
  const tracking = readClient('src/app/(tabs)/tracking.tsx');
  assert.match(tracking, /Redirect/);
  assert.match(tracking, /\/\(tabs\)\/shipments/);
});
