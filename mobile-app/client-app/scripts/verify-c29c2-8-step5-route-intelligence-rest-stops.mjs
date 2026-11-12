/**
 * C2.9-C2.8 Step 5 Static Verification — Route Intelligence & Rest Stops
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

test('Step 5 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP5_ROUTE_INTELLIGENCE_REST_STOPS.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.8 Step 5/i);
  assert.match(doc, /RestStopPlanner|JourneyRestEngine/);
  assert.match(doc, /HARD STOP/i);
});

test('route intelligence / rest-stop planning exists', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Journey/RestStopPlanner.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Journey/JourneyRestEngine.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Journey/JourneyPlan.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Journey/RestStopWaypoint.php')));
  const planner = read('backend/app/Simulation/Journey/RestStopPlanner.php');
  assert.match(planner, /maxDrivingDistanceM|max_driving_distance/);
});

test('config exposes rest policy (not hardcoded-only)', () => {
  const cfg = read('backend/config/simulation.php');
  assert.match(cfg, /max_driving_distance_m/);
  assert.match(cfg, /rest_duration_seconds/);
  assert.match(cfg, /geofence_radius_m/);
});

test('operational rest stages exist on lifecycle enum', () => {
  const stages = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleStage.php');
  assert.match(stages, /ApproachingRestStop/);
  assert.match(stages, /Resting/);
});

test('geofence waypoint helper exists and detector reused', () => {
  const geo = read('backend/app/Simulation/Lifecycle/GeofenceProximityDetector.php');
  assert.match(geo, /waypointReached/);
  const journey = read('backend/app/Simulation/Journey/JourneyRestEngine.php');
  assert.match(journey, /GeofenceProximityDetector/);
  assert.match(journey, /maxTravelledCapM/);
});

test('route progress clamp + rest hold wired in module', () => {
  const progress = read('backend/app/Simulation/Progress/RouteProgressEngine.php');
  assert.match(progress, /maxDistanceTravelledM/);
  const module = read('backend/app/Simulation/Modules/RouteProgressModule.php');
  assert.match(module, /JourneyRestEngine/);
  assert.match(module, /isResting|maxTravelledCapM/);
});

test('canonical adapter remains authoritative; rest stages ops-only', () => {
  const adapter = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php');
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.match(adapter, /TransitionShipmentStatusAction/);
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
  assert.equal(/->update\(\s*\[\s*'status_id'|\$updates\s*=\s*\[\s*'status_id'/i.test(engine), false);
  assert.equal(/ApproachingRestStop[\s\S]*IN_TRANSIT/.test(adapter) && /case ShipmentLifecycleStage::ApproachingRestStop/.test(adapter), false);
  assert.match(engine, /ApproachingRestStop|Resting/);
});

test('FSM rest context and enrollment boundary preserved', () => {
  const evalSrc = read(
    'backend/app/Simulation/Domain/Vehicle/Services/VehicleStateTransitionEvaluator.php'
  );
  assert.match(evalSrc, /is_resting/);
  const lifecycleMod = read('backend/app/Simulation/Modules/ShipmentLifecycleModule.php');
  assert.match(lifecycleMod, /SimulationEligibleAssignments/);
  assert.equal(/Assignment::active\(\)/.test(lifecycleMod), false);
});

test('Step 2–4 guarantees remain intact', () => {
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C2_8_STEP2_ENGINE_INTEGRATION_FOUNDATION.md')));
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C2_8_STEP3_ENGINE_TRIP_ENROLLMENT.md')));
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C2_8_STEP4_GEOFENCE_OPERATIONAL_STAGES.md')));
  assert.ok(
    existsSync(resolve(ROOT, 'backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php'))
  );
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Actions/Assignment/EnrollAssignmentInEngineAction.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Lifecycle/GeofenceProximityDetector.php')));
});

test('focused Step 5 tests exist', () => {
  assert.ok(
    existsSync(resolve(ROOT, 'backend/tests/Unit/Simulation/RestStopPlannerAndJourneyRestEngineTest.php'))
  );
  assert.ok(
    existsSync(
      resolve(ROOT, 'backend/tests/Unit/Simulation/ShipmentLifecycleRestOperationalStagesTest.php')
    )
  );
  assert.ok(
    existsSync(
      resolve(ROOT, 'backend/tests/Feature/Simulation/ShipmentLifecycleRestCanonicalBoundaryTest.php')
    )
  );
});
