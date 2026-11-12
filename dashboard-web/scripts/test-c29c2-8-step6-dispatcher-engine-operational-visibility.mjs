/**
 * C2.9-C2.8 Step 6 — Dispatcher Engine Operational Visibility (static)
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

function readRepo(rel) {
  return readFileSync(resolve(ROOT, '..', rel), 'utf8');
}

test('Step 6 documentation exists', () => {
  const doc = readRepo('docs/C2.9C2_8_STEP6_DISPATCHER_ENGINE_OPERATIONAL_VISIBILITY.md');
  assert.match(doc, /C2\.9-C2\.8 Step 6/i);
  assert.match(doc, /HARD STOP/i);
  assert.match(doc, /engine_ops|BuildShipmentEngineOpsReadModelAction/);
});

test('backend read model exists and does not mutate status', () => {
  const action = readRepo(
    'backend/app/Actions/Shipment/BuildShipmentEngineOpsReadModelAction.php'
  );
  assert.match(action, /Cache::get/);
  assert.match(action, /ShipmentLifecycleEngine::CACHE_PREFIX|JourneyPlanStore|route_progress/);
  assert.equal(/->update\(|status_id\s*=/.test(action), false);
  assert.equal(/TransitionShipmentStatusAction/.test(action), false);
});

test('ShipmentResource exposes engine_ops and enrollment; no Engine write path', () => {
  const resource = readRepo('backend/app/Http/Resources/ShipmentResource.php');
  assert.match(resource, /engine_ops/);
  assert.match(resource, /engine_enrollment_status/);
  assert.match(resource, /BuildShipmentEngineOpsReadModelAction/);
});

test('lifecycle adapter remains canonical writer', () => {
  const adapter = readRepo(
    'backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php'
  );
  assert.match(adapter, /TransitionShipmentStatusAction/);
  const engine = readRepo('backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php');
  assert.equal(/function syncDatabaseStatus/.test(engine), false);
});

test('Dispatcher UI reads Engine ops without mutation controls', () => {
  const panel = read('src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx');
  assert.ok(panel);
  assert.match(panel, /Operational stage|engineOps|Resting/);
  assert.equal(/Mark Loading|Mark Resting|Mark Departed|updateStatus|\/status/i.test(panel), false);
  assert.match(panel, /EngineEnrollmentControls|Enroll|observe/i);

  const details = read('src/features/dispatcher/shipments/components/ShipmentDetailsPanel.tsx');
  assert.match(details, /EngineOperationsPanel/);

  const service = read('src/features/dispatcher/shipments/services/shipments.service.ts');
  assert.match(service, /getShipmentById/);
  assert.equal(/engine-enroll|\.patch\(|\.put\(|changeStatus/i.test(service), false);
});

test('canonical vs operational stage separation in UI helpers', () => {
  const helpers = read('src/features/dispatcher/shipments/utils/engineOperationalStage.ts');
  assert.match(helpers, /formatOpsListIndicator/);
  assert.match(helpers, /ENGINE_OPERATIONAL_TIMELINE/);
  assert.match(helpers, /Resting/);
  const strip = read('src/features/dispatcher/shipments/components/ShipmentLifecycleStrip.tsx');
  assert.match(strip, /Waiting Assignment|In Transit|Completed/);
});

test('list shows compact ops indicator; detail uses controlled refresh', () => {
  const row = read('src/features/dispatcher/shipments/components/ShipmentRow.tsx');
  assert.match(row, /formatOpsListIndicator/);
  const page = read('src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx');
  assert.match(page, /simulationEligible|ENGINE_REFRESH|silent/);
});

test('no second simulation or tracking architecture introduced in dispatcher shipments', () => {
  const panel = read('src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx');
  assert.equal(/SimulationRuntime|new RouteProgressEngine|createTracking/i.test(panel), false);
  assert.ok(existsSync(resolve(ROOT, '..', 'backend/app/Simulation/Lifecycle/ShipmentLifecycleEngine.php')));
});

test('focused backend visibility tests exist', () => {
  assert.ok(
    existsSync(
      resolve(ROOT, '..', 'backend/tests/Feature/Shipment/ShipmentEngineOpsVisibilityTest.php')
    )
  );
});
