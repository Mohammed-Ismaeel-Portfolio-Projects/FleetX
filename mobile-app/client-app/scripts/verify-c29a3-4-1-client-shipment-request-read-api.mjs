/**
 * C2.9-A3.4.1 Static Verification — Client Shipment Request Read API
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

test('read API implementation doc exists', () => {
  const doc = read('docs/C2.9A3_4_1_CLIENT_SHIPMENT_REQUEST_READ_API_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-A3\.4\.1/i);
});

test('ClientShipmentRequestController exists with index show store and update', () => {
  const controller = read('backend/app/Http/Controllers/Client/ClientShipmentRequestController.php');
  assert.ok(controller);
  assert.match(controller, /function index/);
  assert.match(controller, /function show/);
  assert.match(controller, /function store/);
  assert.match(controller, /function update/);
});

test('read routes exist with correct names', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /shipment-requests/);
  assert.match(routes, /ClientShipmentRequestController/);
  assert.match(routes, /v1\.client\.shipment-requests\.index/);
  assert.match(routes, /v1\.client\.shipment-requests\.show/);
});

test('write routes beyond create draft update submit and resubmit are not introduced', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.equal(/Route::delete\([^)]*shipment-requests/i.test(routes), false);
});

test('client read actions exist', () => {
  assert.ok(read('backend/app/Actions/ShipmentOrder/QueryClientShipmentRequestsAction.php'));
  assert.ok(read('backend/app/Actions/ShipmentOrder/GetClientShipmentRequestAction.php'));
});

test('client resources exist', () => {
  assert.ok(read('backend/app/Http/Resources/Client/ClientShipmentRequestListResource.php'));
  assert.ok(read('backend/app/Http/Resources/Client/ClientShipmentRequestShowResource.php'));
});

test('client shipment request gates exist', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\.viewAny/);
  assert.match(provider, /client\.shipmentRequest\.view/);
});

test('forCustomer scoping used in actions', () => {
  const list = read('backend/app/Actions/ShipmentOrder/QueryClientShipmentRequestsAction.php');
  const show = read('backend/app/Actions/ShipmentOrder/GetClientShipmentRequestAction.php');
  assert.match(list, /forCustomer/);
  assert.match(show, /forCustomer/);
});

test('ownership probe parameters prohibited in form requests', () => {
  const listReq = read('backend/app/Http/Requests/Client/QueryClientShipmentRequestsRequest.php');
  const showReq = read('backend/app/Http/Requests/Client/ShowClientShipmentRequestRequest.php');
  assert.match(listReq, /customer_company_id.*prohibited/);
  assert.match(showReq, /company_id.*prohibited/);
  assert.match(showReq, /submitted_by_user_id.*prohibited/);
});

test('feature tests exist', () => {
  assert.ok(read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestListTest.php'));
  assert.ok(read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestShowTest.php'));
});

test('resources exclude sensitive fields', () => {
  const list = read('backend/app/Http/Resources/Client/ClientShipmentRequestListResource.php');
  const show = read('backend/app/Http/Resources/Client/ClientShipmentRequestShowResource.php');
  assert.equal(list.includes('payload_json'), false);
  assert.equal(list.includes('converted_shipment_id'), false);
  assert.equal(list.includes('dispatcher_notes'), false);
  assert.equal(show.includes('payload_json'), false);
  assert.equal(show.includes('converted_shipment_id'), false);
  assert.equal(show.includes('dispatcher_notes'), false);
  assert.equal(show.includes('submitted_by_user_id'), false);
});

test('client read API does not invoke conversion', () => {
  const list = read('backend/app/Http/Resources/Client/ClientShipmentRequestListResource.php') || '';
  const show = read('backend/app/Http/Resources/Client/ClientShipmentRequestShowResource.php') || '';
  assert.equal(list.includes('ConvertShipmentOrder'), false);
  assert.equal(show.includes('ConvertShipmentOrder'), false);
});

test('client shipment request UI exists without conversion controls', () => {
  const form = read('mobile-app/client-app/src/app/request-form.tsx') || '';
  assert.ok(form.length > 0, 'Client request form expected after SHIPMENT-REQUEST phases');
  assert.equal(/convertToShipment|ConvertShipment/i.test(form), false);
});

test('scope limited to read list and show', () => {
  const doc = read('docs/C2.9A3_4_1_CLIENT_SHIPMENT_REQUEST_READ_API_IMPLEMENTATION.md');
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /submit|resubmit|POST create/i);
});
