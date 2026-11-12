/**
 * C2.7-D1 — Client Tracking MVP UI verification (static).
 * Run: node ./scripts/verify-c27d1-client-tracking-ui.mjs
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

const api = readClient('src/features/shipments/shipmentsApi.ts');
const types = readClient('src/features/shipments/types.ts');
const progress = readClient('src/app/shipment-tracking/[id].tsx');
const details = readClient('src/app/shipment-details/[id].tsx');
const trackingTab = readClient('src/app/(tabs)/tracking.tsx');
const list = readClient('src/app/(tabs)/shipments.tsx');
const layout = readClient('src/app/_layout.tsx');
const backendRoute = readRepo('backend/routes/api.php');

const trackingFn = api.slice(api.indexOf('export async function getClientShipmentTracking'));

check('API uses client tracking endpoint with shipment id path only', () => {
  assert.match(api, /getClientShipmentTracking/);
  assert.match(trackingFn, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
  assert.match(backendRoute, /v1\.client\.shipments\.tracking/);
  assert.equal(/params:\s*\{[^}]*company_id/.test(trackingFn), false);
  assert.equal(/params:\s*\{[^}]*customer_company_id/.test(trackingFn), false);
  assert.equal(trackingFn.includes('tracking-logs'), false);
  assert.equal(trackingFn.includes('tracking-states'), false);
  assert.equal(trackingFn.includes('live-fleet'), false);
  assert.equal(trackingFn.includes('/dashboard/'), false);
  assert.equal(trackingFn.includes('params:'), false);
  assert.match(api, /getClientShipmentTracking[\s\S]*?\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
});

check('types cover C2.7-C allowlist milestones + C5.1 lastKnownPosition', () => {
  assert.match(types, /ClientShipmentTracking/);
  assert.match(types, /ClientShipmentMilestone/);
  assert.match(types, /'created' \| 'pickup' \| 'delivery'/);
  assert.match(types, /ClientLastKnownPosition/);
  assert.match(types, /lastKnownPosition: ClientLastKnownPosition \| null/);
  assert.equal(/\beta\b/i.test(types), false);
  assert.equal(/\bdriver\b/i.test(types), false);
  assert.equal(/\bvehicle\b/i.test(types), false);
  assert.equal(/\bplate\b/i.test(types), false);
  assert.equal(/trackingLogs/.test(types), false);
});

check('progress screen UI contract', () => {
  assert.match(progress, /getClientShipmentTracking/);
  assert.match(progress, /shipment-progress-number/);
  assert.match(progress, /shipment-progress-status/);
  assert.match(progress, /customerFacingShipmentStatus/);
  assert.equal(progress.includes('shipment-progress-status-raw'), false);
  assert.match(progress, /shipment-progress-route/);
  assert.match(progress, /Shipment Progress/);
  assert.match(progress, /Milestones/);
  assert.match(progress, /Planned/);
  assert.match(progress, /Actual/);
  assert.match(progress, /shipment-progress-milestone-created/);
  assert.match(progress, /shipment-progress-milestone-pickup/);
  assert.match(progress, /shipment-progress-milestone-delivery/);
  assert.match(progress, /shipment-progress-last-known/);
  assert.match(progress, /Last known location/);
  assert.equal(progress.includes('Status History'), false);
  assert.equal(progress.includes('Status Events'), false);
  assert.equal(progress.includes('Tracking History'), false);
  assert.equal(progress.includes('live-fleet'), false);
  assert.equal(progress.includes('SafeMap'), false);
  assert.equal(/\bETA\b/.test(progress), false);
});

check('state handling present', () => {
  assert.match(progress, /shipment-progress-loading/);
  assert.match(progress, /shipment-progress-screen/);
  assert.match(progress, /shipment-progress-forbidden/);
  assert.match(progress, /shipment-progress-not-found/);
  assert.match(progress, /shipment-progress-error/);
  assert.match(progress, /shipment-progress-retry/);
  assert.match(progress, /statusCode === 401/);
  assert.match(progress, /AuthContext|do not reinterpret/);
  assert.match(progress, /errorIsNetwork/);
  assert.match(progress, /shipment-progress-unlinked/);
  assert.match(progress, /shipment-progress-forbidden-role/);
  assert.match(progress, /errorStatus === 403/);
  assert.match(progress, /!isForbidden/);
});

check('navigation: details entry with id; no card Track; tab remains honest', () => {
  assert.match(layout, /shipment-tracking\/\[id\]/);
  assert.match(details, /shipment-details-progress/);
  assert.match(details, /router\.push\(`\/shipment-tracking\/\$\{shipmentId\}`\)/);
  assert.equal(/\bTrack\b/.test(details), false);
  assert.equal(list.includes('shipment-tracking'), false);
  assert.equal(/\bTrack\b/.test(list), false);
  assert.match(trackingTab, /Redirect/);
  assert.equal(trackingTab.includes('getClientShipmentTracking'), false);
  assert.equal(trackingTab.includes('listClientShipments'), false);
});

check('no invented first-shipment selection on tracking route', () => {
  assert.equal(trackingTab.toLowerCase().includes('first shipment'), false);
  assert.equal(trackingTab.includes('getClientShipmentTracking'), false);
});

if (process.exitCode) {
  console.error(`\nC2.7-D1 verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.7-D1 verification OK — ${passed} checks passed.`);
}
