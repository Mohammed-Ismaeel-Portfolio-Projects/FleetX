/**
 * C2.9-C1.9 Static Verification — Delivered Transition Readiness Audit
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

test('C1.9 audit doc exists', () => {
  const doc = read('docs/C2.9C1_9_DELIVERED_TRANSITION_READINESS_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.9/i);
  assert.match(doc, /Delivered → Completed/i);
  assert.match(doc, /Production Changes/i);
});

test('C1.9 audit doc recorded Delivered as terminal at audit time', () => {
  const doc = read('docs/C2.9C1_9_DELIVERED_TRANSITION_READINESS_AUDIT.md');
  assert.match(doc, /Delivered.*\(none|terminal/i);
  assert.match(doc, /Delivered → Completed/i);
});

test('C1.10 implements Delivered to Completed after C1.9 audit', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /DELIVERED.*COMPLETED/s);
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C1_10_DELIVERED_TO_COMPLETED.md')));
});

test('Completed status exists in canonical ShipmentStatus vocabulary', () => {
  const status = read('backend/app/Support/ShipmentStatus.php');
  assert.match(status, /COMPLETED = 'Completed'/);
  assert.match(status, /DELIVERED = 'Delivered'/);
});

test('no Delivered to Completed transition was introduced in transition action', () => {
  const action = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  assert.match(action, /ShipmentTransitionMap::canTransition/);
  assert.equal(/Delivered.*Completed|DELIVERED.*COMPLETED/.test(action), false);
});

test('C1.8 history behavior remains present', () => {
  const action = read('backend/app/Actions/Shipment/TransitionShipmentStatusAction.php');
  assert.match(action, /ShipmentStatusHistory::query\(\)->create/);
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Models/ShipmentStatusHistory.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/database/migrations/2026_08_12_120000_create_shipment_status_history_table.php')));
});

test('existing transition enforcement paths unchanged', () => {
  const change = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  const update = read('backend/app/Actions/Shipment/UpdateShipmentAction.php');
  assert.match(change, /TransitionShipmentStatusAction/);
  assert.match(update, /transitionAction->execute/);
});

test('Delivered outbound transitions to In Transit or Assigned remain rejected in C1.7 tests', () => {
  const testFile = read('backend/tests/Feature/Shipment/ShipmentInTransitToDeliveredTest.php');
  assert.match(testFile, /delivered_to_in_transit_is_rejected/i);
  assert.match(testFile, /delivered_to_assigned_is_rejected/i);
});

test('C1.10 implementation artifacts exist after readiness audit', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/tests/Feature/Shipment/ShipmentDeliveredToCompletedTest.php')));
  assert.ok(existsSync(resolve(ROOT, 'mobile-app/client-app/scripts/verify-c29c1-10-delivered-completed.mjs')));
});

test('C1.3 Assignment integration unchanged', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
  assert.equal(/COMPLETED|Completed/.test(action), false);
});

test('audit doc documents blockers and deferred work', () => {
  const doc = read('docs/C2.9C1_9_DELIVERED_TRANSITION_READINESS_AUDIT.md');
  assert.match(doc, /Blocker|blocker/i);
  assert.match(doc, /Deferred|deferred/i);
  assert.match(doc, /ShipmentService|legacy/i);
});
