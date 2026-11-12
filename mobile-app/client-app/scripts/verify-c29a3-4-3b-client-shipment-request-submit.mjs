/**
 * C2.9-A3.4.3b Static Verification — Client Shipment Request Submit
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

test('submit implementation doc exists', () => {
  const doc = read('docs/C2.9A3_4_3B_CLIENT_SHIPMENT_REQUEST_SUBMIT_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-A3\.4\.3b/i);
});

test('POST submit route exists with correct name', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /\/submit/);
  assert.match(routes, /v1\.client\.shipment-requests\.submit/);
});

test('ResubmitClientShipmentRequestAction is not part of submit phase', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ResubmitClientShipmentRequestAction.php')),
    true
  );
  const submitAction = read('backend/app/Actions/ShipmentOrder/SubmitClientShipmentRequestAction.php') || '';
  assert.equal(submitAction.includes('ResubmitClientShipmentRequestAction'), false);
});

test('SubmitClientShipmentRequestAction uses TransitionShipmentOrderStatusAction', () => {
  const action = read('backend/app/Actions/ShipmentOrder/SubmitClientShipmentRequestAction.php');
  assert.ok(action);
  assert.match(action, /class SubmitClientShipmentRequestAction/);
  assert.match(action, /TransitionShipmentOrderStatusAction/);
  assert.match(action, /forCustomer/);
  assert.match(action, /SUBMITTED/);
  assert.match(action, /submission_revision.*1|submission_revision' => 1/);
});

test('SubmitClientShipmentRequestRequest prohibits client lifecycle fields', () => {
  const req = read('backend/app/Http/Requests/Client/SubmitClientShipmentRequestRequest.php');
  assert.ok(req);
  assert.match(req, /payload_json.*prohibited/);
  assert.match(req, /submitted_by_user_id.*prohibited/);
  assert.match(req, /status_id.*prohibited/);
});

test('submission validator and payload snapshot exist', () => {
  assert.ok(read('backend/app/Support/ShipmentOrderSubmissionValidator.php'));
  assert.ok(read('backend/app/Support/ShipmentOrderPayloadSnapshotBuilder.php'));
});

test('client.shipmentRequest.submit gate exists', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\.submit/);
});

test('submit feature tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestSubmitTest.php');
  assert.ok(testFile);
  assert.match(testFile, /ClientShipmentRequestSubmitTest/);
  assert.match(testFile, /submission_persists_metadata_and_history/);
});

test('client submit does not invoke conversion', () => {
  const action = read('backend/app/Actions/ShipmentOrder/SubmitClientShipmentRequestAction.php') || '';
  assert.ok(action);
  assert.equal(action.includes('ConvertShipmentOrderToShipmentAction'), false);
  assert.equal(action.includes('ConvertShipmentOrder'), false);
});

test('client shipment request UI exists without conversion controls', () => {
  const form = read('mobile-app/client-app/src/app/request-form.tsx') || '';
  assert.ok(form.length > 0, 'Client request form expected after SHIPMENT-REQUEST phases');
  assert.equal(/convertToShipment|ConvertShipment/i.test(form), false);
});

test('scope limited to draft submit only', () => {
  const doc = read('docs/C2.9A3_4_3B_CLIENT_SHIPMENT_REQUEST_SUBMIT_IMPLEMENTATION.md');
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /resubmit/i);
});
