/**
 * C2.9-A3.4.2 Static Verification — Client Shipment Request Create Draft
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

test('create draft implementation doc exists', () => {
  const doc = read('docs/C2.9A3_4_2_CLIENT_SHIPMENT_REQUEST_CREATE_DRAFT_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-A3\.4\.2/i);
});

test('POST create route exists with correct name', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /Route::post\([^)]*shipment-requests/);
  assert.match(routes, /v1\.client\.shipment-requests\.store/);
});

test('submit resubmit delete routes are not introduced beyond create', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.equal(/Route::delete\([^)]*shipment-requests/i.test(routes), false);
});

test('CreateClientShipmentRequestAction exists', () => {
  const action = read('backend/app/Actions/ShipmentOrder/CreateClientShipmentRequestAction.php');
  assert.ok(action);
  assert.match(action, /class CreateClientShipmentRequestAction/);
  assert.equal(action.includes('TransitionShipmentOrderStatusAction'), false);
});

test('CreateClientShipmentRequestRequest exists with prohibited fields', () => {
  const req = read('backend/app/Http/Requests/Client/CreateClientShipmentRequestRequest.php');
  assert.ok(req);
  assert.match(req, /customer_company_id.*prohibited/);
  assert.match(req, /status_id.*prohibited/);
  assert.match(req, /request_number.*prohibited/);
  assert.match(req, /payload_json.*prohibited/);
});

test('request number generator exists', () => {
  const gen = read('backend/app/Support/ShipmentOrderRequestNumberGenerator.php');
  assert.ok(gen);
  assert.match(gen, /REQ-/);
  assert.match(gen, /lockForUpdate/);
});

test('client.shipmentRequest.create gate exists', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\.create/);
});

test('controller has store method', () => {
  const controller = read('backend/app/Http/Controllers/Client/ClientShipmentRequestController.php');
  assert.match(controller, /function store/);
  assert.match(controller, /client\.shipmentRequest\.create/);
});

test('create feature tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestCreateTest.php');
  assert.ok(testFile);
  assert.match(testFile, /ClientShipmentRequestCreateTest/);
  assert.match(testFile, /no_status_history_row_is_created/);
});

test('client draft create does not invoke conversion', () => {
  const action = read('backend/app/Actions/ShipmentOrder/CreateClientShipmentRequestAction.php') || '';
  assert.ok(action);
  assert.equal(action.includes('ConvertShipmentOrderToShipmentAction'), false);
  assert.equal(action.includes('ConvertShipmentOrder'), false);
});

test('client shipment request UI exists without conversion controls', () => {
  const form = read('mobile-app/client-app/src/app/request-form.tsx') || '';
  assert.ok(form.length > 0, 'Client request form expected after SHIPMENT-REQUEST phases');
  assert.equal(/convertToShipment|ConvertShipment/i.test(form), false);
});

test('scope limited to draft create only', () => {
  const doc = read('docs/C2.9A3_4_2_CLIENT_SHIPMENT_REQUEST_CREATE_DRAFT_IMPLEMENTATION.md');
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /submit|resubmit|PATCH/i);
});
