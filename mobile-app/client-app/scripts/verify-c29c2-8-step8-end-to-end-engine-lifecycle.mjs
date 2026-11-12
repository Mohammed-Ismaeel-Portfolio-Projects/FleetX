/**
 * C2.9-C2.8 Step 8 Static Verification — End-to-End Engine Lifecycle
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

test('Step 8 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP8_END_TO_END_ENGINE_LIFECYCLE.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.8 Step 8/i);
  assert.match(doc, /HARD STOP/i);
  assert.match(doc, /Delivered.*Completed|Completed.*Delivered/s);
});

test('Step 8 E2E integration test exists', () => {
  assert.ok(
    existsSync(
      resolve(ROOT, 'backend/tests/Feature/Simulation/EngineEndToEndLifecycleIntegrationTest.php')
    )
  );
  const testFile = read('backend/tests/Feature/Simulation/EngineEndToEndLifecycleIntegrationTest.php');
  assert.match(testFile, /test_full_lifecycle_enrollment_through_completion_with_rest/);
  assert.match(testFile, /ShipmentStatusHistory/);
  assert.match(testFile, /EnrollAssignmentInEngineAction/);
});

test('canonical status writes remain centralized via adapter + action', () => {
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  const adapter = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php');
  assert.match(adapter, /TransitionShipmentStatusAction/);
  assert.match(engine, /transitionAdapter->applyOperationalStage/);
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
  assert.equal(/->update\(\s*\[\s*'status_id'|\$updates\s*=\s*\[\s*'status_id'/i.test(engine), false);
});

test('Delivered and Completed map through adapter; rest remains ops-only', () => {
  const adapter = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php');
  assert.match(adapter, /ShipmentLifecycleStage::Delivered.*ShipmentStatus::DELIVERED/s);
  assert.match(adapter, /ShipmentLifecycleStage::Completed.*ShipmentStatus::COMPLETED/s);
  assert.match(adapter, /rest.*ops-only|default => null/s);
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(engine, /ApproachingRestStop|Resting/);
});

test('enrollment remains the runtime gate; single engine-enroll route', () => {
  const module = read('backend/app/Simulation/Modules/ShipmentLifecycleModule.php');
  assert.match(module, /SimulationEligibleAssignments/);
  assert.equal(/Assignment::active\(\)/.test(module), false);
  const routes = read('backend/routes/api.php');
  assert.equal((routes.match(/Route::post\([^)]*engine-enroll/g) || []).length, 1);
  const enroll = read('backend/app/Actions/Assignment/EnrollAssignmentInEngineAction.php');
  assert.equal(/TransitionShipmentStatusAction|status_id/.test(enroll), false);
});

test('geofence and rest architecture intact from Steps 4–5', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Lifecycle/GeofenceProximityDetector.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Journey/JourneyRestEngine.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Journey/RestStopPlanner.php')));
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(engine, /GeofenceProximityDetector/);
  assert.match(engine, /JourneyRestEngine/);
  assert.match(engine, /ApproachingRestStop|Resting/);
});

test('Dispatcher remains observe/enroll — no manual lifecycle controls', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx'
  );
  assert.ok(panel);
  assert.match(panel, /EngineEnrollmentControls/);
  assert.equal(/Mark In Transit|Mark Delivered|Mark Completed/i.test(panel), false);
  const detail = read('dashboard-web/src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx');
  assert.equal(/Mark In Transit|Mark Delivered|Mark Completed|updateStatus/i.test(detail || ''), false);
});

test('Steps 2–7 foundation files still present', () => {
  const required = [
    'backend/app/Simulation/Runtime/SimulationRuntime.php',
    'backend/app/Simulation/Progress/RouteProgressEngine.php',
    'backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php',
    'backend/app/Actions/Shipment/TransitionShipmentStatusAction.php',
    'backend/app/Support/ShipmentTransitionMap.php',
    'backend/app/Support/EngineEnrollmentEligibility.php',
    'backend/app/Actions/Shipment/BuildShipmentEngineOpsReadModelAction.php',
  ];
  for (const rel of required) {
    assert.ok(existsSync(resolve(ROOT, rel)), `missing ${rel}`);
  }
});

test('Delivered stage advances to Completed on subsequent tick (observable milestone)', () => {
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(engine, /case ShipmentLifecycleStage::Delivered:/);
  assert.match(engine, /ShipmentLifecycleStage::Completed/);
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /DELIVERED.*COMPLETED/s);
});
