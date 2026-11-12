/**
 * C2.9-C3.10 Static Verification — Client integration polish & production QA
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
const shipments = readClient('src/app/(tabs)/shipments.tsx') || '';
const tracking = readClient('src/app/shipment-tracking/[id].tsx') || '';
const details = readClient('src/app/shipment-details/[id].tsx') || '';
const messages = readClient('src/app/(tabs)/messages.tsx') || '';
const conversation = readClient('src/app/conversation/[id].tsx') || '';
const handoff = readClient('src/app/shipment/[id].tsx') || '';
const profile = readClient('src/app/(tabs)/profile.tsx') || '';
const login = readClient('src/app/login.tsx') || '';
const register = readClient('src/app/register.tsx') || '';
const tabs = readClient('src/app/(tabs)/_layout.tsx') || '';
const layout = readClient('src/app/_layout.tsx') || '';
const workspace = readClient('src/features/shipments/shipmentWorkspace.ts') || '';
const dest = readClient('src/features/notifications/notificationDestination.ts') || '';
const handoffApi = readClient('src/features/messaging/shipmentHandoff.ts') || '';
const pkg = readClient('package.json') || '';

const ENGINE = [
  'EnRoutePickup',
  'ArrivedPickup',
  'ApproachingRestStop',
  'ArrivedDelivery',
  'Unloading',
];

test('C3.10 documentation exists', () => {
  const doc = read('docs/C2.9C3_10_CLIENT_INTEGRATION_POLISH.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.10/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'Audit findings',
    'Customer status',
    'Messaging',
    'Assignment',
    'Accessibility',
    'Home',
    'Security',
    'Tests',
    'Regression',
    'Deferred',
  ]) {
    assert.match(doc, new RegExp(s, 'i'), s);
  }
});

test('navigation IA preserved', () => {
  assert.match(tabs, /name="index"/);
  assert.match(tabs, /name="requests"/);
  assert.match(tabs, /name="shipments"/);
  assert.match(tabs, /name="messages"/);
  assert.match(tabs, /name="profile"/);
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(layout, /shipment-tracking\/\[id\]/);
  assert.match(layout, /request-details\/\[id\]/);
  assert.match(layout, /conversation\/\[id\]/);
  assert.match(layout, /name="notifications"/);
});

test('customer-safe shipment status on Home, list, and tracking', () => {
  assert.match(workspace, /customerFacingShipmentStatus/);
  assert.match(workspace, /isEngineStageName/);
  assert.match(home, /customerFacingShipmentStatus/);
  assert.match(shipments, /customerFacingShipmentStatus/);
  assert.match(tracking, /customerFacingShipmentStatus/);
  assert.equal(shipments.includes('shipment-status-raw-'), false);
  assert.equal(tracking.includes('shipment-progress-status-raw'), false);
  assert.equal(home.includes('formatStatusLabel(statusRaw)'), false);
  for (const stage of ENGINE) {
    assert.equal(shipments.includes(`>${stage}<`), false, stage);
    assert.equal(tracking.includes(`>${stage}<`), false, stage);
    assert.equal(home.includes(`>${stage}<`), false, stage);
  }
});

test('Home request pills use request status formatter', () => {
  assert.match(home, /formatRequestStatusLabel/);
  assert.match(home, /customerFacingShipmentStatus/);
});

test('messaging is customer-facing; no Assignment IDs; no create', () => {
  assert.equal(/dispatcher_customer/.test(messages.replace(/type:\s*'dispatcher_customer'/g, '')), false);
  assert.equal(messages.includes('dispatcher_customer thread'), false);
  assert.match(messages, /FleetX Support/);
  assert.match(messages, /accessibilityLabel/);
  assert.match(messages, /accessibilityRole="button"/);
  assert.equal(conversation.includes('Assignment:'), false);
  assert.equal(conversation.includes('assignmentId'), false);
  assert.match(conversation, /FleetX Support/);
  assert.match(handoff, /findAuthorizedConversationForShipment/);
  assert.match(handoff, /Contact FleetX Support/);
  assert.match(handoff, /GradientBackground/);
  assert.equal(handoff.includes('apiClient.post'), false);
  assert.equal(messages.includes('apiClient.post'), false);
  assert.equal(handoffApi.includes('apiClient.post'), false);
  assert.match(details, /Contact FleetX Support/);
});

test('Client API / Engine / Dispatcher boundary', () => {
  const blob = [home, shipments, tracking, details, messages, conversation, handoff, profile].join(
    '\n'
  );
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.equal(blob.includes('ShipmentLifecycleEngine'), false);
});

test('C3.5–C3.9 architecture preserved', () => {
  assert.match(details, /CustomerJourney|buildCustomerJourney/);
  assert.match(details, /findAuthorizedConversationForShipment/);
  assert.match(dest, /resolveClientNotificationDestination/);
  assert.match(profile, /AppConfig\.appVersion/);
  assert.match(profile, /profile-logout|await logout\(\)/);
  assert.equal(profile.includes('+1 (555)'), false);
  assert.equal(profile.includes('AI Insights'), false);
  assert.equal(profile.includes('Biometric'), false);
});

test('Home debug QA strip and auth fake capabilities removed', () => {
  assert.equal(home.includes('home-qa-shipments'), false);
  assert.equal(home.includes('home-qa-messages'), false);
  assert.equal(home.includes('home-qa-requests'), false);
  assert.equal(login.includes('Google (Unavailable)'), false);
  assert.equal(login.includes('Forgot Password? (Unavailable)'), false);
  assert.equal(register.includes('Google (Unavailable)'), false);
});

test('dead Expo AppTabs starter removed', () => {
  assert.equal(existsSync(resolve(CLIENT, 'src/components/app-tabs.tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/components/app-tabs.web.tsx')), false);
});

test('no GPS / ETA / Engine UI / POD expansion', () => {
  const blob = [home, shipments, tracking, details, messages].join('\n');
  assert.equal(blob.includes('SafeMap'), false);
  assert.equal(blob.includes('LIVE ETA'), false);
  assert.equal(/\bETA:\b/.test(blob), false);
  assert.equal(blob.includes('live GPS'), false);
  assert.equal(blob.includes('Proof of Delivery'), false);
});

test('package script registered', () => {
  assert.match(pkg, /test:c29c3-10-client-integration-polish/);
});
