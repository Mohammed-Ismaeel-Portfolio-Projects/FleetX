/**
 * C2.9-C2.8 Step 1 Static Verification — Engine-Driven Operations Audit
 * Audit-only: confirms inventory + docs; no production code required for this step.
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

test('C2.8 Step 1 audit documentation exists and is audit-only', () => {
  const doc = read('docs/C2.9C2_8_ENGINE_DRIVEN_OPERATIONS_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.8/i);
  assert.match(doc, /AUDIT ONLY|Step 1/i);
  assert.match(doc, /Production code changed \(Step 1\).*NONE|Production code changed.*NONE/i);
  assert.match(doc, /HARD STOP/i);
});

test('SimulationRuntime wires lifecycle and tracking modules', () => {
  const runtime = read('backend/app/Simulation/Runtime/SimulationRuntime.php');
  assert.match(runtime, /ShipmentLifecycleModule/);
  assert.match(runtime, /TrackingSimulationModule/);
  assert.match(runtime, /RouteProgressModule/);
});

test('ShipmentLifecycleEngine routes canonical writes through adapter (Step 2)', () => {
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(engine, /ShipmentLifecycleTransitionAdapter|transitionAdapter/);
  assert.match(engine, /TransitionShipmentStatusAction|does not mutate status_id/i);
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
});

test('canonical transition map remains five-state ops path', () => {
  const map = read('backend/app/Support/ShipmentTransitionMap.php');
  assert.match(map, /WAITING_ASSIGNMENT/);
  assert.match(map, /ASSIGNED/);
  assert.match(map, /IN_TRANSIT/);
  assert.match(map, /DELIVERED/);
  assert.match(map, /COMPLETED/);
});

test('operational stages exist separately from canonical statuses', () => {
  const stages = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleStage.php');
  assert.match(stages, /EnRoutePickup/);
  assert.match(stages, /Loading/);
  assert.match(stages, /Unloading/);
  assert.match(stages, /toDatabaseStatusName/);
});

test('simulation config exposes arrival and loading thresholds', () => {
  const config = read('backend/config/simulation.php');
  assert.match(config, /arrival_distance_m/);
  assert.match(config, /loading_duration_seconds/);
  assert.match(config, /unloading_duration_seconds/);
});

test('CreateAssignmentAction still uses canonical transition action', () => {
  const action = read('backend/app/Actions/Assignment/CreateAssignmentAction.php');
  assert.match(action, /TransitionShipmentStatusAction/);
});

test('Dispatcher Active Tracking consumes live-fleet API', () => {
  const service = read('dashboard-web/src/features/dispatcher/tracking/services/tracking.service.ts');
  assert.match(service, /live-fleet|liveFleet|\/tracking\/live-fleet/);
});

test('C2.7 operational detail remains present', () => {
  assert.ok(
    existsSync(
      resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx')
    )
  );
  const doc = read('docs/C2.9C2_7_SHIPMENT_OPERATIONAL_DETAIL.md');
  assert.ok(doc);
});
