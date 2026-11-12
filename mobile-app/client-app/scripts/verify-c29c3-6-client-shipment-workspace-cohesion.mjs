/**
 * C2.9-C3.6 Static Verification — Client Shipment Workspace Cohesion
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

const details = readClient('src/app/shipment-details/[id].tsx') || '';
const workspace = readClient('src/features/shipments/shipmentWorkspace.ts') || '';
const api = readClient('src/features/shipments/shipmentsApi.ts') || '';
const types = readClient('src/features/shipments/types.ts') || '';
const requestDetails = readClient('src/app/request-details/[id].tsx') || '';
const handoff = readClient('src/app/shipment/[id].tsx') || '';
const tracking = readClient('src/app/shipment-tracking/[id].tsx') || '';
const list = readClient('src/app/(tabs)/shipments.tsx') || '';
const home = readClient('src/app/(tabs)/index.tsx') || '';
const layout = readClient('src/app/_layout.tsx') || '';
const tabs = readClient('src/app/(tabs)/_layout.tsx') || '';
const showResource = read('backend/app/Http/Resources/Client/ClientShipmentShowResource.php') || '';

const ENGINE = [
  'EnRoutePickup',
  'ArrivedPickup',
  'ApproachingRestStop',
  'ArrivedDelivery',
  'ShipmentLifecycleEngine',
  'SimulationRuntime',
];

test('C3.6 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_6_CLIENT_SHIPMENT_WORKSPACE_COHESION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.6/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'audited architecture',
    'Shipment Workspace',
    'information hierarchy',
    'customer-facing status',
    'route',
    'progress',
    'messaging',
    'Request → Shipment',
    'Client API',
    'live tracking',
    'loading',
    'files changed',
    'tests',
    'regression',
    'verifier',
    'known warnings',
    'deferred work',
    'blockers',
  ]) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), s);
  }
});

test('existing Shipment Workspace remains the canonical route', () => {
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(details, /getClientShipment/);
  assert.match(details, /shipment-details-screen/);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/customer-shipment/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/shipment-workspace/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/client-shipment/[id].tsx')), false);
  assert.match(list, /\/shipment-details\/\$\{item\.id\}/);
  assert.match(home, /\/shipment-details\/\$\{id\}/);
  assert.match(requestDetails, /\/shipment-details\/\$\{id\}/);
});

test('shipment header exposes customer-safe status', () => {
  assert.match(details, /shipment-details-header/);
  assert.match(details, /shipment-details-number/);
  assert.match(details, /customerFacingShipmentStatus/);
  assert.match(details, /shipmentStatusGuidance/);
  assert.match(workspace, /Waiting for Assignment/);
  assert.match(workspace, /Your shipment is currently on its way/);
  assert.match(workspace, /Your shipment has been completed/);
});

test('customer-facing lifecycle terminology is used', () => {
  assert.match(workspace, /CUSTOMER_LIFECYCLE_STEPS/);
  assert.match(details, /shipment-details-lifecycle/);
  assert.match(workspace, /'Assigned'/);
  assert.match(workspace, /'In Transit'/);
  assert.match(workspace, /'Delivered'/);
  assert.match(workspace, /'Completed'/);
  for (const stage of ENGINE) {
    assert.equal(details.includes(stage), false, stage);
  }
});

test('route / pickup / delivery information is present when available', () => {
  assert.match(details, /shipment-details-route/);
  assert.match(details, /Pickup/);
  assert.match(details, /Delivery/);
  assert.match(details, /formatPlaceSummary/);
  assert.match(details, /plannedPickupDate/);
  assert.match(details, /plannedDeliveryDate/);
  assert.match(details, /Pickup date not provided|Planned pickup not provided/);
  assert.equal(details.includes('LIVE ETA'), false);
  assert.equal(details.includes('etaEngine'), false);
});

test('existing progress functionality remains accessible', () => {
  assert.match(details, /shipment-details-progress/);
  assert.match(details, /Shipment progress/);
  assert.match(details, /\/shipment-tracking\/\$\{shipmentId\}/);
  assert.match(tracking, /getClientShipmentTracking/);
  assert.match(layout, /shipment-tracking\/\[id\]/);
});

test('Request → Shipment continuity from C3.5 remains intact', () => {
  assert.match(requestDetails, /request-details-open-shipment/);
  assert.match(requestDetails, /\/shipment-details\/\$\{id\}/);
  assert.match(requestDetails, /linkedShipmentId/);
  assert.equal(requestDetails.includes('/dispatcher/'), false);
});

test('existing messaging integration remains intact', () => {
  assert.match(details, /findAuthorizedConversationForShipment/);
  assert.match(details, /Contact FleetX Support/);
  assert.match(details, /\/conversation\/\$\{conversationId\}/);
  assert.match(details, /FleetX Support must open the conversation first/);
  assert.match(handoff, /findAuthorizedConversationForShipment/);
  assert.equal(details.includes("apiClient.post"), false);
  assert.equal(/openConversation\(/.test(details), false);
});

test('no Engine / Dispatcher / Assignment APIs are used', () => {
  const blob = details + workspace + api;
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('engine-enroll'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.match(api, /\/client\/shipments\/\$\{/);
  assert.equal(/'driver'/.test(showResource), false);
  assert.equal(/'activeAssignment'/.test(showResource), false);
});

test('no fake ETA / GPS / Engine stage is introduced', () => {
  assert.equal(details.includes('SafeMap'), false);
  assert.equal(details.includes('latitude'), false);
  assert.equal(details.includes('longitude'), false);
  assert.equal(details.includes('GPS'), false);
  assert.equal(details.includes('rest-stop'), false);
  assert.equal(/\beta\b/.test(details), false);
  assert.equal(/\beta\b/.test(workspace), false);
  assert.equal(/\beta\b/.test(api), false);
  // Engine stages filtered via customerFacingShipmentStatus → isEngineStageName in workspace.
  assert.match(workspace, /isEngineStageName/);
  assert.match(details, /customerFacingShipmentStatus/);
});

test('shipment remains read-only from unsupported operations', () => {
  assert.equal(details.includes('updateClientShipment'), false);
  assert.equal(details.includes('Create Shipment'), false);
  assert.equal(/\bTrack\b/.test(details), false);
  assert.equal(details.includes('Message driver'), false);
  assert.equal(details.includes('cancelShipment'), false);
  assert.equal(details.includes('approve'), false);
  assert.equal(details.includes('convert'), false);
});

test('loading / error / 404 behavior remains safe', () => {
  assert.match(details, /shipment-details-loading/);
  assert.match(details, /shipment-details-error/);
  assert.match(details, /shipment-details-forbidden/);
  assert.match(details, /shipment-details-not-found/);
  assert.match(details, /shipment-details-retry/);
  assert.match(details, /statusCode === 401/);
  assert.match(details, /errorStatus === 403/);
  assert.match(details, /errorStatus === 404/);
  assert.match(details, /do not reinterpret as empty details/);
});

test('existing navigation remains intact', () => {
  assert.match(tabs, /tabBarLabel: 'Home'/);
  assert.match(tabs, /tabBarLabel: 'Requests'/);
  assert.match(tabs, /tabBarLabel: 'Shipments'/);
  assert.match(tabs, /tabBarLabel: 'Messages'/);
  assert.match(tabs, /tabBarLabel: 'Profile'/);
  assert.equal(/tabBarLabel: 'Tracking'/.test(tabs), false);
  assert.match(layout, /request-details\/\[id\]/);
  assert.match(layout, /conversation\/\[id\]/);
  assert.match(details, /shipment-details-back/);
});
