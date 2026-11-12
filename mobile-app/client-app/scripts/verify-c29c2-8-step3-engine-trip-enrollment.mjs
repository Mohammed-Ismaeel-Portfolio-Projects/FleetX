/**
 * C2.9-C2.8 Step 3 Static Verification — Engine Trip Enrollment
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

test('Step 3 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP3_ENGINE_TRIP_ENROLLMENT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.8 Step 3/i);
  assert.match(doc, /Enrollment|enroll/i);
  assert.match(doc, /HARD STOP/i);
});

test('enrollment action and status constants exist', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Actions/Assignment/EnrollAssignmentInEngineAction.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Support/EngineEnrollmentStatus.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Enrollment/SimulationEligibleAssignments.php')));
  const action = read('backend/app/Actions/Assignment/EnrollAssignmentInEngineAction.php');
  assert.match(action, /lockForUpdate/);
  assert.match(action, /already enrolled/i);
});

test('runtime modules use SimulationEligibleAssignments not bare active()', () => {
  const modules = [
    'backend/app/Simulation/Modules/VehicleStateMachineModule.php',
    'backend/app/Simulation/Modules/RouteProgressModule.php',
    'backend/app/Simulation/Modules/ShipmentLifecycleModule.php',
    'backend/app/Simulation/Modules/TelemetryModule.php',
    'backend/app/Services/TrackingSimulator/TrackingSimulatorService.php',
  ];
  for (const rel of modules) {
    const src = read(rel);
    assert.ok(src, `missing ${rel}`);
    assert.match(src, /SimulationEligibleAssignments/);
    assert.equal(/Assignment::active\(\)/.test(src), false, rel);
  }
});

test('Step 2 adapter remains canonical status boundary', () => {
  const adapter = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php');
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(adapter, /TransitionShipmentStatusAction/);
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
});

test('enrollment route registered', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /engine-enroll/);
});

test('migration adds enrollment columns', () => {
  const mig = read(
    'backend/database/migrations/2026_08_13_010000_add_engine_enrollment_to_assignments_table.php'
  );
  assert.ok(mig);
  assert.match(mig, /engine_enrollment_status/);
  assert.match(mig, /engine_enrolled_at/);
});

test('focused enrollment tests exist', () => {
  const tests = read('backend/tests/Feature/Assignment/EnrollAssignmentInEngineActionTest.php');
  assert.ok(tests);
  assert.match(tests, /idempotent/i);
  assert.match(tests, /does_not_advance_shipment_status/);
});
