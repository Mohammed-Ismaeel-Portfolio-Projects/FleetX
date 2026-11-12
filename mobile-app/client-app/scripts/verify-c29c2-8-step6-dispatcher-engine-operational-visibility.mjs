/**
 * C2.9-C2.8 Step 6 Static Verification (repo-root via client-app scripts)
 * Delegates structural checks; primary UI verifier lives in dashboard-web.
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

test('Step 6 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP6_DISPATCHER_ENGINE_OPERATIONAL_VISIBILITY.md');
  assert.ok(doc);
  assert.match(doc, /HARD STOP/i);
});

test('backend engine ops read model and resource wiring exist', () => {
  assert.ok(
    existsSync(resolve(ROOT, 'backend/app/Actions/Shipment/BuildShipmentEngineOpsReadModelAction.php'))
  );
  const resource = read('backend/app/Http/Resources/ShipmentResource.php');
  assert.match(resource, /engine_ops/);
  assert.match(resource, /BuildShipmentEngineOpsReadModelAction/);
});

test('dispatcher EngineOperationsPanel exists and is read-only', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx'
  );
  assert.ok(panel);
  assert.equal(/Mark Resting|Mark Loading|TransitionShipmentStatus/i.test(panel), false);
});

test('Steps 2–5 artifacts remain', () => {
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C2_8_STEP5_ROUTE_INTELLIGENCE_REST_STOPS.md')));
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C2_8_STEP4_GEOFENCE_OPERATIONAL_STAGES.md')));
  assert.ok(
    existsSync(resolve(ROOT, 'backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php'))
  );
});
