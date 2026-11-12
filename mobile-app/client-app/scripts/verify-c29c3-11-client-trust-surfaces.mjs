/**
 * C2.9-C3.11 Static Verification — Client Trust Surfaces
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

const workspace = readClient('src/features/shipments/shipmentWorkspace.ts') || '';
const shipments = readClient('src/app/(tabs)/shipments.tsx') || '';
const requestDetails = readClient('src/app/request-details/[id].tsx') || '';
const unavailable = readClient('src/components/unavailable-domain.tsx') || '';
const conversation = readClient('src/app/conversation/[id].tsx') || '';
const messages = readClient('src/app/(tabs)/messages.tsx') || '';
const login = readClient('src/app/login.tsx') || '';
const customButton = readClient('src/components/custom-button.tsx') || '';
const details = readClient('src/app/shipment-details/[id].tsx') || '';
const handoff = readClient('src/app/shipment/[id].tsx') || '';
const home = readClient('src/app/(tabs)/index.tsx') || '';
const tracking = readClient('src/app/shipment-tracking/[id].tsx') || '';
const profile = readClient('src/app/(tabs)/profile.tsx') || '';
const dest = readClient('src/features/notifications/notificationDestination.ts') || '';
const tabs = readClient('src/app/(tabs)/_layout.tsx') || '';
const pkg = readClient('package.json') || '';

const LIFECYCLE = [
  'Waiting Assignment',
  'Assigned',
  'Ready For Pickup',
  'Picked Up',
  'In Transit',
  'At Customs',
  'Out For Delivery',
  'Delivered',
  'Completed',
  'Cancelled',
];

const SNAKE_LEGACY = ['pending', 'in_transit', 'at_customs', 'assigned', 'delivered', 'cancelled'];

test('C3.11 documentation exists', () => {
  const doc = read('docs/C2.9C3_11_CLIENT_TRUST_SURFACES.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.11/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'Objective',
    'Status vocabulary',
    'Support',
    'Authentication',
    'Accessibility',
    'Security',
    'Tests',
    'Regression',
    'Deferred',
  ]) {
    assert.match(doc, new RegExp(s, 'i'), s);
  }
});

test('request details uses customer shipment status projector', () => {
  assert.match(requestDetails, /customerFacingShipmentStatus/);
  assert.match(requestDetails, /linkedShipment/);
  assert.equal(requestDetails.includes('formatStatusLabel(request.linkedShipment'), false);
  assert.equal(requestDetails.includes("from '@/features/shipments/shipmentsApi'"), false);
});

test('shipment filters use Client lifecycle catalog — not snake_case-only', () => {
  assert.match(workspace, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(shipments, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(shipments, /customerFacingShipmentStatus/);
  for (const name of LIFECYCLE) {
    assert.match(workspace, new RegExp(name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const snake of SNAKE_LEGACY) {
    assert.equal(
      shipments.includes(`'${snake}'`),
      false,
      `legacy snake_case filter literal ${snake}`
    );
  }
  assert.equal(shipments.includes("'pending'"), false);
  assert.equal(shipments.includes("'in_transit'"), false);
});

test('no customer-visible Message dispatcher', () => {
  assert.equal(unavailable.includes('Message dispatcher'), false);
  assert.match(unavailable, /FleetX Support/);
  assert.match(unavailable, /accessibilityLabel="Open FleetX Support messages"/);
  const srcBlob = [unavailable, conversation, messages, details, handoff, login].join('\n');
  assert.equal(srcBlob.includes('Message dispatcher'), false);
  assert.equal(srcBlob.includes('Contact dispatcher'), false);
});

test('conversation has no Assignment ID; FleetX Support; Retry; GradientBackground', () => {
  assert.equal(conversation.includes('Assignment:'), false);
  assert.equal(conversation.includes('assignmentId'), false);
  assert.equal(conversation.includes('Assignment ID'), false);
  assert.match(conversation, /FleetX Support/);
  assert.match(conversation, /GradientBackground/);
  assert.match(conversation, /conversation-retry|Retry/);
  assert.match(conversation, /accessibilityLabel/);
  assert.match(conversation, /findAuthorizedConversationForShipment|getConversation/);
  assert.equal(conversation.includes('apiClient.post'), false);
  assert.equal(conversation.includes("'/conversations'"), false);
});

test('messages cohesion: FleetX Support + Retry a11y', () => {
  assert.match(messages, /FleetX Support/);
  assert.match(messages, /messages-retry/);
  assert.match(messages, /accessibilityLabel="Retry loading FleetX Support messages"/);
  assert.match(messages, /listConversations/);
  assert.equal(messages.includes('apiClient.post'), false);
});

test('login honesty: no credential literals; Remember Me removed', () => {
  assert.equal(login.includes('Client@123'), false);
  assert.equal(login.includes('client@fleetx.com'), false);
  assert.equal(login.includes('Remember Me'), false);
  assert.equal(login.includes('rememberMe'), false);
  assert.equal(login.includes('Checkbox'), false);
  assert.match(login, /const \[email, setEmail\] = useState\(''\)/);
  assert.match(login, /const \[password, setPassword\] = useState\(''\)/);
  assert.match(login, /accessibilityLabel="Sign in to FleetX"/);
});

test('CustomButton supports accessibility on login CTA', () => {
  assert.match(customButton, /accessibilityRole="button"/);
  assert.match(customButton, /accessibilityLabel/);
});

test('FleetX Support terminology on Support surfaces', () => {
  assert.match(messages, /FleetX Support/);
  assert.match(conversation, /FleetX Support/);
  assert.match(details, /Contact FleetX Support/);
  assert.match(unavailable, /FleetX Support/);
});

test('Client API / Engine / Dispatcher boundary intact', () => {
  const blob = [
    home,
    shipments,
    tracking,
    details,
    messages,
    conversation,
    handoff,
    profile,
    requestDetails,
    unavailable,
    login,
  ].join('\n');
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.equal(blob.includes('ShipmentLifecycleEngine'), false);
});

test('C3.6–C3.10 architecture preserved', () => {
  assert.match(tabs, /name="index"/);
  assert.match(tabs, /name="requests"/);
  assert.match(tabs, /name="shipments"/);
  assert.match(tabs, /name="messages"/);
  assert.match(tabs, /name="profile"/);
  assert.match(workspace, /customerFacingShipmentStatus/);
  assert.match(workspace, /buildCustomerJourney|CUSTOMER_LIFECYCLE_STEPS/);
  assert.match(details, /findAuthorizedConversationForShipment/);
  assert.match(dest, /resolveClientNotificationDestination/);
  assert.match(profile, /AppConfig\.appVersion/);
  assert.equal(profile.includes('+1 (555)'), false);
  assert.equal(profile.includes('AI Insights'), false);
  assert.equal(home.includes('home-qa-shipments'), false);
  assert.equal(login.includes('Forgot Password? (Unavailable)'), false);
});

test('filter chip accessibility present', () => {
  assert.match(shipments, /accessibilityLabel=\{`Filter shipments by status \$\{chipLabel\}`\}/);
  assert.match(shipments, /accessibilityRole="button"/);
  assert.match(shipments, /accessibilityLabel="All statuses"/);
});

test('no GPS / ETA / POD expansion', () => {
  const blob = [shipments, conversation, messages, login, requestDetails].join('\n');
  assert.equal(blob.includes('SafeMap'), false);
  assert.equal(blob.includes('LIVE ETA'), false);
  assert.equal(blob.includes('Proof of Delivery'), false);
});

test('package script registered', () => {
  assert.match(pkg, /test:c29c3-11-client-trust-surfaces/);
});
