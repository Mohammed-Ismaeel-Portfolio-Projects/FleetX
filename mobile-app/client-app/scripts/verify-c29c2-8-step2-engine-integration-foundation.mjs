/**
 * C2.9-C2.8 Step 2 Static Verification — Engine Integration Foundation
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

test('Step 2 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP2_ENGINE_INTEGRATION_FOUNDATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.8 Step 2/i);
  assert.match(doc, /ShipmentLifecycleTransitionAdapter/);
  assert.match(doc, /HARD STOP/i);
});

test('adapter exists and uses TransitionShipmentStatusAction', () => {
  const adapter = read(
    'backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php'
  );
  assert.ok(adapter);
  assert.match(adapter, /TransitionShipmentStatusAction/);
  assert.match(adapter, /ShipmentTransitionMap/);
  assert.equal(/->update\(\s*\[\s*'status_id'/i.test(adapter), false);
});

test('ShipmentLifecycleEngine no longer defines syncDatabaseStatus', () => {
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(engine, /transitionAdapter|ShipmentLifecycleTransitionAdapter/);
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
  assert.equal(/->update\(\s*\[\s*'status_id'|\$updates\s*=\s*\[\s*'status_id'/i.test(engine), false);
});

test('canonical five-state map unchanged', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT/);
  assert.match(map, /ASSIGNED/);
  assert.match(map, /IN_TRANSIT/);
  assert.match(map, /DELIVERED/);
  assert.match(map, /COMPLETED/);
});

test('CreateAssignmentAction still uses transition action', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
});

test('focused adapter tests exist', () => {
  assert.ok(
    existsSync(
      resolve(ROOT, 'backend/tests/Feature/Simulation/ShipmentLifecycleTransitionAdapterTest.php')
    )
  );
  const tests = read('backend/tests/Feature/Simulation/ShipmentLifecycleTransitionAdapterTest.php');
  assert.match(tests, /idempotent/i);
  assert.match(tests, /waiting_assignment_to_delivered/i);
});

test('toDatabaseStatusName is deprecated for write path', () => {
  const stages = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleStage.php');
  assert.match(stages, /@deprecated/);
  assert.match(stages, /must NOT be used to write/i);
});
