/**
 * C2.6-C5-C Client Shipment List — global UX states verification.
 * Run: node ./scripts/verify-c26c5c-shipment-list-states.mjs
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

const screen = read('src/app/(tabs)/shipments.tsx');
const api = read('src/features/shipments/shipmentsApi.ts');
const messagingApi = read('src/features/messaging/messagingApi.ts');
const apiClient = read('src/lib/api/client.ts');

check('initial loading exists', () => {
  assert.match(screen, /testID="shipment-loading"/);
  assert.match(screen, /Loading shipments/);
  assert.match(screen, /loading && items\.length === 0/);
});

check('success path uses real data', () => {
  assert.match(screen, /listClientShipments/);
  assert.match(screen, /setItems\(result\.shipments\)/);
  assert.match(screen, /testID="shipment-list"/);
  assert.equal(screen.includes('FX-4920'), false);
  assert.equal(screen.includes('mockShipments'), false);
});

check('empty is distinct from unavailable', () => {
  assert.match(screen, /shipment-empty/);
  assert.match(screen, /No shipments yet/);
  assert.match(screen, /shipments-unlinked/);
  assert.match(screen, /UnavailableDomainScreen/);
  // Empty is successful list path, not UnavailableDomain
  const emptyIdx = screen.indexOf('shipment-empty');
  const unavailNearEmpty = screen.slice(Math.max(0, emptyIdx - 200), emptyIdx + 200);
  assert.equal(unavailNearEmpty.includes('UnavailableDomainScreen'), false);
});

check('initial error has retry', () => {
  assert.match(screen, /errorSource === 'initial'/);
  assert.match(screen, /testID="shipment-error"/);
  assert.match(screen, /testID="shipment-retry"/);
  assert.match(screen, /loadPage\(1, 'replace'\)/);
});

check('refresh failure preserves existing data', () => {
  assert.match(screen, /errorSource === 'refresh'/);
  assert.match(screen, /Preserve existing rows on refresh failure/);
  assert.match(screen, /shipment-inline-error/);
  assert.match(screen, /mode === 'refresh'/);
});

check('pagination failure preserves loaded data', () => {
  assert.match(screen, /errorSource === 'append'/);
  assert.match(screen, /shipment-load-more-error/);
  assert.match(screen, /shipment-load-more-retry/);
  assert.match(screen, /appendFailedPage/);
  assert.match(screen, /Preserve already loaded shipments/);
});

check('403 is not converted to empty', () => {
  assert.match(screen, /testID="shipment-forbidden"/);
  assert.match(screen, /Access unavailable/);
  assert.match(screen, /errorStatus === 403/);
  assert.match(screen, /!isForbidden \? \(/);
});

check('unlinked state uses UnavailableDomain', () => {
  assert.match(screen, /!identityLinked/);
  assert.match(screen, /testID="shipments-unlinked"/);
  assert.match(screen, /UnavailableDomainScreen/);
});

check('401 remains delegated to AuthContext', () => {
  assert.match(screen, /statusCode === 401/);
  assert.match(screen, /AuthContext/);
  assert.match(apiClient, /emitSessionInvalid/);
  assert.match(apiClient, /status === 401/);
  assert.match(screen, /do not reinterpret as empty list/);
});

check('network failures are honest', () => {
  assert.match(api, /isNetwork/);
  assert.match(api, /Network error\. Check your connection/);
  assert.match(screen, /errorIsNetwork/);
  assert.match(screen, /Connection problem/);
});

check('retry safety / locking preserved', () => {
  assert.match(screen, /inFlightRef/);
  assert.match(screen, /requestSeqRef/);
  assert.match(screen, /if \(inFlightRef\.current\) return/);
});

check('no ops endpoint / no tenant params', () => {
  assert.match(api, /\/client\/shipments/);
  assert.equal(api.includes("'/shipments'"), false);
  assert.equal(api.includes('/shipments/queue'), false);
  assert.equal(screen.includes('customer_company_id'), false);
  assert.equal(screen.includes('company_id:'), false);
  assert.equal(screen.includes('driver_id'), false);
});

check('Messaging untouched', () => {
  assert.equal(messagingApi.includes('/client/shipments'), false);
  assert.equal(messagingApi.includes('listClientShipments'), false);
});

check('structural testIDs present', () => {
  for (const id of [
    'shipment-screen',
    'shipment-loading',
    'shipment-list',
    'shipment-empty',
    'shipment-error',
    'shipment-retry',
    'shipment-refresh',
  ]) {
    assert.match(screen, new RegExp(id));
  }
  assert.match(screen, /testID="shipment-screen"/);
  assert.match(screen, /testID="shipment-list"/);
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-C states verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-C states verification OK — ${passed} checks passed.`);
}
