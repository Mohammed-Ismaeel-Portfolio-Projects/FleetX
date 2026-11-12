/**
 * C2.9-C1.10 Static Verification — Delivered → Completed Transition
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

test('C1.10 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_10_DELIVERED_TO_COMPLETED.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.10/i);
});

test('Delivered to Completed exists in ShipmentTransitionMap', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /ShipmentStatus::DELIVERED => \[ShipmentStatus::COMPLETED\]/);
  assert.match(map, /WAITING_ASSIGNMENT.*ASSIGNED/s);
  assert.match(map, /ASSIGNED.*IN_TRANSIT/s);
  assert.match(map, /IN_TRANSIT.*DELIVERED/s);
});

test('unsupported transitions remain rejected in C1.10 tests', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php');
  assert.match(testFile, /waiting_assignment_to_completed/i);
  assert.match(testFile, /assigned_to_completed/i);
  assert.match(testFile, /in_transit_to_completed/i);
  assert.match(testFile, /completed_to_delivered/i);
  assert.match(testFile, /completed_to_assigned/i);
});

test('status history preserved via transition action', () => {
  const action = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  const testFile = read('backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php');
  assert.match(action, /ShipmentStatusHistory::query\(\)->create/);
  assert.match(testFile, /creates_one_history_row/i);
});

test('both mutation paths support Delivered to Completed', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php');
  assert.match(testFile, /status_endpoint/i);
  assert.match(testFile, /update_endpoint/i);
  const change = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  const update = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(change, /TransitionShipmentStatusAction/);
  assert.match(update, /transitionAction->execute/);
});

test('authorization unchanged', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('shipment\.change-status'/);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php');
  assert.match(testFile, /client.*denied|client_is_denied/i);
  assert.match(testFile, /unauthenticated/i);
});

test('C1.3 Assignment integration unchanged', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.equal(/COMPLETED|Completed/.test(action), false);
});

test('no unrelated lifecycle transitions introduced', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.equal(/CANCELLED|Cancelled/.test(map), false);
  assert.equal(/COMPLETED.*DELIVERED|Completed.*In Transit/i.test(map), false);
});

test('no new API endpoint introduced', () => {
  const routes = read('backend/routes/api.php');
  assert.equal(/Route::.*delivered-to-completed|to-completed/i.test(routes), false);
});

test('C1.10 focused tests exist', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php');
  assert.ok(testFile);
  assert.match(testFile, /delivered_to_completed/i);
});
