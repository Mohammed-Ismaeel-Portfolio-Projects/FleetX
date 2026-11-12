/**
 * C2.9-C2.8 Step 7 Static Verification (client-app registry)
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

test('Step 7 documentation exists', () => {
  const doc = read('docs/C2.9C2_8_STEP7_DISPATCHER_ENGINE_ENROLLMENT.md');
  assert.ok(doc);
  assert.match(doc, /HARD STOP/i);
});

test('single engine-enroll route and Dispatcher enroll UI exist', () => {
  const routes = read('backend/routes/api.php');
  assert.equal((routes.match(/Route::post\([^)]*engine-enroll/g) || []).length, 1);
  const controls = read(
    'dashboard-web/src/features/dispatcher/shipments/components/EngineEnrollmentControls.tsx'
  );
  assert.ok(controls);
  assert.match(controls, /Enroll in Engine/);
});

test('eligibility helper + Step 6 panel reuse', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Support/EngineEnrollmentEligibility.php')));
  const panel = read(
    'dashboard-web/src/features/dispatcher/shipments/components/EngineOperationsPanel.tsx'
  );
  assert.match(panel, /EngineEnrollmentControls/);
});

test('enrollment action does not write shipment status', () => {
  const action = read('backend/app/Actions/Assignment/EnrollAssignmentInEngineAction.php');
  assert.equal(/TransitionShipmentStatusAction|status_id/.test(action), false);
});
