/**
 * C2.9-C3.4 Static Verification — Client Shipment Requests Workflow
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

const workflow = [
  'src/app/(tabs)/requests.tsx',
  'src/app/request-details/[id].tsx',
  'src/app/request-form.tsx',
  'src/features/requests/requestsApi.ts',
  'src/features/requests/types.ts',
  'src/features/requests/requestStatus.ts',
  'src/features/requests/requestForm.ts',
]
  .map((f) => readClient(f) || '')
  .join('\n');

test('C3.4 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_4_CLIENT_SHIPMENT_REQUEST_WORKFLOW.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.4/i);
  assert.match(doc, /HARD STOP/i);
  assert.match(doc, /Backend production changes:\s*NONE/i);
  for (const s of [
    'Objective',
    'Existing architecture reused',
    'API contracts',
    'Request state model',
    'List architecture',
    'Create flow',
    'Draft',
    'Review',
    'Submission',
    'Resubmission',
    'Error handling',
    'Authorization',
    'Navigation',
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

test('Requests tab is a functional Requests Center using the client list API', () => {
  const requests = readClient('src/app/(tabs)/requests.tsx');
  assert.match(requests, /listClientShipmentRequests/);
  assert.match(requests, /requests-screen/);
  assert.match(requests, /requests-list/);
  assert.match(requests, /requests-empty/);
  assert.match(requests, /requests-loading/);
  assert.match(requests, /requests-error/);
  assert.match(requests, /Create Shipment Request/);
  assert.equal(requests.includes('requests-shell'), false);
  assert.equal(requests.includes('Coming next'), false);
});

test('request API uses only client shipment-request endpoints', () => {
  const api = readClient('src/features/requests/requestsApi.ts');
  assert.match(api, /apiClient\.get[\s\S]*\/client\/shipment-requests/);
  assert.match(api, /apiClient\.post[\s\S]*\/client\/shipment-requests'/);
  assert.match(api, /apiClient\.patch[\s\S]*\/client\/shipment-requests\/\$\{/);
  assert.match(api, /\/client\/shipment-requests\/\$\{encodeURIComponent\(requestId\)\}\/submit/);
  assert.match(api, /\/client\/shipment-requests\/\$\{encodeURIComponent\(requestId\)\}\/resubmit/);
  assert.match(api, /function getClientShipmentRequest/);
  assert.match(api, /function createClientShipmentRequest/);
  assert.match(api, /function updateClientShipmentRequest/);
  assert.match(api, /function submitClientShipmentRequest/);
  assert.match(api, /function resubmitClientShipmentRequest/);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/convert/.test(api), false);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/approve/.test(api), false);
  assert.equal(api.includes('/dispatcher/'), false);
  assert.equal(api.includes('live-fleet'), false);
  assert.equal(api.includes('engine-enroll'), false);
  assert.match(api, /delete \(body as \{ customer_company_id/);
});

test('create/edit/review/submit/resubmit workflow exists with duplicate-submit guard', () => {
  const form = readClient('src/app/request-form.tsx');
  assert.match(form, /createClientShipmentRequest/);
  assert.match(form, /updateClientShipmentRequest/);
  assert.match(form, /submitClientShipmentRequest/);
  assert.match(form, /resubmitClientShipmentRequest/);
  assert.match(form, /request-save-draft/);
  assert.match(form, /request-go-review/);
  assert.match(form, /request-review/);
  assert.match(form, /request-submit/);
  assert.match(form, /request-resubmit/);
  assert.match(form, /submitInFlight/);
  assert.match(form, /disabled=\{busy\}/);
  assert.match(form, /Save Draft/);
  assert.match(form, /Submit Request/);
  assert.match(form, /Resubmit Request/);
  assert.equal(form.includes('apiClient'), false);
});

test('request detail is status-aware and does not convert or approve requests', () => {
  const details = readClient('src/app/request-details/[id].tsx');
  assert.match(details, /getClientShipmentRequest/);
  assert.match(details, /Continue Editing/);
  assert.match(details, /Edit and Resubmit/);
  assert.match(details, /Awaiting FleetX review/);
  assert.match(details, /requestStatusGuidance/);
  assert.equal(details.includes('converted_shipment_id'), false);
  assert.equal(details.includes('live-fleet'), false);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/convert/.test(details), false);
  assert.equal(/\/shipment-requests\/\$\{[^}]+\}\/approve/.test(details), false);
  assert.match(details, /request-details-loading/);
  assert.match(details, /request-details-error|request-details-not-found/);
});

test('canonical request statuses are used; Engine stages are not', () => {
  const status = readClient('src/features/requests/requestStatus.ts');
  for (const name of [
    'draft',
    'submitted',
    'under_review',
    'changes_requested',
    'approved',
    'rejected',
    'converted',
  ]) {
    assert.match(status, new RegExp(`'${name}'`));
  }
  assert.equal(workflow.includes('EnRoutePickup'), false);
  assert.equal(workflow.includes('ArrivedDelivery'), false);
  assert.equal(workflow.includes('SimulationRuntime'), false);
  assert.equal(workflow.includes('ShipmentLifecycleEngine'), false);
});

test('no fake request/shipment records; Client App does not fabricate shipments', () => {
  assert.equal(workflow.includes('FX-4920'), false);
  assert.equal(workflow.includes('Robert Chen'), false);
  assert.equal(workflow.includes('FleetX AI'), false);
  assert.equal(workflow.includes('LIVE ETA'), false);
  const details = readClient('src/app/request-details/[id].tsx');
  assert.match(details, /still a shipment request, not a shipment|not a shipment/i);
  assert.equal(details.includes('createLocalShipment'), false);
  assert.equal(/approveClient|convertClient|rejectClient/.test(workflow), false);
});

test('error handling covers auth, not found, validation, and network', () => {
  const api = readClient('src/features/requests/requestsApi.ts');
  assert.match(api, /statusCode === 401/);
  assert.match(api, /statusCode === 403/);
  assert.match(api, /statusCode === 404/);
  assert.match(api, /statusCode === 409/);
  assert.match(api, /statusCode === 429/);
  assert.match(api, /fieldErrors/);
  const list = readClient('src/app/(tabs)/requests.tsx');
  assert.match(list, /errorStatus === 401|statusCode === 401/);
  const form = readClient('src/app/request-form.tsx');
  assert.match(form, /fieldErrors/);
  assert.match(form, /mapApiFieldErrors/);
});

test('C3.2 navigation remains; Home request rows open request detail', () => {
  const tabs = readClient('src/app/(tabs)/_layout.tsx');
  assert.match(tabs, /tabBarLabel: 'Home'/);
  assert.match(tabs, /tabBarLabel: 'Requests'/);
  assert.match(tabs, /tabBarLabel: 'Shipments'/);
  assert.match(tabs, /tabBarLabel: 'Messages'/);
  assert.match(tabs, /tabBarLabel: 'Profile'/);
  assert.equal(/tabBarLabel: 'Tracking'/.test(tabs), false);
  const home = readClient('src/app/(tabs)/index.tsx');
  assert.match(home, /\/request-details\/\$\{id\}/);
  assert.match(home, /\/\(tabs\)\/requests/);
  const layout = readClient('src/app/_layout.tsx');
  assert.match(layout, /request-details\/\[id\]/);
  assert.match(layout, /request-form/);
});
