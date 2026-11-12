/**
 * C2.9-B2.2 Static Verification — Dispatcher Shipment Request Review Transitions
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

test('review transitions doc exists', () => {
  const doc = read('docs/C2.9B2_2_DISPATCHER_SHIPMENT_REQUEST_REVIEW_TRANSITIONS.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B2\.2/i);
});

test('review routes exist with correct names', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /start-review/);
  assert.match(routes, /request-changes/);
  assert.match(routes, /v1\.shipment-requests\.approve/);
  assert.match(routes, /v1\.shipment-requests\.reject/);
});

test('review actions use TransitionShipmentOrderStatusAction', () => {
  for (const file of [
    'StartReviewShipmentOrderAction.php',
    'RequestChangesShipmentOrderAction.php',
    'ApproveShipmentOrderAction.php',
    'RejectShipmentOrderAction.php',
  ]) {
    const action = read(`backend/app/Actions/ShipmentOrder/${file}`);
    assert.ok(action);
    assert.match(action, /TransitionShipmentOrderStatusAction/);
    assert.equal(action.includes('forCustomer'), false);
  }
});

test('review gates exist', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /shipmentOrder\.startReview/);
  assert.match(provider, /shipmentOrder\.requestChanges/);
  assert.match(provider, /shipmentOrder\.approve/);
  assert.match(provider, /shipmentOrder\.reject/);
});

test('request changes and reject require reason in form requests', () => {
  const changes = read('backend/app/Http/Requests/ShipmentOrder/RequestChangesShipmentOrderRequest.php');
  const reject = read('backend/app/Http/Requests/ShipmentOrder/RejectShipmentOrderRequest.php');
  assert.match(changes, /reason.*required/);
  assert.match(reject, /reason.*required/);
});

test('no conversion route or action introduced', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.equal(routes.includes('/convert'), false);
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')),
    false
  );
});

test('review feature tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/DispatcherShipmentRequestReviewTransitionsTest.php');
  assert.ok(testFile);
  assert.match(testFile, /start_review_moves_submitted_to_under_review/);
  assert.match(testFile, /concurrent_start_review/);
});

test('client shipment request routes remain intact', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /v1\.client\.shipment-requests\.submit/);
  assert.match(routes, /ClientShipmentRequestController/);
});
