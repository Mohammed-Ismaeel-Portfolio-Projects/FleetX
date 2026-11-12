/**
 * C2.9-C3.12 Static Verification — Client Production Hardening
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
const overview = readClient('src/features/home/homeOverview.ts') || '';
const workspace = readClient('src/features/shipments/shipmentWorkspace.ts') || '';
const shipmentsApi = readClient('src/features/shipments/shipmentsApi.ts') || '';
const details = readClient('src/app/shipment-details/[id].tsx') || '';
const requestForm = readClient('src/app/request-form.tsx') || '';
const register = readClient('src/app/register.tsx') || '';
const login = readClient('src/app/login.tsx') || '';
const sessionGate = readClient('src/app/index.tsx') || '';
const inputField = readClient('src/components/input-field.tsx') || '';
const checkbox = readClient('src/components/checkbox.tsx') || '';
const customButton = readClient('src/components/custom-button.tsx') || '';
const productBarrel = readClient('src/components/product/index.ts') || '';
const messagingApi = readClient('src/features/messaging/messagingApi.ts') || '';
const messages = readClient('src/app/(tabs)/messages.tsx') || '';
const conversation = readClient('src/app/conversation/[id].tsx') || '';
const handoff = readClient('src/app/shipment/[id].tsx') || '';
const unavailable = readClient('src/components/unavailable-domain.tsx') || '';
const profile = readClient('src/app/(tabs)/profile.tsx') || '';
const dest = readClient('src/features/notifications/notificationDestination.ts') || '';
const shipments = readClient('src/app/(tabs)/shipments.tsx') || '';
const tabs = readClient('src/app/(tabs)/_layout.tsx') || '';
const pkg = readClient('package.json') || '';
const tsconfig = readClient('tsconfig.json') || '';

test('C3.12 documentation exists', () => {
  const doc = read('docs/C2.9C3_12_CLIENT_PRODUCTION_HARDENING.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.12/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'Objective',
    'Dead-code',
    'Status',
    'Request-form',
    'Auth',
    'Messaging',
    'Security',
    'Tests',
    'Regression',
    'Deferred',
  ]) {
    assert.match(doc, new RegExp(s, 'i'), s);
  }
});

test('product-source tree removed; no production imports', () => {
  assert.equal(existsSync(resolve(CLIENT, 'src/product-source')), false);
  assert.equal(tsconfig.includes('product-source'), false);
  const appBlob = [home, details, login, register, messages, conversation, profile].join('\n');
  assert.equal(appBlob.includes('product-source'), false);
});

test('dead template artifacts absent from production tree', () => {
  assert.equal(existsSync(resolve(CLIENT, 'src/components/safe-map.tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/components/web-badge.tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/components/hint-row.tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/components/external-link.tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/components/ui/collapsible.tsx')), false);
  assert.equal(customButton.includes('GoogleLogo'), false);
  assert.equal(productBarrel.includes('GoogleLogo'), false);
  assert.equal(shipmentsApi.includes('function formatStatusLabel'), false);
});

test('openConversation create helper removed; screens remain lookup-only', () => {
  assert.equal(messagingApi.includes('openConversation'), false);
  assert.equal(messagingApi.includes('Failed to open conversation'), false);
  assert.match(messagingApi, /listConversations/);
  assert.match(messagingApi, /getConversation/);
  assert.equal(messages.includes('openConversation'), false);
  assert.equal(conversation.includes('openConversation'), false);
  assert.equal(handoff.includes('openConversation'), false);
  assert.equal(details.includes('openConversation'), false);
  assert.match(details, /findAuthorizedConversationForShipment/);
});

test('Home active statuses align with Client lifecycle catalog', () => {
  assert.match(overview, /SHIPMENT_STATUS/);
  assert.match(overview, /WAITING_ASSIGNMENT/);
  assert.match(overview, /IN_TRANSIT/);
  assert.equal(overview.includes("'pending'"), false);
  assert.equal(overview.includes("'in_transit'"), false);
  assert.equal(overview.includes("'at_customs'"), false);
  assert.match(overview, /snake_case|Dual backend|intentionally NOT included/i);
});

test('Workspace uses customerFacingShipmentStatus', () => {
  assert.match(details, /customerFacingShipmentStatus/);
  assert.equal(details.includes('formatCustomerShipmentStatus'), false);
  assert.match(workspace, /customerFacingShipmentStatus/);
  assert.match(workspace, /isEngineStageName/);
});

test('request form has Retry path', () => {
  assert.match(requestForm, /request-form-retry/);
  assert.match(requestForm, /loadExisting/);
  assert.match(requestForm, /accessibilityLabel="Retry loading this request"/);
  assert.match(requestForm, /loadInFlight/);
});

test('register honesty: no UI-only phone persistence', () => {
  assert.equal(register.includes('register-phone'), false);
  assert.equal(register.includes('phoneNumber'), false);
  assert.equal(register.includes('not sent to server'), false);
  assert.equal(register.includes('+1 (555)'), false);
  assert.match(register, /registerAccount/);
  assert.match(register, /accessibilityLabel="Create FleetX account"/);
  assert.match(register, /accessibilityLabel="Full name"/);
});

test('auth accessibility metadata present', () => {
  assert.match(inputField, /Hide password|Show password/);
  assert.match(inputField, /accessibilityRole="button"/);
  assert.match(checkbox, /accessibilityRole="checkbox"/);
  assert.match(login, /accessibilityLabel="Email address"/);
  assert.match(login, /accessibilityLabel="Password"/);
  assert.match(login, /accessibilityLabel="Sign in to FleetX"/);
  assert.match(sessionGate, /accessibilityLabel="Retry session restore"/);
  assert.equal(login.includes('Remember Me'), false);
  assert.equal(login.includes('Client@123'), false);
  assert.equal(login.includes('client@fleetx.com'), false);
});

test('Support terminology; residual dispatcher testID removed', () => {
  assert.equal(unavailable.includes('Message dispatcher'), false);
  assert.match(unavailable, /FleetX Support/);
  assert.equal(details.includes('shipment-details-message-dispatcher'), false);
  assert.match(details, /shipment-details-contact-support/);
  assert.match(details, /Contact FleetX Support/);
});

test('Client API boundary intact', () => {
  const blob = [home, details, messages, conversation, handoff, requestForm, register, login].join(
    '\n'
  );
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.equal(blob.includes('ShipmentLifecycleEngine'), false);
});

test('C3.8–C3.11 invariants preserved', () => {
  assert.match(dest, /resolveClientNotificationDestination/);
  assert.match(dest, /ALLOWED_KINDS/);
  assert.match(dest, /UUID_RE|isUuid/);
  assert.match(profile, /AppConfig\.appVersion/);
  assert.equal(profile.includes('AI Insights'), false);
  assert.equal(profile.includes('Biometric'), false);
  assert.equal(home.includes('home-qa-shipments'), false);
  assert.match(shipments, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(tabs, /name="index"/);
  assert.match(tabs, /name="requests"/);
  assert.match(tabs, /name="shipments"/);
  assert.match(tabs, /name="messages"/);
  assert.match(tabs, /name="profile"/);
});

test('package script registered', () => {
  assert.match(pkg, /test:c29c3-12-client-production-hardening/);
});
