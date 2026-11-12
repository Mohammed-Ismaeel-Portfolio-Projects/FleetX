/**
 * C2.9-C1.4 Static Verification — Shipment Status Transition Rules
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

test('C1.4 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_4_SHIPMENT_STATUS_TRANSITION_RULES.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.4/i);
});

test('Shipment transition boundary exists', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Support/ShipmentTransitionMap.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Actions/Shipment/TransitionShipmentStatusAction.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Exceptions/InvalidShipmentTransitionException.php')));
});

test('Waiting Assignment to Assigned is supported', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT/);
  assert.match(map, /ASSIGNED/);
  assert.match(map, /canTransition/);
});

test('ChangeShipmentStatusAction delegates to transition action', () => {
  const action = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /transitionAction->execute/);
});

test('C1.3 CreateAssignmentAction uses transition mechanism', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /transitionShipmentStatusAction->execute/);
  assert.equal(/status_id.*ShipmentStatus::statusId\(ShipmentStatus::ASSIGNED\)/.test(action), false);
});

test('C1.4 transition tests exist', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentStatusTransitionRulesTest.php');
  assert.ok(testFile);
  assert.match(testFile, /WAITING_ASSIGNMENT|Waiting Assignment/);
  assert.match(testFile, /unsupported.*transition|Unsupported/i);
});

test('C1.2 assignment gates remain defined', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('assignment\.create'/);
  assert.match(provider, /Gate::define\('assignment\.view'/);
});

test('no forbidden UI tracking notification or auto-assignment', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests/convert')),
    false
  );
  const transition = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  assert.equal(/auto.?assign|AutoAssign/i.test(transition), false);
  assert.equal(/Notification::/i.test(transition), false);
});

test('C1.4 Waiting Assignment to Assigned transition preserved with history writer', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT.*ASSIGNED/s);
  const transition = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  assert.match(transition, /ShipmentStatusHistory::query\(\)->create/);
});

test('conversion core unchanged', () => {
  const convert = read('backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php');
  assert.match(convert, /ShipmentOrderConversionMapper/);
  assert.equal(convert.includes('TransitionShipmentStatusAction'), false);
});
