/**
 * C2.9-C2.8 Step 7 — Dispatcher Engine Enrollment (dashboard-web)
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const REPO = resolve(ROOT, '..');

function readDash(rel) {
  const p = resolve(ROOT, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

function readRepo(rel) {
  const p = resolve(REPO, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

test('Step 7 documentation exists', () => {
  const doc = readRepo('docs/C2.9C2_8_STEP7_DISPATCHER_ENGINE_ENROLLMENT.md');
  assert.match(doc, /C2\.9-C2\.8 Step 7/i);
  assert.match(doc, /HARD STOP/i);
  assert.match(doc, /engine-enroll/);
});

test('existing enrollment API is reused; no duplicate endpoint', () => {
  const routes = readRepo('backend/routes/api.php');
  const postRoutes = routes.match(/Route::post\([^)]*engine-enroll/g) || [];
  assert.equal(postRoutes.length, 1);
  assert.match(routes, /v1\.assignments\.engine-enroll/);
  const action = readRepo('backend/app/Actions/Assignment/EnrollAssignmentInEngineAction.php');
  assert.match(action, /EngineEnrollmentEligibility|lockForUpdate/);
  assert.equal(/status_id|TransitionShipmentStatusAction/.test(action), false);
});

test('shared eligibility assessor is backend source of truth', () => {
  const elig = readRepo('backend/app/Support/EngineEnrollmentEligibility.php');
  assert.match(elig, /can_enroll/);
  assert.match(elig, /ASSIGNED/);
  const readModel = readRepo(
    'backend/app/Actions/Shipment/BuildShipmentEngineOpsReadModelAction.php'
  );
  assert.match(readModel, /EngineEnrollmentEligibility/);
  assert.match(readModel, /can_enroll/);
});

test('Dispatcher has controlled enrollment CTA without ops-stage mutation', () => {
  const controls = readDash(
    'src/features/dispatcher/shipments/components/EngineEnrollmentControls.tsx'
  );
  assert.match(controls, /Enroll in Engine/);
  assert.match(controls, /canEnroll|enrollment\.canEnroll/);
  assert.equal(/Mark Loading|Mark Resting|Mark Departed|Mark Unloading/i.test(controls), false);

  const service = readDash('src/features/dispatcher/shipments/services/assignments.service.ts');
  assert.match(service, /engine-enroll/);
  assert.equal(/\.patch\(|changeStatus|Mark /i.test(service), false);

  const panel = readDash(
    'src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx'
  );
  assert.match(panel, /EngineEnrollmentControls/);
});

test('Step 6 visibility remains wired', () => {
  const panel = readDash(
    'src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx'
  );
  assert.match(panel, /Operational timeline|routeProgress|Rest/);
  assert.ok(
    existsSync(
      resolve(REPO, 'docs/C2.9C2_8_STEP6_DISPATCHER_ENGINE_OPERATIONAL_VISIBILITY.md')
    )
  );
});

test('lifecycle adapter remains authoritative', () => {
  const adapter = readRepo(
    'backend/app/Simulation/Lifecycle/ShipmentLifecycleTransitionAdapter.php'
  );
  assert.match(adapter, /TransitionShipmentStatusAction/);
});
