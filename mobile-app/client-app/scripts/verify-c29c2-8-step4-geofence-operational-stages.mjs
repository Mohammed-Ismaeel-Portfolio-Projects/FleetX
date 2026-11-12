/**
 * C2.9-C2.8 Step 4 Static Verification — Geofence & Operational Stages
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

test('Step 4 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP4_GEOFENCE_OPERATIONAL_STAGES.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.8 Step 4/i);
  assert.match(doc, /GeofenceProximityDetector|geofence/i);
  assert.match(doc, /HARD STOP/i);
});

test('operational stage architecture and geofence detector exist', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Lifecycle/GeofenceProximityDetector.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Simulation/Lifecycle/ShipmentLifecycleStage.php')));
  const detector = read('backend/app/Simulation/Lifecycle/GeofenceProximityDetector.php');
  assert.match(detector, /pickupReached/);
  assert.match(detector, /deliveryReached/);
  assert.match(detector, /crossedInto/);
});

test('engine uses geofence detector and does not fast-forward ≥2% to EnRouteDelivery', () => {
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.ok(engine);
  assert.match(engine, /GeofenceProximityDetector/);
  assert.match(engine, /pickupReached|deliveryReached/);
  assert.equal(/completionPercentage\s*>=\s*2|>=\s*2\.0/.test(engine), false);
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
  assert.equal(/->update\(\s*\[\s*'status_id'|\$updates\s*=\s*\[\s*'status_id'/i.test(engine), false);
});

test('snapshot persists geofence / progress crossing fields', () => {
  const snap = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleSnapshot.php');
  assert.match(snap, /pickupGeofenceReached|pickup_geofence_reached/);
  assert.match(snap, /deliveryGeofenceReached|delivery_geofence_reached/);
  assert.match(snap, /lastTravelledM|last_travelled_m/);
  assert.match(snap, /lastRemainingDistanceM|last_remaining_distance_m/);
});

test('adapter maps departure to In Transit and delivery to Delivered; Loaded is ops-only', () => {
  const adapter = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php');
  assert.match(adapter, /TransitionShipmentStatusAction/);
  assert.match(adapter, /ShipmentLifecycleStage::Departed/);
  assert.match(adapter, /ShipmentLifecycleStage::EnRouteDelivery/);
  assert.match(adapter, /ShipmentStatus::IN_TRANSIT/);
  assert.match(adapter, /ShipmentStatus::DELIVERED/);
  // Loaded must not be grouped with Departed for In Transit (Step 4)
  assert.equal(
    /ShipmentLifecycleStage::Loaded,\s*\r?\n\s*ShipmentLifecycleStage::Departed/.test(adapter),
    false
  );
});

test('enrollment boundary remains active', () => {
  const module = read('backend/app/Simulation/Modules/ShipmentLifecycleModule.php');
  assert.match(module, /SimulationEligibleAssignments/);
  assert.equal(/Assignment::active\(\)/.test(module), false);
});

test('idempotency: stage change gate and adapter already-satisfied no-op', () => {
  const engine = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  const adapter = read('backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php');
  assert.match(engine, /stageChangedThisTick|\$changed/);
  assert.match(adapter, /if \(\$from === \$target\)/);
});

test('focused Step 4 tests exist', () => {
  assert.ok(
    existsSync(resolve(ROOT, 'backend/tests/Unit/Simulation/GeofenceProximityDetectorTest.php'))
  );
  assert.ok(
    existsSync(
      resolve(ROOT, 'backend/tests/Unit/Simulation/ShipmentLifecycleGeofenceOperationalStagesTest.php')
    )
  );
  assert.ok(
    existsSync(
      resolve(
        ROOT,
        'backend/tests/Feature/Simulation/ShipmentLifecycleGeofenceCanonicalIntegrationTest.php'
      )
    )
  );
  const feature = read(
    'backend/tests/Feature/Simulation/ShipmentLifecycleGeofenceCanonicalIntegrationTest.php'
  );
  assert.match(feature, /in_transit_history|Delivered/i);
  assert.match(feature, /changed_by_user_id/);
});
