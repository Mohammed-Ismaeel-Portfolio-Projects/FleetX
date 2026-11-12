/**
 * C2.9-C1.1 Static Verification — Post-Conversion Operational Readiness Audit
 * Audit-only phase: documentation and readiness findings; no production implementation.
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

test('C1.1 audit document exists with required phase id', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.1/i);
  assert.match(doc, /AUDIT ONLY|audit only/i);
});

test('audit contains required readiness sections', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  const sections = [
    /Verdict/i,
    /Shipment Lifecycle/i,
    /Assignment/i,
    /assignment\.\*/i,
    /Tracking/i,
    /Converted vs Direct/i,
    /Traceability/i,
    /Blocking/i,
    /Recommended Next/i,
    /Dependency Order/i,
    /Tests and Assertions/i,
    /Deferred/i,
  ];
  for (const section of sections) {
    assert.match(doc, section);
  }
});

test('assignment gate gap documented in C1.1 and resolved in C1.2', () => {
  const c11 = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  const c12 = read('docs/C2.9C1_2_ASSIGNMENT_AUTHORIZATION_GATES_IMPLEMENTATION.md');
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.match(c11, /assignment\.\*.*undefined|undefined.*assignment/i);
  assert.ok(c12);
  assert.match(c12, /C2\.9-C1\.2/i);
  assert.match(provider, /Gate::define\('assignment\.view'/);
});

test('audit confirms conversion path remains complete (B3.x)', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  assert.match(doc, /ConvertShipmentOrderToShipmentAction/);
  assert.match(doc, /Waiting Assignment/);
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')));
  const routes = read('backend/routes/api.php');
  assert.match(routes, /v1\.shipment-requests\.convert/);
});

test('audit identifies ShipmentStatus canonical class', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  const status = read('backend/app/Support/ShipmentStatus.php');
  assert.match(doc, /ShipmentStatus/);
  assert.match(status, /CONVERSION_INITIAL/);
  assert.match(status, /WAITING_ASSIGNMENT/);
});

test('audit documents no TransitionShipmentStatusAction for Shipment', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  assert.match(doc, /TransitionShipmentStatusAction.*No|No.*TransitionShipmentStatusAction/is);
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/Shipment/TransitionShipmentStatusAction.php')),
    false
  );
});

test('audit documents tracking requires active assignment', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  const tracking = read('backend/app/Actions/Tracking/UpdateTrackingStateAction.php');
  assert.match(doc, /active assignment|activeAssignment/i);
  assert.match(tracking, /activeAssignment/);
});

test('audit recommends next phase without implementing it', () => {
  const doc = read('docs/C2.9C1_1_POST_CONVERSION_OPERATIONAL_READINESS_AUDIT.md');
  assert.match(doc, /C2\.9-C1\.2|C1\.2.*assignment/i);
  assert.match(doc, /HARD STOP/i);
  assert.match(doc, /Production Code Changed[\s\S]*NONE/i);
});

test('no forbidden implementation introduced during C1.1', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/Shipment/TransitionShipmentStatusAction.php')),
    false
  );
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
});

test('B3.x conversion tests still exist (regression anchor)', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/tests/Feature/ShipmentOrder/ShipmentOrderConversionConcurrencyTest.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/tests/Feature/ShipmentOrder/DispatcherShipmentRequestConversionTest.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/tests/Feature/ShipmentOrder/ConvertShipmentOrderToShipmentActionTest.php')));
});

test('assignment API exists with C1.2 authorization gates wired', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /AssignmentController/);
  const policy = read('backend/app/Policies/AssignmentPolicy.php');
  assert.match(policy, /assignment\.create/);
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.match(provider, /Gate::define\('assignment\.view'/);
});
