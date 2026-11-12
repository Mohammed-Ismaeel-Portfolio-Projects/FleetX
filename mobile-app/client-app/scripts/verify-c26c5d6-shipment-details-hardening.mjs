/**
 * C2.6-C5-D6 Client Shipment Details Hardening — focused verification.
 * Run: node ./scripts/verify-c26c5d6-shipment-details-hardening.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
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

const details = read('src/app/shipment-details/[id].tsx');
const api = read('src/features/shipments/shipmentsApi.ts');
const layout = read('src/app/_layout.tsx');

check('client details endpoint remains /client/shipments/{id}', () => {
  assert.match(details, /getClientShipment/);
  assert.match(api, /function getClientShipment/);
  assert.match(api, /\/client\/shipments\/\$\{/);
});

check('ops /shipments/{id} is not referenced', () => {
  assert.equal(details.includes("'/shipments/"), false);
  assert.equal(details.includes('"/shipments/'), false);
  assert.equal(api.includes("'/shipments/"), false);
  assert.equal(/get\([^)]*['"`]\/shipments\//.test(api), false);
});

check('shipment ID comes from the route', () => {
  assert.match(details, /useLocalSearchParams/);
  assert.match(details, /shipmentId/);
  assert.match(details, /getClientShipment\(idToLoad\)|getClientShipment\(shipmentId/);
});

check('no company/tenant parameter is sent', () => {
  assert.equal(details.includes('customer_company_id'), false);
  assert.equal(details.includes('company_id:'), false);
  assert.equal(details.includes('driver_id'), false);
  const start = api.indexOf('export async function getClientShipment(');
  const end = api.indexOf('export async function getClientShipmentTracking');
  assert.ok(start >= 0, 'getClientShipment present');
  const body = end > start ? api.slice(start, end) : api.slice(start);
  // Exclude trailing JSDoc/helpers that mention forbidden params only as guards.
  const tryBlock = body.slice(0, body.indexOf('interface ClientShipmentTrackingApi'));
  const scoped = tryBlock.length > 0 ? tryBlock : body;
  assert.equal(scoped.includes('params:'), false);
  assert.equal(/query\.customer_company_id|params:[\s\S]*customer_company_id/.test(scoped), false);
});

check('loading state exists', () => {
  assert.match(details, /shipment-details-loading/);
  assert.match(details, /Loading shipment/);
  assert.match(details, /loadedForId/);
});

check('success state exists', () => {
  assert.match(details, /shipment-details-screen/);
  assert.match(details, /shipment-details-content/);
  assert.match(details, /shipment-details-number/);
  assert.match(details, /shipment-details-status-raw/);
});

check('403 state exists without misleading empty data', () => {
  assert.match(details, /shipment-details-forbidden/);
  assert.match(details, /Access unavailable/);
  assert.match(details, /errorStatus === 403/);
});

check('404 state exists', () => {
  assert.match(details, /shipment-details-not-found/);
  assert.match(details, /errorStatus === 404/);
  assert.match(details, /could not be found for your company/);
});

check('retry exists for recoverable errors', () => {
  assert.match(details, /shipment-details-retry/);
  assert.match(details, /canRetry/);
  assert.match(details, /!isForbidden/);
});

check('retry uses the same shipment ID', () => {
  assert.match(details, /shipmentIdRef/);
  assert.match(details, /getClientShipment\(idToLoad\)/);
  assert.match(details, /onPress=\{\(\) => void load\(\)\}/);
});

check('request safety / no stale ID data', () => {
  assert.match(details, /inFlightRef/);
  assert.match(details, /requestSeqRef/);
  assert.match(details, /mountedRef/);
  assert.match(details, /drop stale shipment|loadedForId !== shipmentId|Route ID change/);
});

check('forbidden PII remains absent', () => {
  for (const token of [
    'driverPhone',
    'driver_phone',
    'pickupAddress',
    'deliveryAddress',
    'pickup_phone',
    'specialInstructions',
    'item.notes',
    'trackingLogs',
    'activeAssignment',
    'latitude',
    'longitude',
  ]) {
    assert.equal(details.includes(token), false, token);
  }
});

check('no tracking/GPS/actions introduced', () => {
  for (const token of [
    'GPS',
    'live location',
    'Edit',
    'Create Shipment',
    'Contact dispatcher',
    'Message driver',
    'POD',
    'Documents',
    'AI recommendation',
  ]) {
    assert.equal(details.includes(token), false, token);
  }
  assert.equal(/\bTrack\b/.test(details), false);
  assert.match(details, /Shipment progress/);
});

check('back navigation exists', () => {
  assert.match(details, /shipment-details-back/);
  assert.match(details, /goBack|router\.back/);
  assert.match(details, /accessibilityLabel="Go back to shipment list"/);
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(details), false);
  assert.equal(/router\.push\(\s*['"`]\/shipment-details['"`]/.test(details), false);
});

check('no legacy /shipment-details route restored', () => {
  assert.equal(fs.existsSync(path.join(root, 'src/app/shipment-details.tsx')), false);
  assert.match(layout, /shipment-details\/\[id\]/);
});

check('no /shipment/[id] navigation introduced', () => {
  assert.equal(details.includes('`/shipment/${'), false);
  assert.equal(/href=\{`\/shipment\//.test(details), false);
  assert.match(details, /Distinct from protected \/shipment\/\[id\]/);
});

check('401 delegated to AuthContext', () => {
  assert.match(details, /statusCode === 401/);
  assert.match(details, /AuthContext/);
  assert.match(details, /do not reinterpret as empty details/);
});

check('hierarchy sections present', () => {
  assert.match(details, /shipment-details-route/);
  assert.match(details, /shipment-details-dates/);
  assert.match(details, /shipment-details-priority|Priority/);
  assert.match(details, /shipment-details-cargo/);
  assert.match(details, /shipment-details-company/);
  assert.match(details, /shipment-details-timestamps/);
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-D6 details hardening verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-D6 details hardening verification OK — ${passed} checks passed.`);
}
