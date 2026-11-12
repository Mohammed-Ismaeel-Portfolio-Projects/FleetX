/**
 * C2.9-C1.11 Static Verification — Shipment Lifecycle Operational Readiness Audit
 * Audit-only phase: confirms C1.2–C1.10 foundation intact; no C1.11 production changes.
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

test('C1.11 audit doc exists and is audit-only', () => {
  const doc = read('docs/C2.9C1_11_SHIPMENT_LIFECYCLE_OPERATIONAL_READINESS_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.11/i);
  assert.match(doc, /AUDIT ONLY/i);
  assert.match(doc, /Production code changed: NONE|Production changes in C1\.11.*NONE/i);
});

test('C1.2 authorization gates remain intact', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('shipment\.change-status'/);
  assert.match(provider, /Gate::define\('assignment\.view'/);
  const authTest = read('backend/tests/Feature/Assignment/AssignmentAuthorizationTest.php');
  assert.ok(authTest);
});

test('C1.3 Assignment integration remains intact', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.match(action, /advanceWaitingAssignmentShipment/);
  const testFile = read('backend/tests/Feature/Assignment/AssignmentShipmentStatusIntegrationTest.php');
  assert.ok(testFile);
});

test('C1.4 transition rules remain intact', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT.*ASSIGNED/s);
  const change = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  assert.match(change, /TransitionShipmentStatusAction/);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentStatusTransitionRulesTest.php');
  assert.ok(testFile);
});

test('C1.5 update enforcement remains intact', () => {
  const update = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(update, /transitionAction->execute/);
  assert.match(update, /unset\(\$changedData\['status_id'\]\)/);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentUpdateStatusEnforcementTest.php');
  assert.ok(testFile);
});

test('C1.6 Assigned to In Transit remains intact', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /ASSIGNED.*IN_TRANSIT/s);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentAssignedToInTransitTest.php');
  assert.ok(testFile);
});

test('C1.7 In Transit to Delivered remains intact', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /IN_TRANSIT.*DELIVERED/s);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentInTransitToDeliveredTest.php');
  assert.ok(testFile);
});

test('C1.8 status history remains intact', () => {
  const action = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  assert.match(action, /ShipmentStatusHistory::query\(\)->create/);
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Models/ShipmentStatusHistory.php')));
  const testFile = read('backend/tests/Feature/Shipment/ShipmentStatusHistoryTest.php');
  assert.ok(testFile);
});

test('C1.10 Delivered to Completed remains intact', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /DELIVERED.*COMPLETED/s);
  const testFile = read('backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php');
  assert.ok(testFile);
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C1_10_DELIVERED_TO_COMPLETED.md')));
});

test('full canonical lifecycle chain present in transition map', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT => \[ShipmentStatus::ASSIGNED\]/);
  assert.match(map, /ASSIGNED => \[ShipmentStatus::IN_TRANSIT\]/);
  assert.match(map, /IN_TRANSIT => \[ShipmentStatus::DELIVERED\]/);
  assert.match(map, /DELIVERED => \[ShipmentStatus::COMPLETED\]/);
});

test('no production lifecycle changes introduced by C1.11', () => {
  const audit = read('docs/C2.9C1_11_SHIPMENT_LIFECYCLE_OPERATIONAL_READINESS_AUDIT.md');
  assert.match(audit, /Do NOT implement|no production/i);
  assert.equal(/CANCELLED.*=>|Cancelled.*transition/i.test(read('backend/app/Support/ShipmentTransitionMap.php')), false);
});

test('audit documents legacy bypasses and deferred work', () => {
  const doc = read('docs/C2.9C1_11_SHIPMENT_LIFECYCLE_OPERATIONAL_READINESS_AUDIT.md');
  assert.match(doc, /ShipmentService|legacy/i);
  assert.match(doc, /Deferred|deferred/i);
  assert.match(doc, /Assignment.*sync|synchronization/i);
});
