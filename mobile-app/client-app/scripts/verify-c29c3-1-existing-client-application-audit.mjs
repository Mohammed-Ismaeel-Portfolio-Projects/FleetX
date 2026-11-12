/**
 * C2.9-C3.1 Static Verification — Existing Client Application Audit
 * Validates audit artifact and key architectural findings.
 * Does NOT assert unimplemented UI features exist.
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

test('C3.1 audit document exists with required sections', () => {
  const doc = read('docs/C2.9C3_1_EXISTING_CLIENT_APPLICATION_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.1/i);
  assert.match(doc, /HARD STOP/i);
  const sections = [
    'Executive Summary',
    'Current Client App Architecture',
    'Existing Routes',
    'Existing Screens',
    'Existing Components',
    'Existing API Integrations',
    'Existing Client Workflows',
    'Backend Capabilities Available to Client',
    'Missing Capabilities',
    'UX / UI Audit',
    'Navigation Assessment',
    'Customer Experience Architecture',
    'Shipment Experience Architecture',
    'Tracking Experience Assessment',
    'Request → Shipment Handoff Assessment',
    'Security / Authorization Assessment',
    'Architectural Risks',
    'Recommended Final Client Architecture',
    'Screen-by-Screen Roadmap',
    'Progressive Implementation Phases',
    'Deferred / Out-of-Scope Features',
    'Blockers',
    'Final Recommendation',
  ];
  for (const s of sections) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), `missing: ${s}`);
  }
});

test('client app uses Expo Router with documented production screens', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/app/_layout.tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/(tabs)/shipments.tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-details/[id].tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-tracking/[id].tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/notifications.tsx')));
  const pkg = readClient('package.json');
  assert.match(pkg, /expo-router/);
});

test('production APIs wired: shipments, notifications, messaging, auth', () => {
  const shipments = readClient('src/features/shipments/shipmentsApi.ts');
  const notifications = readClient('src/features/notifications/notificationsApi.ts');
  const messaging = readClient('src/features/messaging/messagingApi.ts');
  const auth = readClient('src/lib/auth/authApi.ts');
  assert.match(shipments, /\/client\/shipments/);
  assert.match(notifications, /\/client\/notifications/);
  assert.match(messaging, /\/conversations/);
  assert.match(auth, /\/auth\/login/);
});

test('shipment requests backend exists but client app has no request module', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Http/Controllers/Client/ClientShipmentRequestController.php')));
  const routes = read('backend/routes/api.php');
  assert.match(routes, /ClientShipmentRequestController/);
  assert.equal(existsSync(resolve(CLIENT, 'src/features/shipment-requests')), false);
  const combined =
    (readClient('src/features/shipments/shipmentsApi.ts') || '') +
    (readClient('src/features/notifications/notificationsApi.ts') || '') +
    (readClient('src/features/messaging/messagingApi.ts') || '');
  assert.equal(/shipment-request|shipmentRequest|\/client\/shipment-requests/.test(combined), false);
});

test('audit documents historical home staleness and orphaned notifications', () => {
  const doc = read('docs/C2.9C3_1_EXISTING_CLIENT_APPLICATION_AUDIT.md');
  assert.match(doc, /stale|orphan/i);
  assert.match(doc, /notifications.*not linked|orphan/i);
  assert.match(doc, /Unavailable|home-shipments-unavailable|home-notifications-unavailable/);
});

test('per-shipment progress remains; tracking is not a primary tab (C3.2)', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-tracking/[id].tsx')));
  const trackingApi = readClient('src/features/shipments/shipmentsApi.ts');
  assert.match(trackingApi, /\/tracking/);
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.equal(/tabBarLabel:\s*'Tracking'/.test(tabs || ''), false);
  const trackingTab = readClient('src/app/(tabs)/tracking.tsx');
  assert.match(trackingTab || '', /Redirect|href: null|shipment-tracking/);
});

test('client tracking contract excludes GPS and ops tracking endpoints', () => {
  const resource = read('backend/app/Http/Resources/Client/ClientShipmentTrackingResource.php');
  assert.match(resource, /No GPS|milestones|derivedMilestones/s);
  const shipmentsApi = readClient('src/features/shipments/shipmentsApi.ts');
  assert.equal(/tracking-logs|live-fleet/.test(shipmentsApi), false);
  assert.match(shipmentsApi, /\/client\/shipments/);
});

test('audit enforces customer boundary — no Engine ops in client app', () => {
  const doc = read('docs/C2.9C3_1_EXISTING_CLIENT_APPLICATION_AUDIT.md');
  assert.match(doc, /must NOT see|Engine enrollment|operational stages/i);
  const appFiles = [
    'src/app/(tabs)/index.tsx',
    'src/app/(tabs)/shipments.tsx',
    'src/app/shipment-details/[id].tsx',
    'src/app/shipment-tracking/[id].tsx',
  ];
  const combined = appFiles.map((f) => readClient(f) || '').join('\n');
  assert.equal(/engine_enroll|SimulationRuntime|ShipmentLifecycleEngine/.test(combined), false);
});

test('product-source mocks removed from Client App tree (C3.12)', () => {
  const tsconfig = readClient('tsconfig.json') || '';
  assert.equal(tsconfig.includes('product-source'), false);
  const layout = readClient('src/app/_layout.tsx');
  assert.equal(/product-source/.test(layout || ''), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/product-source')), false);
});

test('implementation phases proposed without claiming they are built', () => {
  const doc = read('docs/C2.9C3_1_EXISTING_CLIENT_APPLICATION_AUDIT.md');
  assert.match(doc, /C3\.2|C3\.3|C3\.4/);
  assert.match(doc, /Do not implement|AUDIT|HARD STOP/i);
  assert.match(doc, /COMPLETE/);
});

test('architectural risks documented including status vocabulary', () => {
  const doc = read('docs/C2.9C3_1_EXISTING_CLIENT_APPLICATION_AUDIT.md');
  assert.match(doc, /status.*vocab|snake_case|canonical/i);
  assert.match(doc, /Architectural Risks/);
});
