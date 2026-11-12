/**
 * C2.9-C3.5 Static Verification — Client Request → Shipment Handoff
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

const details = readClient('src/app/request-details/[id].tsx') || '';
const list = readClient('src/app/(tabs)/requests.tsx') || '';
const api = readClient('src/features/requests/requestsApi.ts') || '';
const types = readClient('src/features/requests/types.ts') || '';
const status = readClient('src/features/requests/requestStatus.ts') || '';
const home = readClient('src/app/(tabs)/index.tsx') || '';
const overview = readClient('src/features/home/homeOverview.ts') || '';
const form = readClient('src/app/request-form.tsx') || '';
const shipmentsList = readClient('src/app/(tabs)/shipments.tsx') || '';
const shipmentDetails = readClient('src/app/shipment-details/[id].tsx') || '';
const showResource = read('backend/app/Http/Resources/Client/ClientShipmentRequestShowResource.php') || '';
const listResource = read('backend/app/Http/Resources/Client/ClientShipmentRequestListResource.php') || '';
const linkedResource = read('backend/app/Http/Resources/Client/ClientLinkedShipmentResource.php') || '';
const getAction = read('backend/app/Actions/ShipmentOrder/GetClientShipmentRequestAction.php') || '';
const queryAction = read('backend/app/Actions/ShipmentOrder/QueryClientShipmentRequestsAction.php') || '';

const clientSurface = [details, list, api, types, status, home, overview, form].join('\n');

test('C3.5 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_5_CLIENT_REQUEST_TO_SHIPMENT_HANDOFF.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.5/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'Existing architecture',
    'Client contract',
    'Request lifecycle',
    'Converted',
    'Request → Shipment',
    'Navigation',
    'Authorization',
    'Loading',
    'Backend',
    'Tests',
    'Verifier',
    'Known warnings',
    'Deferred work',
  ]) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), s);
  }
});

test('converted request state is recognized without new request or shipment statuses', () => {
  assert.match(status, /isConvertedStatus/);
  assert.match(status, /REQUEST_STATUS\.CONVERTED/);
  assert.match(status, /'converted'/);
  assert.match(details, /isConvertedStatus\(statusRaw\)/);
  assert.match(list, /isConvertedStatus\(statusRaw\)/);
  assert.equal(status.includes('EnRoutePickup'), false);
  assert.equal(clientSurface.includes('createRequestStatus'), false);
});

test('linked shipment relationship is handled safely from the Client contract', () => {
  assert.match(types, /linkedShipment/);
  assert.match(api, /linked_shipment/);
  assert.match(api, /function mapLinkedShipment/);
  assert.match(status, /function linkedShipmentId/);
  assert.match(details, /linkedShipmentId\(request\.linkedShipment\)/);
  assert.match(list, /linkedShipmentId\(item\.linkedShipment\)/);
  assert.match(showResource, /linked_shipment/);
  assert.match(listResource, /linked_shipment/);
  assert.match(linkedResource, /shipment_number/);
  assert.equal(showResource.includes('converted_shipment_id'), false);
  assert.equal(listResource.includes('converted_shipment_id'), false);
  assert.equal(details.includes('converted_shipment_id'), false);
  assert.equal(api.includes("converted_shipment_id"), true);
  assert.match(api, /delete \(body as \{ converted_shipment_id/);
  assert.match(getAction, /convertedShipment\.status/);
  assert.match(queryAction, /convertedShipment\.status/);
});

test('Open Shipment CTA exists only when a valid linked shipment id is present', () => {
  assert.match(details, /request-details-open-shipment/);
  assert.match(details, /shipmentId \?/);
  assert.match(details, /openLinkedShipment\(shipmentId\)/);
  assert.match(list, /request-open-shipment-/);
  assert.match(list, /if \(!shipmentId\) return/);
  assert.equal(/Open Shipment[\s\S]*converted_shipment_id/.test(details), false);
});

test('CTA targets the existing Client Shipment Workspace', () => {
  assert.match(details, /router\.push\(`\/shipment-details\/\$\{id\}`\)/);
  assert.match(list, /router\.push\(`\/shipment-details\/\$\{shipmentId\}`\)/);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/converted-shipment/[id].tsx')), false);
  assert.equal(clientSurface.includes('converted-shipment/'), false);
  assert.match(shipmentDetails, /getClientShipment/);
  assert.match(shipmentDetails, /shipment-details/);
});

test('no Dispatcher, Admin, Engine, Assignment, or live-fleet APIs are used', () => {
  assert.equal(clientSurface.includes('/dispatcher/'), false);
  assert.equal(clientSurface.includes('/admin/'), false);
  assert.equal(clientSurface.includes('live-fleet'), false);
  assert.equal(clientSurface.includes('engine-enroll'), false);
  assert.equal(clientSurface.includes('/assignments/'), false);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/convert/.test(clientSurface), false);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/approve/.test(clientSurface), false);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/reject/.test(clientSurface), false);
  assert.equal(api.includes('/client/shipment-requests'), true);
  assert.equal(details.includes('getClientShipment('), false);
});

test('no shipment is fabricated locally after conversion', () => {
  assert.equal(clientSurface.includes('createLocalShipment'), false);
  assert.equal(clientSurface.includes('FX-4920'), false);
  assert.equal(clientSurface.includes('LIVE ETA'), false);
  assert.equal(/apiClient\.post[\s\S]*\/client\/shipments/.test(api), false);
  assert.equal(shipmentsList.includes('createClientShipment'), false);
  assert.equal(details.includes('optimisticShipment'), false);
});

test('missing relationship is handled honestly', () => {
  assert.match(details, /Shipment created, but shipment details are not currently available/);
  assert.match(details, /request-details-missing-shipment/);
  assert.equal(details.includes('SHP-PLACEHOLDER'), false);
  assert.match(status, /never invented|Safe linked shipment id/i);
});

test('converted requests remain read-only', () => {
  assert.match(status, /isEditableRequestStatus/);
  assert.match(form, /isEditableRequestStatus/);
  assert.match(details, /isDraftStatus\(statusRaw\) \?/);
  assert.match(details, /isChangesRequestedStatus\(statusRaw\) \?/);
  assert.match(details, /isEditableRequestStatus\(statusRaw\) \|\| converted \? null/);
});

test('existing request workflow and shipment navigation remain intact', () => {
  assert.match(list, /listClientShipmentRequests/);
  assert.match(form, /createClientShipmentRequest/);
  assert.match(form, /submitClientShipmentRequest/);
  assert.match(form, /resubmitClientShipmentRequest/);
  assert.match(details, /Continue Editing/);
  assert.match(details, /Edit and Resubmit/);
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.match(tabs, /tabBarLabel: 'Home'/);
  assert.match(tabs, /tabBarLabel: 'Requests'/);
  assert.match(tabs, /tabBarLabel: 'Shipments'/);
  assert.match(tabs, /tabBarLabel: 'Messages'/);
  assert.match(tabs, /tabBarLabel: 'Profile'/);
  assert.equal(/tabBarLabel: 'Tracking'/.test(tabs), false);
  assert.match(shipmentsList, /listClientShipments/);
  assert.match(shipmentDetails, /getClientShipment/);
  const layout = readClient('src/app/_layout.tsx');
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(layout, /request-details\/\[id\]/);
});

test('Requests list communicates converted continuity without overloading rows', () => {
  assert.match(list, /Converted · Shipment created/);
  assert.match(list, /Open Shipment/);
  assert.equal(list.includes('EnRoutePickup'), false);
  assert.equal(list.includes('dispatcher'), false);
});

test('Home remains an overview; converted continuity uses existing shipment navigation', () => {
  assert.match(overview, /selectPendingRequests/);
  assert.match(overview, /Converted requests are excluded from Home pending rows/);
  assert.match(overview, /shipmentWorkspaceIdForConvertedRequest/);
  assert.match(home, /shipmentWorkspaceIdForConvertedRequest/);
  assert.match(home, /\/shipment-details\/\$\{id\}/);
  assert.match(home, /\/request-details\/\$\{id\}/);
  assert.equal(/createClientShipmentRequest|submitClientShipmentRequest/.test(home), false);
  assert.equal(home.includes('home-converted-shipment-card'), false);
});

test('linked shipment resource is a Client-safe allowlist', () => {
  assert.match(linkedResource, /'id'/);
  assert.match(linkedResource, /'shipment_number'/);
  assert.match(linkedResource, /'status'/);
  assert.equal(linkedResource.includes('driver'), false);
  assert.equal(linkedResource.includes('vehicle'), false);
  assert.equal(linkedResource.includes('assignment'), false);
  assert.equal(linkedResource.includes('gps'), false);
  assert.equal(linkedResource.includes('eta'), false);
  assert.equal(linkedResource.includes('shipment_order_id'), false);
  assert.ok(read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestLinkedShipmentTest.php'));
});
