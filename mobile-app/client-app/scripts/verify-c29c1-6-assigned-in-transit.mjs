/**
 * C2.9-C1.6 Static Verification — Assigned → In Transit Transition
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

test('C1.6 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_6_ASSIGNED_TO_IN_TRANSIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.6/i);
});

test('Assigned to In Transit exists in ShipmentTransitionMap', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /ShipmentStatus::ASSIGNED/);
  assert.match(map, /ShipmentStatus::IN_TRANSIT/);
  assert.match(map, /WAITING_ASSIGNMENT.*ASSIGNED/s);
});

test('unsupported transitions remain rejected in C1.6 tests', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentAssignedToInTransitTest.php');
  assert.match(testFile, /waiting_assignment_to_in_transit|Waiting Assignment/i);
  assert.match(testFile, /in_transit_to_assigned/i);
});

test('canonical TransitionShipmentStatusAction remains the writer', () => {
  const change = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  const update = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(change, /TransitionShipmentStatusAction/);
  assert.match(update, /TransitionShipmentStatusAction/);
  assert.equal(existsSync(resolve(ROOT, 'backend/app/Actions/Shipment/TransitionShipmentStatusAction.php')), true);
});

test('C1.3 Assignment integration unchanged', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /ShipmentStatus::ASSIGNED/);
  assert.equal(/IN_TRANSIT/.test(action), false);
});

test('C1.5 UpdateShipmentAction still routes status through transition', () => {
  const action = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(action, /transitionAction->execute/);
  assert.match(action, /unset\(\$changedData\['status_id'\]\)/);
});

test('no new shipment status API endpoint introduced', () => {
  const routes = read('backend/routes/api.php');
  const statusRouteCount = (routes.match(/shipments.*status/g) || []).length;
  assert.ok(statusRouteCount >= 1);
  assert.equal(/Route::.*assigned-to-in-transit|in-transit/i.test(routes), false);
});

test('no UI or tracking implementation added', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-in-transit')),
    false
  );
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.equal(/tracking|gps|notification/i.test(map), false);
});

test('C1.6 Assigned to In Transit preserved after later phases', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /ShipmentStatus::ASSIGNED => \[ShipmentStatus::IN_TRANSIT\]/);
});

test('C1.6 focused tests exist', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentAssignedToInTransitTest.php');
  assert.ok(testFile);
  assert.match(testFile, /assigned_to_in_transit/i);
});
