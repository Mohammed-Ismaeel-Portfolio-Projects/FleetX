/**
 * C5.4 — Client filtering & workflow polish (static + behavioral).
 * Run: node ./scripts/verify-c54-client-filtering.mjs
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
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e);
  }
}

const shipmentsUi = readClient('src/app/(tabs)/shipments.tsx');
const requestsUi = readClient('src/app/(tabs)/requests.tsx');
const shipmentWorkspace = readClient('src/features/shipments/shipmentWorkspace.ts');
const shipmentsApi = readClient('src/features/shipments/shipmentsApi.ts');
const requestStatus = readClient('src/features/requests/requestStatus.ts');
const requestsApi = readClient('src/features/requests/requestsApi.ts');
const requestTypes = readClient('src/features/requests/types.ts');
const queryShipments = readRepo('backend/app/Http/Requests/Client/QueryClientShipmentsRequest.php');
const queryRequests = readRepo('backend/app/Http/Requests/Client/QueryClientShipmentRequestsRequest.php');
const queryRequestsAction = readRepo('backend/app/Actions/ShipmentOrder/QueryClientShipmentRequestsAction.php');
const queryShipmentsAction = readRepo('backend/app/Actions/Shipment/QueryClientShipmentsAction.php');
const listResource = readRepo('backend/app/Http/Resources/Client/ClientShipmentRequestListResource.php');
const transition = readRepo('backend/app/Actions/ShipmentOrder/TransitionShipmentOrderStatusAction.php');

check('backend shipment priority contract remains low|normal|high|urgent', () => {
  assert.match(queryShipments, /'priority' => \['sometimes', 'in:low,normal,high,urgent'\]/);
  assert.match(queryShipmentsAction, /\$params\['priority'\]/);
});

check('Client shipment priority filter uses backend contract values', () => {
  assert.match(shipmentWorkspace, /CLIENT_SHIPMENT_PRIORITY_FILTERS/);
  assert.match(shipmentWorkspace, /'low'/);
  assert.match(shipmentWorkspace, /'normal'/);
  assert.match(shipmentWorkspace, /'high'/);
  assert.match(shipmentWorkspace, /'urgent'/);
  assert.match(shipmentsUi, /shipment-priority-filters/);
  assert.match(shipmentsUi, /shipment-priority-all/);
  assert.match(shipmentsUi, /priorityFilter/);
  assert.match(shipmentsUi, /\.\.\.\(priority \? \{ priority \} : \{\}\)/);
  assert.match(shipmentsApi, /if \(params\.priority\) query\.priority = params\.priority/);
});

check('shipment search + status remain and compose with priority', () => {
  assert.match(shipmentsUi, /shipment-search-input/);
  assert.match(shipmentsUi, /shipment-status-filters/);
  assert.match(shipmentsUi, /appliedSearch/);
  assert.match(shipmentsUi, /statusFilter/);
  assert.match(shipmentsUi, /priorityFilter/);
  assert.match(shipmentsUi, /shipment-filters-reset|Clear filters/);
  assert.match(shipmentsUi, /shipment-empty-filtered/);
  assert.match(shipmentsUi, /No shipments match your current filters/);
});

check('date filters and request search are not added', () => {
  assert.doesNotMatch(shipmentsUi, /pickup_from|delivery_from|DateTimePicker|date range/i);
  assert.doesNotMatch(requestsUi, /searchInput|request-search|params\.search/);
  assert.match(queryRequests, /'search' => \['prohibited'\]/);
});

check('optional shipment sort UI was not forced', () => {
  assert.doesNotMatch(shipmentsUi, /shipment-sort|sortFilter|planned_delivery_date/);
  assert.match(shipmentsUi, /sort_by: 'created_at'/);
});

check('Client-only request status filter is additive', () => {
  assert.match(queryRequests, /ShipmentOrderStatus::all/);
  assert.match(queryRequestsAction, /whereHas\('status'/);
  assert.match(queryRequestsAction, /ShipmentOrderStatus::TYPE/);
  assert.match(requestStatus, /CLIENT_REQUEST_STATUS_FILTERS/);
  assert.match(requestTypes, /status\?: string/);
  assert.match(requestsApi, /if \(params\.status\) query\.status = params\.status/);
  assert.match(requestsUi, /request-status-filters/);
  assert.match(requestsUi, /request-status-all/);
  assert.match(requestsUi, /requests-empty-filtered/);
  assert.match(requestsUi, /No requests match your current filter/);
});

check('request list still does not expose review_feedback', () => {
  assert.doesNotMatch(listResource, /review_feedback/);
  assert.doesNotMatch(requestsUi, /review_feedback|reviewFeedback|dispatcher_notes/);
});

check('lifecycle transition action remains untouched by C5.4', () => {
  assert.match(transition, /ShipmentOrderStatusHistory::query\(\)->create/);
  assert.doesNotMatch(queryRequestsAction, /TransitionShipmentOrderStatusAction/);
  assert.doesNotMatch(queryRequests, /TransitionShipmentOrderStatusAction/);
});

check('tenancy guards remain on request list query', () => {
  assert.match(queryRequests, /customer_company_id.*prohibited/s);
  assert.match(queryRequestsAction, /forCustomer\(\$companyId\)/);
  assert.match(requestsApi, /customer_company_id/);
  assert.match(requestsApi, /submitted_by_user_id/);
});

check('behavioral: priority filter values match backend enum', () => {
  const match = shipmentWorkspace.match(
    /CLIENT_SHIPMENT_PRIORITY_FILTERS\s*=\s*\[([^\]]+)\]/s
  );
  assert.ok(match);
  const values = [...match[1].matchAll(/'([^']+)'/g)].map((m) => m[1]);
  assert.deepEqual(values, ['low', 'normal', 'high', 'urgent']);
});

check('behavioral: request status filters are domain names only', () => {
  const match = requestStatus.match(/CLIENT_REQUEST_STATUS_FILTERS[^=]*=\s*\[([^\]]+)\]/s);
  assert.ok(match);
  assert.match(match[1], /DRAFT/);
  assert.match(match[1], /CHANGES_REQUESTED/);
  assert.match(match[1], /CONVERTED/);
  assert.doesNotMatch(match[1], /dispatcher|ops|engine/i);
});

check('listClientShipmentRequests does not strip allowlisted status via stripTenancy', () => {
  const fnStart = requestsApi.indexOf('export async function listClientShipmentRequests');
  const fnEnd = requestsApi.indexOf('export async function getClientShipmentRequest', fnStart);
  const body = requestsApi.slice(fnStart, fnEnd);
  assert.match(body, /params\.status/);
  assert.doesNotMatch(body, /stripTenancy\(query\)/);
});

console.log(`\nC5.4 client filtering: ${passed} passed, ${failed} failed`);
process.exit(failed > 0 ? 1 : 0);
