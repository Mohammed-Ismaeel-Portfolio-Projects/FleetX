/**
 * C2.9-C1.5 Static Verification — Shipment Update Status Enforcement
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

test('C1.5 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_5_SHIPMENT_UPDATE_STATUS_ENFORCEMENT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.5/i);
});

test('UpdateShipmentAction routes status changes through transition action', () => {
  const action = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.ok(action);
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /transitionAction->execute/);
  assert.match(action, /unset\(\$changedData\['status_id'\]\)/);
  assert.equal(/status_id.*ShipmentStatus::statusId/.test(action), false);
});

test('Waiting Assignment to Assigned remains supported via update path', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT/);
  assert.match(map, /ASSIGNED/);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentUpdateStatusEnforcementTest.php');
  assert.match(testFile, /waiting_assignment_to_assigned|Waiting Assignment/i);
});

test('unsupported transitions remain rejected in update tests', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentUpdateStatusEnforcementTest.php');
  assert.match(testFile, /unsupported|rejected/i);
  assert.match(testFile, /IN_TRANSIT|In Transit/);
});

test('normal shipment updates without status remain available', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentUpdateStatusEnforcementTest.php');
  assert.match(testFile, /without_status_id|without status/i);
});

test('C1.3 CreateAssignmentAction still uses transition mechanism', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /transitionShipmentStatusAction->execute/);
  assert.equal(/status_id.*ShipmentStatus::statusId\(ShipmentStatus::ASSIGNED\)/.test(action), false);
});

test('C1.4 ChangeShipmentStatusAction still delegates to transition action', () => {
  const action = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /transitionAction->execute/);
});

test('C1.5 transition boundary preserved with update enforcement', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /canTransition/);
  assert.match(map, /WAITING_ASSIGNMENT/);
});

test('no forbidden UI tracking notification or auto-assignment', () => {
  const action = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.equal(/auto.?assign|AutoAssign/i.test(action), false);
  assert.equal(/Notification::/i.test(action), false);
});

test('C1.2 assignment gates remain defined', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('assignment\.create'/);
});
