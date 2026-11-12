/**
 * C2.9-B2.1 Static Verification — Dispatcher Shipment Request Read API
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

test('implementation doc exists', () => {
  const doc = read('docs/C2.9B2_1_DISPATCHER_SHIPMENT_REQUEST_READ_API_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B2\.1/i);
});

test('ops read routes exist with correct names', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /ShipmentRequestController/);
  assert.match(routes, /v1\.shipment-requests\.index/);
  assert.match(routes, /v1\.shipment-requests\.show/);
});

test('lifecycle mutation routes are not introduced beyond review transitions', () => {
  const routes = read('backend/routes/api.php') || '';
  const opsRoutes = routes
    .split('\n')
    .filter((line) => /\[ShipmentRequestController::class/.test(line))
    .join('\n');
  assert.equal(opsRoutes.includes('/convert'), false);
  assert.equal(opsRoutes.includes('/cancel'), false);
  assert.equal(opsRoutes.includes('/resubmit'), false);
  assert.match(opsRoutes, /start-review/);
  assert.match(opsRoutes, /request-changes/);
  assert.match(opsRoutes, /\/approve/);
  assert.match(opsRoutes, /\/reject/);
});

test('shipmentOrder gates exist and client gates remain separate', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /shipmentOrder\.viewAny/);
  assert.match(provider, /shipmentOrder\.view/);
  assert.match(provider, /client\.shipmentRequest\.viewAny/);
  assert.match(provider, /client\.shipmentRequest\.view/);
});

test('ops list and show actions exist without forCustomer scoping', () => {
  const list = read('backend/app/Actions/ShipmentOrder/QueryShipmentRequestsAction.php');
  const show = read('backend/app/Actions/ShipmentOrder/GetShipmentRequestAction.php');
  assert.ok(list);
  assert.ok(show);
  assert.equal(list.includes('forCustomer'), false);
  assert.equal(show.includes('forCustomer'), false);
  assert.match(list, /customer_company_id/);
  assert.match(show, /statusHistory/);
});

test('dispatcher resources exist separately from client resources', () => {
  assert.ok(read('backend/app/Http/Resources/ShipmentOrder/ShipmentRequestListResource.php'));
  assert.ok(read('backend/app/Http/Resources/ShipmentOrder/ShipmentRequestShowResource.php'));
  const show = read('backend/app/Http/Resources/ShipmentOrder/ShipmentRequestShowResource.php');
  assert.match(show, /submission_snapshot/);
  assert.match(show, /status_history/);
  const clientShow = read('backend/app/Http/Resources/Client/ClientShipmentRequestShowResource.php');
  assert.equal(clientShow.includes('submission_snapshot'), false);
  assert.equal(clientShow.includes('payload_json'), false);
});

test('client routes remain intact', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /v1\.client\.shipment-requests\.index/);
  assert.match(routes, /v1\.client\.shipment-requests\.submit/);
  assert.match(routes, /v1\.client\.shipment-requests\.resubmit/);
});

test('dispatcher feature tests exist', () => {
  assert.ok(read('backend/tests/Feature/ShipmentOrder/DispatcherShipmentRequestListTest.php'));
  assert.ok(read('backend/tests/Feature/ShipmentOrder/DispatcherShipmentRequestShowTest.php'));
});

test('no conversion or assignment changes introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')),
    false
  );
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.equal(provider.includes("Gate::define('assignment."), false);
});

test('scope limited to read list and show', () => {
  const doc = read('docs/C2.9B2_1_DISPATCHER_SHIPMENT_REQUEST_READ_API_IMPLEMENTATION.md');
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /start-review|approve|convert/i);
});
