/**
 * C2.6-C5-D7-B — Client shipment search + exact status filter verification.
 * Run: node ./scripts/verify-c26c5d7b-shipment-search-status.mjs
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

const screen = readClient('src/app/(tabs)/shipments.tsx');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const types = readClient('src/features/shipments/types.ts');
const request = readRepo('backend/app/Http/Requests/Client/QueryClientShipmentsRequest.php');
const action = readRepo('backend/app/Actions/Shipment/QueryClientShipmentsAction.php');

check('1. Search input exists', () => {
  assert.match(screen, /testID="shipment-search-input"/);
  assert.match(screen, /TextInput/);
  assert.match(screen, /Search shipments/);
});

check('2. Search uses approved API parameter', () => {
  assert.match(request, /'search'/);
  assert.match(action, /params\['search'\]/);
  assert.match(types, /search\?:/);
  assert.match(api, /if \(params\.search\) query\.search = params\.search/);
  assert.match(screen, /\.\.\.\(search \? \{ search \} : \{\}\)/);
  assert.match(screen, /listClientShipments/);
  assert.equal(screen.includes('.filter(') && screen.includes('localSearch'), false);
});

check('3. Status filter exists', () => {
  assert.match(screen, /testID="shipment-status-filters"/);
  assert.match(screen, /testID="shipment-status-all"/);
  assert.match(screen, /EXACT_STATUS_FILTERS|CLIENT_SHIPMENT_STATUS_FILTERS/);
});

check('4. Status uses exact Client lifecycle names (C3.11)', () => {
  const workspace = readClient('src/features/shipments/shipmentWorkspace.ts');
  assert.match(workspace, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(workspace, /Waiting Assignment/);
  assert.match(workspace, /In Transit/);
  assert.match(screen, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(screen, /customerFacingShipmentStatus/);
  // Legacy snake_case StatusesSeeder names must not be the filter source of truth.
  assert.equal(screen.includes("'pending'"), false);
  assert.equal(screen.includes("'in_transit'"), false);
  assert.equal(screen.includes("'at_customs'"), false);
  assert.match(request, /'status'/);
  assert.match(action, /where\('name', \$statusName\)/);
  assert.match(screen, /\.\.\.\(status \? \{ status \} : \{\}\)/);
});

check('5. No invented status buckets', () => {
  for (const bucket of ['Active', 'Delayed']) {
    assert.equal(screen.includes(`'${bucket}'`), false, bucket);
    assert.equal(screen.includes(`"${bucket}"`), false, bucket);
  }
  assert.equal(screen.includes('statusBuckets'), false);
  assert.equal(screen.includes('bucket='), false);
  // Exact lifecycle names live in shipmentWorkspace — not as invented UX buckets on the screen.
  assert.match(screen, /CLIENT_SHIPMENT_STATUS_FILTERS/);
});

check('6. No company/tenant parameters', () => {
  assert.match(request, /customer_company_id.*prohibited/s);
  assert.equal(screen.includes('customer_company_id'), false);
  assert.equal(screen.includes('company_id:'), false);
  assert.equal(screen.includes('driver_id'), false);
  assert.equal(screen.includes('dispatcher_id'), false);
  assert.match(api, /delete \(query as \{ customer_company_id/);
  assert.match(api, /delete \(query as \{ company_id/);
});

check('7. No ops endpoints', () => {
  assert.match(api, /\/client\/shipments/);
  assert.equal(api.includes('/shipments/queue'), false);
  assert.equal(api.includes('/shipments/kpis'), false);
  assert.equal(screen.includes('/shipments/queue'), false);
  assert.equal(screen.includes("'/shipments'"), false);
});

check('8. Reset/clear behavior exists', () => {
  assert.match(screen, /testID="shipment-search-clear"/);
  assert.match(screen, /testID="shipment-filters-reset"/);
  assert.match(screen, /clearFilters/);
  assert.match(screen, /Reset filters/);
  assert.match(screen, /setStatusFilter\(null\)/);
  assert.match(screen, /setAppliedSearch\(''\)/);
});

check('9. Search/filter preserve shipment-card navigation', () => {
  assert.match(screen, /shipment-details\/\$\{item\.id\}/);
  assert.match(screen, /testID=\{`shipment-card-\$\{item\.id\}`\}/);
  assert.match(screen, /router\.push\(`\/shipment-details\/\$\{item\.id\}`\)/);
});

check('10. Empty filtered results distinct from Unavailable', () => {
  assert.match(screen, /testID=\{filtersActive \? 'shipment-empty-filtered' : 'shipment-empty'\}/);
  assert.match(screen, /No shipments found/);
  assert.match(screen, /No shipments yet/);
  assert.match(screen, /shipments-unlinked/);
  assert.match(screen, /UnavailableDomainScreen/);
  const filteredIdx = screen.indexOf('shipment-empty-filtered');
  const slice = screen.slice(Math.max(0, filteredIdx - 120), filteredIdx + 200);
  assert.equal(slice.includes('UnavailableDomainScreen'), false);
});

check('11. Pagination resets when criteria change', () => {
  assert.match(screen, /appliedSearch, statusFilter/);
  assert.match(screen, /pageRef\.current = 1/);
  assert.match(screen, /requestSeqRef\.current \+= 1/);
  assert.match(screen, /inFlightRef\.current = false/);
  assert.match(screen, /void loadPage\(1, 'replace'\)/);
  assert.match(screen, /SEARCH_DEBOUNCE_MS/);
});

check('12. Existing C5 state/error guards remain', () => {
  assert.match(screen, /inFlightRef/);
  assert.match(screen, /requestSeqRef/);
  assert.match(screen, /requestSeq !== requestSeqRef\.current/);
  assert.match(screen, /errorSource === 'refresh'/);
  assert.match(screen, /errorSource === 'append'/);
  assert.match(screen, /errorSource === 'initial'/);
  assert.match(screen, /statusCode === 401/);
  assert.match(screen, /errorStatus === 403/);
  assert.match(screen, /testID="shipment-forbidden"/);
  assert.match(screen, /Preserve existing rows on refresh failure/);
  assert.match(screen, /Preserve already loaded shipments/);
});

check('phase documentation exists', () => {
  const doc = path.join(repoRoot, 'docs/C2.6C5D7B_CLIENT_SHIPMENT_SEARCH_STATUS.md');
  assert.ok(fs.existsSync(doc), 'missing D7-B doc');
  const body = fs.readFileSync(doc, 'utf8');
  assert.match(body, /search/i);
  assert.match(body, /status/i);
  assert.match(body, /EXACT/i);
  assert.match(body, /\/api\/v1\/client\/shipments/);
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-D7-B verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-D7-B verification OK — ${passed} checks passed.`);
}
