/**
 * C2.9-C1.3 Static Verification — Assignment → Shipment Status Integration
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

test('C1.3 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_3_ASSIGNMENT_SHIPMENT_STATUS_INTEGRATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.3/i);
});

test('CreateAssignmentAction integrates Waiting Assignment to Assigned via transition', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.ok(action);
  assert.match(action, /advanceWaitingAssignmentShipment/);
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /ShipmentStatus::WAITING_ASSIGNMENT/);
  assert.match(action, /ShipmentStatus::ASSIGNED/);
});

test('integration uses canonical ShipmentStatus vocabulary', () => {
  const status = read('backend/app/Support/ShipmentStatus.php');
  assert.match(status, /WAITING_ASSIGNMENT = 'Waiting Assignment'/);
  assert.match(status, /ASSIGNED = 'Assigned'/);
});

test('C1.3 integration tests exist', () => {
  const testFile = read('backend/tests/Feature/Assignment/AssignmentShipmentStatusIntegrationTest.php');
  assert.ok(testFile);
  assert.match(testFile, /Waiting Assignment|WAITING_ASSIGNMENT/);
  assert.match(testFile, /Assigned|ASSIGNED/);
  assert.match(testFile, /rolls_back|rollback/i);
});

test('C1.2 assignment gates remain defined', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('assignment\.create'/);
  assert.match(provider, /Gate::define\('assignment\.view'/);
});

test('C1.3 uses Shipment transition boundary not direct status mutation', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Support/ShipmentTransitionMap.php')),
    true
  );
});

test('conversion core unchanged as separate path', () => {
  const convert = read('backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php');
  assert.match(convert, /ShipmentOrderConversionMapper/);
  assert.equal(convert.includes('advanceWaitingAssignmentShipment'), false);
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
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.equal(/auto.?assign|AutoAssign/i.test(action), false);
});
