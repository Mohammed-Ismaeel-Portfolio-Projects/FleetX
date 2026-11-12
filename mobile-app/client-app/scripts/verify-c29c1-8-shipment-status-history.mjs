/**
 * C2.9-C1.8 Static Verification — Shipment Status Transition History
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

test('C1.8 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_8_SHIPMENT_STATUS_HISTORY.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.8/i);
});

test('shipment status history migration exists', () => {
  const mig = read('backend/database/migrations/2026_08_12_120000_create_shipment_status_history_table.php');
  assert.ok(mig);
  assert.match(mig, /shipment_status_history/);
});

test('ShipmentStatusHistory model exists', () => {
  const model = read('backend/app/Models/ShipmentStatusHistory.php');
  assert.ok(model);
  assert.match(model, /class ShipmentStatusHistory/);
  assert.match(model, /TransitionShipmentStatusAction/);
});

test('TransitionShipmentStatusAction creates history on success', () => {
  const action = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  assert.match(action, /ShipmentStatusHistory::query\(\)->create/);
  assert.match(action, /from_status_id/);
  assert.match(action, /to_status_id/);
  assert.match(action, /changed_by_user_id/);
});

test('Shipment model has statusHistory relationship', () => {
  const model = read('backend/app/Models/Shipment.php');
  assert.match(model, /statusHistory/);
  assert.match(model, /latestStatusHistory/);
});

test('C1.8 history tests exist', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentStatusHistoryTest.php');
  assert.ok(testFile);
  assert.match(testFile, /creates_one_history_row/i);
  assert.match(testFile, /rejected.*no_history|creates_no_history/i);
  assert.match(testFile, /atomic|rollback/i);
});

test('C1.7 transitions remain in map unchanged', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /IN_TRANSIT.*DELIVERED/s);
  assert.match(map, /ASSIGNED.*IN_TRANSIT/s);
  assert.match(map, /WAITING_ASSIGNMENT.*ASSIGNED/s);
});

test('C1.3 Assignment still uses transition mechanism', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
});

test('C1.5 update path still routes through transition action', () => {
  const action = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(action, /transitionAction->execute/);
});

test('no cancellation or unrelated lifecycle transitions introduced', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.equal(/CANCELLED|Cancelled/.test(map), false);
  assert.match(map, /DELIVERED.*COMPLETED/s);
});

test('no UI tracking or assignment redesign', () => {
  const history = read('backend/app/Models/ShipmentStatusHistory.php');
  assert.equal(/Notification::|tracking|gps/i.test(history), false);
  const assignment = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.equal(/DELIVERED|completeAssignment/i.test(assignment), false);
});
