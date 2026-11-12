/**
 * C2.9-C1.7 Static Verification — In Transit → Delivered Transition
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

test('C1.7 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_7_IN_TRANSIT_TO_DELIVERED.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.7/i);
});

test('In Transit to Delivered exists in ShipmentTransitionMap', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /ShipmentStatus::IN_TRANSIT/);
  assert.match(map, /ShipmentStatus::DELIVERED/);
  assert.match(map, /WAITING_ASSIGNMENT.*ASSIGNED/s);
  assert.match(map, /ASSIGNED.*IN_TRANSIT/s);
});

test('unsupported transitions remain rejected in C1.7 tests', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentInTransitToDeliveredTest.php');
  assert.match(testFile, /waiting_assignment_to_delivered|Waiting Assignment/i);
  assert.match(testFile, /assigned_to_delivered/i);
  assert.match(testFile, /delivered_to_in_transit/i);
  assert.match(testFile, /delivered_to_assigned/i);
});

test('canonical transition boundary still used', () => {
  const change = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  const update = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(change, /TransitionShipmentStatusAction/);
  assert.match(update, /TransitionShipmentStatusAction/);
});

test('C1.6 Assigned to In Transit remains in map', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /ShipmentStatus::ASSIGNED => \[ShipmentStatus::IN_TRANSIT\]/);
});

test('C1.5 UpdateShipmentAction still routes status through transition', () => {
  const action = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(action, /transitionAction->execute/);
  assert.match(action, /unset\(\$changedData\['status_id'\]\)/);
});

test('no new shipment status API endpoint introduced', () => {
  const routes = read('backend/routes/api.php');
  assert.equal(/Route::.*in-transit-to-delivered|to-delivered/i.test(routes), false);
});

test('no Assignment redesign introduced', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.equal(/DELIVERED|In Transit|IN_TRANSIT/.test(action), false);
});

test('no UI or tracking implementation added', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-delivered')),
    false
  );
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.equal(/tracking|gps|notification/i.test(map), false);
});

test('C1.7 scope transitions remain; no cancellation introduced', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /IN_TRANSIT.*DELIVERED/s);
  assert.equal(/CANCELLED|Cancelled/.test(map), false);
});

test('C1.7 focused tests exist', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentInTransitToDeliveredTest.php');
  assert.ok(testFile);
  assert.match(testFile, /in_transit_to_delivered/i);
});
