/**
 * C2.6-C5-D7-A Post-MVP audit — verification that this phase is docs-only.
 * Run: node ./scripts/verify-c26c5d7a-shipment-post-mvp-audit.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
}

let passed = 0;
function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    console.error(`FAIL  ${name}`);
    console.error(e);
    process.exitCode = 1;
  }
}

check('audit document exists', () => {
  const doc = path.join(repoRoot, 'docs/C2.6C5D7A_CLIENT_SHIPMENT_POST_MVP_AUDIT.md');
  assert.ok(fs.existsSync(doc), 'missing audit doc');
  const body = fs.readFileSync(doc, 'utf8');
  for (const section of [
    'Executive summary',
    'Current completed scope',
    'Completion matrix',
    'Feature classification',
    'Search',
    'Status analysis',
    'Tracking analysis',
    'Create Shipment',
    'Actions analysis',
    'Notifications analysis',
    'Home integration',
    'MVP judgment',
    'Proposed next phase',
    'Explicit blockers',
    'Security notes',
  ]) {
    assert.match(body, new RegExp(section, 'i'), section);
  }
});

check('no create-shipment implementation in Client App', () => {
  const list = readClient('src/app/(tabs)/shipments.tsx');
  const details = readClient('src/app/shipment-details/[id].tsx');
  assert.equal(list.includes('Create Shipment'), false);
  assert.equal(details.includes('Create Shipment'), false);
  assert.equal(details.includes('POST'), false);
});

check('no ops tracking endpoints in shipment domain (client tracking contract allowed)', () => {
  const list = readClient('src/app/(tabs)/shipments.tsx');
  const details = readClient('src/app/shipment-details/[id].tsx');
  const api = readClient('src/features/shipments/shipmentsApi.ts');
  const tracking = readClient('src/app/(tabs)/tracking.tsx');
  assert.equal(list.includes('live-fleet'), false);
  assert.equal(details.includes('live-fleet'), false);
  assert.equal(api.includes('tracking-logs'), false);
  assert.equal(api.includes('tracking-states'), false);
  assert.equal(api.includes('live-fleet'), false);
  assert.match(api, /getClientShipmentTracking/);
  assert.match(tracking, /Redirect/);
});

check('no shipment action buttons on details', () => {
  const details = readClient('src/app/shipment-details/[id].tsx');
  for (const token of ['Cancel', 'Edit', 'POD', 'Download', 'Message driver']) {
    assert.equal(details.includes(token), false, token);
  }
  assert.equal(/\bTrack\b/.test(details), false);
});

check('client shipment API helpers remain list+show+tracking (no mutate helpers)', () => {
  const api = readClient('src/features/shipments/shipmentsApi.ts');
  assert.match(api, /listClientShipments/);
  assert.match(api, /getClientShipment/);
  assert.match(api, /getClientShipmentTracking/);
  assert.equal(api.includes('createClientShipment'), false);
  assert.equal(api.includes('updateClientShipment'), false);
  assert.equal(api.includes("'/shipments'"), false);
});

check('backend client routes remain list+show+tracking GET only (no mutating client shipment verbs)', () => {
  const routes = readRepo('backend/routes/api.php');
  assert.match(routes, /v1\.client\.shipments\.index/);
  assert.match(routes, /v1\.client\.shipments\.show/);
  assert.match(routes, /v1\.client\.shipments\.tracking/);
  assert.equal(routes.includes('v1.client.shipments.store'), false);
  assert.equal(routes.includes('v1.client.shipments.update'), false);
  assert.equal(routes.includes('client/tracking'), false);
  assert.equal(/Route::(post|put|patch|delete)\(['"`]\/shipments/.test(
    routes.slice(routes.indexOf("prefix('client')"), routes.indexOf('// Shipment Routes (ops'))
  ), false);
});

check('messaging internals not coupled to shipment show', () => {
  const messagingApi = readClient('src/features/messaging/messagingApi.ts');
  const handoff = readClient('src/app/shipment/[id].tsx');
  assert.equal(messagingApi.includes('getClientShipment'), false);
  assert.equal(messagingApi.includes('/client/shipments'), false);
  assert.match(handoff, /findAuthorizedConversationForShipment/);
});

check('audit phase deliverables are docs + verify only (script self-check)', () => {
  assert.ok(fs.existsSync(path.join(clientRoot, 'scripts/verify-c26c5d7a-shipment-post-mvp-audit.mjs')));
  assert.ok(fs.existsSync(path.join(repoRoot, 'docs/C2.6C5D7A_CLIENT_SHIPMENT_POST_MVP_AUDIT.md')));
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-D7-A audit verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-D7-A audit verification OK — ${passed} checks passed.`);
}
