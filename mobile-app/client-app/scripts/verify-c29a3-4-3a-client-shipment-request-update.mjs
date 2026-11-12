/**
 * C2.9-A3.4.3a Static Verification — Client Shipment Request Draft Update
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

test('draft update implementation doc exists', () => {
  const doc = read('docs/C2.9A3_4_3A_CLIENT_SHIPMENT_REQUEST_DRAFT_UPDATE_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-A3\.4\.3a/i);
});

test('PATCH update route exists with correct name', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /Route::patch\([^)]*shipment-requests/);
  assert.match(routes, /v1\.client\.shipment-requests\.update/);
});

test('delete route is not introduced', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.equal(/Route::delete\([^)]*shipment-requests/i.test(routes), false);
});

test('UpdateClientShipmentRequestAction exists with editable status allowlist', () => {
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php');
  assert.ok(action);
  assert.match(action, /class UpdateClientShipmentRequestAction/);
  assert.match(action, /forCustomer/);
  assert.equal(action.includes('TransitionShipmentOrderStatusAction'), false);
  assert.match(action, /DRAFT/);
  assert.match(action, /CHANGES_REQUESTED/);
});

test('UpdateClientShipmentRequestRequest exists with prohibited fields', () => {
  const req = read('backend/app/Http/Requests/Client/UpdateClientShipmentRequestRequest.php');
  assert.ok(req);
  assert.match(req, /customer_company_id.*prohibited/);
  assert.match(req, /status_id.*prohibited/);
  assert.match(req, /payload_json.*prohibited/);
  assert.match(req, /driver_id.*prohibited/);
});

test('client.shipmentRequest.update gate exists', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\.update/);
});

test('controller has update method using show resource', () => {
  const controller = read('backend/app/Http/Controllers/Client/ClientShipmentRequestController.php');
  assert.match(controller, /function update/);
  assert.match(controller, /client\.shipmentRequest\.update/);
  assert.match(controller, /ClientShipmentRequestShowResource/);
});

test('update feature tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestUpdateTest.php');
  assert.ok(testFile);
  assert.match(testFile, /ClientShipmentRequestUpdateTest/);
  assert.match(testFile, /no_status_history_row_is_created/);
  assert.match(testFile, /submitted_request_cannot_be_updated/);
});

test('client draft update does not invoke conversion', () => {
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php') || '';
  assert.ok(action);
  assert.equal(action.includes('ConvertShipmentOrderToShipmentAction'), false);
  assert.equal(action.includes('ConvertShipmentOrder'), false);
});

test('client shipment request UI exists without conversion controls', () => {
  const form = read('mobile-app/client-app/src/app/request-form.tsx') || '';
  assert.ok(form.length > 0, 'Client request form expected after SHIPMENT-REQUEST phases');
  assert.equal(/convertToShipment|ConvertShipment/i.test(form), false);
});

test('scope covers draft and changes_requested client PATCH', () => {
  const a3Doc = read('docs/C2.9A3_4_3A_CLIENT_SHIPMENT_REQUEST_DRAFT_UPDATE_IMPLEMENTATION.md');
  const b23Doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_IMPLEMENTATION.md');
  assert.ok(a3Doc);
  assert.ok(b23Doc);
  assert.match(b23Doc, /changes_requested/i);
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php');
  assert.match(action, /CHANGES_REQUESTED/);
});
